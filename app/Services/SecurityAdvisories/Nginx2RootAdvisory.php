<?php

namespace App\Services\SecurityAdvisories;

use App\Enums\ServerStatus;
use App\Enums\WebServer;
use App\Exceptions\SshCommandException;
use App\Models\Server;
use App\Services\Ssh\SshConnection;
use RuntimeException;

/**
 * NGINX2Root (18 Nov 2026): flaws in nginx's HTTP/2 handling let anyone on
 * the internet run commands on the server. Every site Flitops sets up turns
 * HTTP/2 on, so every nginx server is exposed until patched.
 *
 * Flitops servers install nginx from Ubuntu's own archive, and Ubuntu shipped
 * the fix in its normal security updates (USN-9159-1, 19 Nov 2026). Ubuntu
 * keeps nginx's version number and changes only the suffix after it, so a
 * server is checked against the exact fixed package for its release.
 */
class Nginx2RootAdvisory implements SecurityAdvisory
{
    public const KEY = '2026-11-nginx2root';

    /**
     * The first package of Ubuntu's own nginx with the fix, per release.
     * Releases not listed (20.04 and older) get it only through paid Ubuntu Pro.
     *
     * @var array<string, string>
     */
    public const FIXED_VERSIONS = [
        '22.04' => '1.18.0-6ubuntu14.23',
        '24.04' => '1.24.0-2ubuntu7.20',
        '26.04' => '1.28.3-2ubuntu1.13',
        '26.10' => '1.30.4-7ubuntu1.2',
    ];

    private const APT = 'apt-get -o DPkg::Lock::Timeout=120';

    /**
     * Fetch the package lists. A package source renaming itself stops apt
     * until someone accepts the new name, as the ondrej/php PPA did when it
     * was marked deprecated. Only a changed name is accepted here: package
     * signatures are still checked.
     */
    private const UPDATE_COMMAND = self::APT.' update --allow-releaseinfo-change-label';

    private const UPGRADE_COMMAND = 'DEBIAN_FRONTEND=noninteractive '.self::APT.' -o Dpkg::Options::=--force-confold install --only-upgrade -y nginx nginx-common nginx-core';

    public function key(): string
    {
        return self::KEY;
    }

    public function title(): string
    {
        return 'Nginx remote code execution (NGINX2Root)';
    }

    public function severity(): string
    {
        return 'critical';
    }

    public function publishedOn(): string
    {
        return '2026-11-20';
    }

    public function summary(): string
    {
        return 'A group of flaws in nginx, the web server that serves your sites, lets anyone on the internet run commands on your server by sending it specially crafted HTTPS traffic. Combined with a separate Linux flaw, an attacker could take full control of the server. Ubuntu has released a fixed nginx; it only protects you once it is installed and nginx is restarted.';
    }

    public function fixDescription(): string
    {
        return 'Flitops installs Ubuntu\'s fixed nginx package, checks your nginx configuration still works with it, and then restarts nginx. Your sites stop answering for a few seconds during the restart. Your nginx configuration files are kept as they are. If the configuration check fails, nginx is not restarted and your sites keep running as before.';
    }

    public function referenceUrl(): ?string
    {
        return 'https://ubuntu.com/security/notices/USN-9159-1';
    }

    public function appliesTo(Server $server): bool
    {
        return $server->web_server === WebServer::Nginx
            && $server->status === ServerStatus::Active;
    }

    public function check(SshConnection $connection, Server $server): SecurityCheckResult
    {
        $release = trim($connection->exec('. /etc/os-release && echo "$VERSION_ID"'));
        $fixedVersion = self::FIXED_VERSIONS[$release] ?? null;

        if ($fixedVersion === null) {
            return SecurityCheckResult::unsupported("This server runs Ubuntu {$release}, which no longer gets this fix for free. Move your sites to a new server on Ubuntu 24.04 or later, or upgrade the server's Ubuntu.");
        }

        $installedVersion = trim($connection->exec('dpkg-query -W -f=\'${Version}\' nginx 2>/dev/null || true'));

        if ($installedVersion === '') {
            return SecurityCheckResult::covered('Nginx isn\'t installed on this server, so it isn\'t affected.');
        }

        if (! $this->isAtLeast($connection, $installedVersion, $fixedVersion)) {
            return SecurityCheckResult::needsFix("Nginx {$installedVersion} is installed. The fix for Ubuntu {$release} is in {$fixedVersion} and later.");
        }

        if ($this->runningCopyIsReplaced($connection)) {
            return SecurityCheckResult::needsRestart("The fixed nginx ({$installedVersion}) is installed, but nginx hasn't been restarted since, so the old copy is still the one serving your sites.");
        }

        return SecurityCheckResult::covered("Nginx {$installedVersion} is installed and running, which includes the fix.");
    }

    public function fix(SshConnection $connection, Server $server, callable $log): void
    {
        $release = trim($connection->exec('. /etc/os-release && echo "$VERSION_ID"'));

        if (! array_key_exists($release, self::FIXED_VERSIONS)) {
            throw new RuntimeException("Ubuntu {$release} no longer gets this fix for free, so there is nothing to install.");
        }

        $this->run($connection, self::UPDATE_COMMAND, 300, $log);
        $this->run($connection, self::UPGRADE_COMMAND, 600, $log);

        try {
            $this->run($connection, 'nginx -t 2>&1', 30, $log);
        } catch (SshCommandException $e) {
            throw new RuntimeException("Nginx's configuration check failed with the new version, so nginx was not restarted and your sites are still running as before.\n{$e->output}", 0, $e);
        }

        $this->run($connection, 'systemctl restart nginx', 60, $log);
    }

    public function manualSteps(Server $server): array
    {
        $user = config('server.user');
        $sshCommand = $server->ssh_port === 22
            ? "ssh {$user}@{$server->ip_address}"
            : "ssh {$user}@{$server->ip_address} -p {$server->ssh_port}";
        $fixedVersion = self::FIXED_VERSIONS[$server->ubuntu_version] ?? null;
        $fixedVersionHint = $fixedVersion === null
            ? 'Compare it with the fixed version for your Ubuntu release in the Ubuntu notice.'
            : "This server runs Ubuntu {$server->ubuntu_version}, so you need {$fixedVersion} or later.";

        return [
            [
                'title' => 'Connect to the server',
                'explanation' => 'Open a terminal on your computer and log in to the server. This needs an SSH key added to the server under SSH Keys.',
                'command' => $sshCommand,
            ],
            [
                'title' => 'See which nginx you have',
                'explanation' => "This prints the version of the nginx package that's installed. {$fixedVersionHint} Ubuntu keeps the first part of the number the same and changes only the part after the dash.",
                'command' => 'dpkg-query -W -f=\'${Version}\n\' nginx',
            ],
            [
                'title' => 'Fetch the latest package list',
                'explanation' => 'This asks Ubuntu which updates are available. It doesn\'t install anything yet. The extra option accepts a package source that has only changed its name, such as the PHP source now labelled "deprecated"; without it apt stops with "changed its \'Label\' value".',
                'command' => 'sudo apt-get update --allow-releaseinfo-change-label',
            ],
            [
                'title' => 'Upgrade nginx only',
                'explanation' => '"--only-upgrade" updates the nginx packages already on the server and installs nothing new. If it asks about keeping your configuration files, keep your current ones.',
                'command' => 'sudo apt-get install --only-upgrade nginx nginx-common nginx-core',
            ],
            [
                'title' => 'Test the configuration',
                'explanation' => 'This checks the new nginx still accepts your sites\' settings before anything restarts. You should see "syntax is ok" and "test is successful". If you don\'t, stop here: your sites keep running on the old nginx. Contact support.',
                'command' => 'sudo nginx -t',
            ],
            [
                'title' => 'Restart nginx',
                'explanation' => 'Until nginx restarts, the old copy stays in memory and keeps serving your sites. A "reload" isn\'t enough: it keeps the old program running. Your sites stop answering for a few seconds.',
                'command' => 'sudo systemctl restart nginx',
            ],
            [
                'title' => 'Check again in Flitops',
                'explanation' => 'Come back to this page and click "Check again" to confirm the server is protected.',
                'command' => null,
            ],
        ];
    }

    /**
     * Compare two Debian package versions the way apt does.
     */
    private function isAtLeast(SshConnection $connection, string $installedVersion, string $fixedVersion): bool
    {
        $answer = $connection->exec(sprintf(
            'dpkg --compare-versions %s ge %s && echo patched || echo vulnerable',
            escapeshellarg($installedVersion),
            escapeshellarg($fixedVersion),
        ));

        return trim($answer) === 'patched';
    }

    /**
     * When a package upgrade replaces the nginx program on disk, the copy
     * still running is marked "(deleted)" until nginx restarts.
     */
    private function runningCopyIsReplaced(SshConnection $connection): bool
    {
        $runningProgram = $connection->exec('sudo readlink /proc/$(cat /run/nginx.pid 2>/dev/null)/exe 2>/dev/null || true');

        return str_contains($runningProgram, '(deleted)');
    }

    /**
     * @param  callable(string): void  $log
     */
    private function run(SshConnection $connection, string $command, int $timeout, callable $log): void
    {
        $output = $connection->sudo($command, $timeout);

        $log(trim("$ sudo {$command}\n{$output}"));
    }
}
