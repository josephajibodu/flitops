<?php

namespace App\Actions\ServerPhp;

use App\Models\Server;
use App\Services\Provisioning\PhpService;
use App\Services\Ssh\SshConnection;
use App\Services\Ssh\SshService;

/**
 * Move a server's PHP packages from the frozen ondrej/php PPA to
 * packages.sury.org, where the same maintainer now publishes them.
 *
 * Nothing about PHP itself changes. The PPA and packages.sury.org label the
 * same build differently (`8.4.28-2+ubuntu24.04.1+deb.sury.org+1` versus
 * `8.4.28-2+debsuryorg24.04.1+php+1`), and apt ranks the PPA's label higher.
 * Left alone, the server would keep the PPA builds, and adding an extension
 * such as gmp would fail: packages.sury.org's extension needs its own exact
 * build of php8.x-common. So each installed PPA build that packages.sury.org
 * has the same build of is reinstalled from there. This restarts PHP-FPM
 * for a few seconds. Packages packages.sury.org has no same build of are
 * left as they are and update from it later as usual.
 *
 * Safe to run again: on a server that's already moved, it only refreshes
 * the package lists.
 */
class MovePhpToSuryRepository
{
    /**
     * Reinstall each installed PPA build (its version contains
     * `+deb.sury.org+`) from packages.sury.org when that has the same build,
     * meaning the version matches up to the first `+`. Config files are kept,
     * nothing may be removed, and packages that were installed only as
     * dependencies are marked that way again afterwards.
     */
    private const REINSTALL_SCRIPT = <<<'SH'
        set -e
        export DEBIAN_FRONTEND=noninteractive UCF_FORCE_CONFFOLD=1

        targets=''
        for package in $(dpkg-query -W -f='${db:Status-Status} ${Package} ${Version}\n' 'php*' 2>/dev/null | awk '$1 == "installed" && $3 ~ /[+]deb[.]sury[.]org[+]/ { print $2 }'); do
            installed=$(dpkg-query -W -f='${Version}' "$package")
            candidate=$(apt-cache madison "$package" | awk -F ' [|] ' '$3 ~ /packages[.]sury[.]org/ { print $2; exit }')
            if [ -n "$candidate" ] && [ "${candidate%%+*}" = "${installed%%+*}" ]; then
                targets="$targets $package=$candidate"
            fi
        done

        [ -n "$targets" ] || exit 0

        automatic=$(apt-mark showauto | grep '^php' || true)
        apt-get -o DPkg::Lock::Timeout=120 -o Dpkg::Options::=--force-confdef -o Dpkg::Options::=--force-confold \
            install -y --no-remove --allow-downgrades $targets
        [ -z "$automatic" ] || apt-mark auto $automatic >/dev/null
        SH;

    public function __construct(
        private SshService $sshService,
    ) {}

    public function execute(Server $server): void
    {
        $connection = $this->sshService->connect($server);

        try {
            $this->onConnection($connection);
        } finally {
            $connection->disconnect();
        }
    }

    /**
     * Move the server over on a connection the caller already holds, and
     * leave its package lists fresh for whatever the caller installs next.
     */
    public function onConnection(SshConnection $connection): void
    {
        $connection->sudo(PhpService::suryRepositoryCommand(), 300);
        $connection->sudo('apt-get -o DPkg::Lock::Timeout=120 update', 300);
        $connection->sudo('sh -c '.escapeshellarg(self::REINSTALL_SCRIPT), 900);
    }
}
