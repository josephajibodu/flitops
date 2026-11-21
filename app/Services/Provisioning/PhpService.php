<?php

namespace App\Services\Provisioning;

class PhpService
{
    /**
     * Point apt at packages.sury.org for PHP, the way its README does, and
     * drop the ondrej/php PPA that Flitops used to add. Ondřej Surý froze
     * that PPA in November 2026 and now publishes the same packages only at
     * packages.sury.org. Runs as root and is safe to run again: on a server
     * that's already set up it changes nothing.
     *
     * The PPA files removed are the ones `add-apt-repository` creates. If the
     * PPA is still listed somewhere else afterwards, the script stops and says
     * where, rather than editing a file it doesn't own.
     */
    private const SURY_REPOSITORY_SCRIPT = <<<'SH'
        set -e

        apt_sources() {
            apt-get indextargets --no-release-info --format '$(REPO_URI) $(SOURCESENTRY)' 2>/dev/null |
                grep -E "^https?://$1/? " | sed -e 's/^[^ ]* //' -e 's/:[0-9]*$//' | sort -u
        }

        if [ -z "$(apt_sources 'packages\.sury\.org/php')" ]; then
            if [ ! -f /usr/share/keyrings/debsuryorg-archive-keyring.gpg ]; then
                keyring=$(mktemp)
                curl -fsSL -o "$keyring" https://packages.sury.org/debsuryorg-archive-keyring.deb
                dpkg -i "$keyring"
                rm -f "$keyring"
            fi

            . /etc/os-release
            printf '%s\n' 'Types: deb' 'URIs: https://packages.sury.org/php/' "Suites: $VERSION_CODENAME" 'Components: main' \
                'Signed-By: /usr/share/keyrings/debsuryorg-archive-keyring.gpg' > /etc/apt/sources.list.d/php.sources
        fi

        rm -f /etc/apt/sources.list.d/ondrej-ubuntu-php-* /etc/apt/trusted.gpg.d/ondrej-ubuntu-php.gpg*

        leftover=$(apt_sources 'ppa\.launchpad(content)?\.net/ondrej/php/ubuntu')
        if [ -n "$leftover" ]; then
            echo "The ondrej/php PPA is still listed in $leftover. Remove it there, then try again." >&2
            exit 1
        fi
        SH;

    public function install(ProvisioningContext $context, string $phpVersion): void
    {
        $context->packages->ensureInstalled('curl');

        $context->runner->runQuietly(self::suryRepositoryCommand(), 300);
        $context->packages->setup();

        $packages = [
            "php{$phpVersion}-fpm",
            "php{$phpVersion}-cli",
            "php{$phpVersion}-common",
            "php{$phpVersion}-mysql",
            "php{$phpVersion}-pgsql",
            "php{$phpVersion}-sqlite3",
            "php{$phpVersion}-redis",
            "php{$phpVersion}-curl",
            "php{$phpVersion}-gd",
            "php{$phpVersion}-mbstring",
            "php{$phpVersion}-xml",
            "php{$phpVersion}-zip",
            "php{$phpVersion}-bcmath",
            "php{$phpVersion}-intl",
            "php{$phpVersion}-readline",
        ];

        foreach ($packages as $package) {
            $context->packages->ensureInstalled($package);
        }

        $serviceName = "php{$phpVersion}-fpm";

        $context->services->enable($serviceName);
        $context->services->start($serviceName);
    }

    public function configureFpmPool(ProvisioningContext $context, ?string $phpVersion = null): void
    {
        $phpVersion = $phpVersion ?? $context->server->php_version;
        $serverUser = $context->serverUser;

        $poolConfig = <<<CONF
[{$serverUser}]
user = {$serverUser}
group = {$serverUser}
listen = /run/php/php{$phpVersion}-fpm-{$serverUser}.sock
listen.owner = www-data
listen.group = www-data
pm = dynamic
pm.max_children = 5
pm.start_servers = 2
pm.min_spare_servers = 1
pm.max_spare_servers = 3
CONF;

        $path = "/etc/php/{$phpVersion}/fpm/pool.d/{$serverUser}.conf";

        $context->files->put($path, $poolConfig);

        $context->services->restart("php{$phpVersion}-fpm");
    }

    /**
     * The shell command that sets up packages.sury.org as the server's PHP
     * source. Run it as root.
     */
    public static function suryRepositoryCommand(): string
    {
        return 'sh -c '.escapeshellarg(self::SURY_REPOSITORY_SCRIPT);
    }
}
