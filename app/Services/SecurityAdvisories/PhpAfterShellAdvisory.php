<?php

namespace App\Services\SecurityAdvisories;

use App\Enums\ServerStatus;
use App\Exceptions\SshCommandException;
use App\Models\Server;
use App\Services\Ssh\SshConnection;
use RuntimeException;

/**
 * phpAfterShell (CVE-2026-126714, 19 Nov 2026): PHP's default file session
 * handler can leave a session file half old and half new, and reading it
 * back can run an attacker's code. The same PHP release fixes several other
 * serious flaws, some of them in PHP-FPM.
 *
 * Flitops installs PHP from Ondřej Surý's packages (ppa:ondrej/php, now also
 * at packages.sury.org). Ubuntu's automatic security updates only cover
 * Ubuntu's own archive, and Flitops never upgrades packages by itself, so
 * customers' PHP stays unpatched until someone upgrades it.
 */
class PhpAfterShellAdvisory implements SecurityAdvisory
{
    public const KEY = '2026-11-phpaftershell';

    /**
     * The first package with the fix, per PHP version Flitops installs.
     * PHP 8.1 is past its end of life, so PHP itself has no fix for it; Surý
     * backported one in his packaging revision 8.1.34-13, which keeps the
     * 8.1.34 number. Compared the way apt compares versions, so a newer
     * build such as 8.4.28-2+ubuntu24.04.1+deb.sury.org+1 counts as fixed.
     *
     * @var array<string, string>
     */
    public const FIXED_VERSIONS = [
        '8.1' => '8.1.34-13',
        '8.2' => '8.2.36',
        '8.3' => '8.3.37',
        '8.4' => '8.4.28',
        '8.5' => '8.5.13',
    ];

    private const APT = 'apt-get -o DPkg::Lock::Timeout=120';

    /**
     * Fetch the package lists. A package source renaming itself stops apt
     * until someone accepts the new name, as the ondrej/php PPA did when it
     * was marked deprecated. Only a changed name is accepted here: package
     * signatures are still checked.
     */
    private const UPDATE_COMMAND = self::APT.' update --allow-releaseinfo-change-label';

    /**
     * Followed by the installed PHP packages. "--no-remove" makes apt stop
     * rather than uninstall anything to make the upgrade fit.
     */
    private const UPGRADE_COMMAND = 'DEBIAN_FRONTEND=noninteractive '.self::APT.' -o Dpkg::Options::=--force-confold install --only-upgrade --no-remove -y';

    /**
     * Every PHP 8 package dpkg knows about, with its state and version.
     */
    private const LIST_PACKAGES_COMMAND = 'dpkg-query -W -f=\'${db:Status-Status} ${Package} ${Version}\n\' \'php8.*\' 2>/dev/null || true';

    public function key(): string
    {
        return self::KEY;
    }

    public function title(): string
    {
        return 'PHP remote code execution (phpAfterShell)';
    }

    public function severity(): string
    {
        return 'critical';
    }

    public function publishedOn(): string
    {
        return '2026-11-21';
    }

    public function summary(): string
    {
        return 'PHP has fixed a group of serious flaws. The worst, called phpAfterShell, is in the way PHP saves visitors\' sessions to files: on a site that uses PHP\'s built-in sessions, an attacker may be able to run their own code on your server. Other flaws fixed at the same time affect PHP-FPM, the part of PHP that runs your sites. Your server\'s automatic security updates don\'t cover PHP, because it comes from a separate package source, so it stays unprotected until it is upgraded and PHP-FPM is restarted.';
    }

    public function fixDescription(): string
    {
        return 'Flitops upgrades the PHP packages already on your server, for every PHP version installed, and adds no new ones. It then checks each version\'s PHP-FPM configuration still works and restarts PHP-FPM. Your sites stop answering for a few seconds during each restart. Your PHP settings and configuration files are kept as they are. If a configuration check fails, Flitops stops without restarting that PHP version, so it keeps running as before.';
    }

    public function referenceUrl(): ?string
    {
        return 'https://github.com/php/php-src/security/advisories/GHSA-fm2m-fjf5-v3cp';
    }

    public function appliesTo(Server $server): bool
    {
        return $server->hasPhp()
            && $server->status === ServerStatus::Active;
    }

    public function check(SshConnection $connection, Server $server): SecurityCheckResult
    {
        $installed = $this->installedPhpVersions($this->installedPackages($connection));

        if ($installed === []) {
            return SecurityCheckResult::covered(sprintf(
                'None of the PHP versions Flitops installs (%s to %s) is on this server, so there is nothing to update.',
                array_key_first(self::FIXED_VERSIONS),
                array_key_last(self::FIXED_VERSIONS),
            ));
        }

        $outdated = [];

        foreach ($installed as $phpVersion => $packageVersions) {
            foreach ($packageVersions as $packageVersion) {
                if (! $this->isAtLeast($connection, $packageVersion, self::FIXED_VERSIONS[$phpVersion])) {
                    $outdated[] = sprintf('PHP %s has %s installed and needs %s or later', $phpVersion, $this->shortVersion($packageVersion), self::FIXED_VERSIONS[$phpVersion]);

                    break;
                }
            }
        }

        if ($outdated !== []) {
            return SecurityCheckResult::needsFix(implode('; ', $outdated).'.');
        }

        $notRestarted = array_filter(
            array_keys($installed),
            fn (string $phpVersion): bool => $this->runningFpmIsReplaced($connection, $phpVersion),
        );

        if ($notRestarted !== []) {
            return SecurityCheckResult::needsRestart(sprintf(
                'The fixed PHP is installed, but PHP-FPM %s hasn\'t been restarted since, so the old copy is still the one running your sites.',
                $this->listVersions($notRestarted),
            ));
        }

        return SecurityCheckResult::covered(sprintf(
            'PHP %s is installed and running with the fix.',
            $this->listVersions(array_keys($installed)),
        ));
    }

    public function fix(SshConnection $connection, Server $server, callable $log): void
    {
        $packages = array_keys($this->installedPackages($connection));

        if ($packages === []) {
            return;
        }

        $this->run($connection, self::UPDATE_COMMAND, 300, $log);
        $this->run($connection, self::UPGRADE_COMMAND.' '.implode(' ', $packages), 900, $log);

        foreach ($packages as $package) {
            if (preg_match('/^php(8\.\d+)-fpm$/', $package, $match) !== 1) {
                continue;
            }

            $phpVersion = $match[1];

            try {
                $this->run($connection, "php-fpm{$phpVersion} -t 2>&1", 30, $log);
            } catch (SshCommandException $e) {
                throw new RuntimeException("PHP-FPM {$phpVersion}'s configuration check failed with the new version, so it was not restarted and your sites are still running as before.\n{$e->output}", 0, $e);
            }

            $this->run($connection, "systemctl restart php{$phpVersion}-fpm", 60, $log);
        }
    }

    public function manualSteps(Server $server): array
    {
        $user = config('server.user');
        $sshCommand = $server->ssh_port === 22
            ? "ssh {$user}@{$server->ip_address}"
            : "ssh {$user}@{$server->ip_address} -p {$server->ssh_port}";
        $phpVersion = $server->php_version ?? '8.4';
        $fixedVersions = collect(self::FIXED_VERSIONS)
            ->except('8.1')
            ->map(fn (string $fixedVersion, string $version): string => "{$fixedVersion} for PHP {$version}")
            ->join(', ', ' and ');

        return [
            [
                'title' => 'Connect to the server',
                'explanation' => 'Open a terminal on your computer and log in to the server. This needs an SSH key added to the server under SSH Keys.',
                'command' => $sshCommand,
            ],
            [
                'title' => 'See which PHP you have',
                'explanation' => "This lists each PHP version installed and its package version, in the third column. You need at least {$fixedVersions}. PHP 8.1 keeps the number 8.1.34 because the fix was added by the package maintainer, so look at the part after the dash: it needs ".self::FIXED_VERSIONS['8.1'].' or later.',
                'command' => 'dpkg -l \'php8.*-fpm\' \'php8.*-cli\' | grep ^ii',
            ],
            [
                'title' => 'Fetch the latest package list',
                'explanation' => 'This asks your package sources which updates are available. It doesn\'t install anything yet. The extra option accepts a package source that has only changed its name, such as the PHP source now labelled "deprecated"; without it apt stops with "changed its \'Label\' value".',
                'command' => 'sudo apt-get update --allow-releaseinfo-change-label',
            ],
            [
                'title' => 'Upgrade PHP only',
                'explanation' => 'This finds the PHP packages already on the server and upgrades just those. "--only-upgrade" means no new PHP packages are added. If it asks about keeping your configuration files, keep your current ones.',
                'command' => 'sudo apt-get install --only-upgrade $(dpkg -l \'php8.*\' | awk \'/^ii/ {print $2}\')',
            ],
            [
                'title' => 'Test the PHP-FPM configuration',
                'explanation' => "This checks the new PHP still accepts your settings before anything restarts. You should see \"test is successful\". Run it for each PHP version on the server, changing {$phpVersion} to the version. If it fails, stop here: your sites keep running as before. Contact support.",
                'command' => "sudo php-fpm{$phpVersion} -t",
            ],
            [
                'title' => 'Restart PHP-FPM',
                'explanation' => "Until PHP-FPM restarts, the old copy stays in memory and keeps running your sites. Run it for each PHP version, changing {$phpVersion} to the version. Your sites stop answering for a few seconds.",
                'command' => "sudo systemctl restart php{$phpVersion}-fpm",
            ],
            [
                'title' => 'Check again in Flitops',
                'explanation' => 'Come back to this page and click "Check again" to confirm the server is protected.',
                'command' => null,
            ],
        ];
    }

    /**
     * The installed PHP 8 packages and their versions.
     *
     * @return array<string, string>
     */
    private function installedPackages(SshConnection $connection): array
    {
        preg_match_all('/^installed (\S+) (\S+)$/m', $connection->exec(self::LIST_PACKAGES_COMMAND), $matches);

        return array_combine($matches[1], $matches[2]);
    }

    /**
     * The PHP versions Flitops knows the fix for, each with the versions of
     * its FPM and command-line packages.
     *
     * @param  array<string, string>  $packages
     * @return array<string, list<string>>
     */
    private function installedPhpVersions(array $packages): array
    {
        $installed = [];

        foreach ($packages as $package => $packageVersion) {
            if (preg_match('/^php(8\.\d+)-(?:fpm|cli)$/', $package, $match) !== 1 || ! array_key_exists($match[1], self::FIXED_VERSIONS)) {
                continue;
            }

            if (! in_array($packageVersion, $installed[$match[1]] ?? [], true)) {
                $installed[$match[1]][] = $packageVersion;
            }
        }

        ksort($installed, SORT_NATURAL);

        return $installed;
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
     * When a package upgrade replaces the PHP-FPM program on disk, the copy
     * still running is marked "(deleted)" until PHP-FPM restarts.
     */
    private function runningFpmIsReplaced(SshConnection $connection, string $phpVersion): bool
    {
        $runningProgram = $connection->exec("sudo readlink /proc/$(cat /run/php/php{$phpVersion}-fpm.pid 2>/dev/null)/exe 2>/dev/null || true");

        return str_contains($runningProgram, '(deleted)');
    }

    /**
     * "8.4.27-1+ubuntu24.04.1+deb.sury.org+1" becomes "8.4.27-1": the part
     * after the plus only names the build.
     */
    private function shortVersion(string $packageVersion): string
    {
        return explode('+', $packageVersion)[0];
    }

    /**
     * @param  list<string>  $versions
     */
    private function listVersions(array $versions): string
    {
        return collect($versions)->join(', ', ' and ');
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
