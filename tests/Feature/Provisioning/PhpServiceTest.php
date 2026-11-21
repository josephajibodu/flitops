<?php

use App\Contracts\Provisioning\PackageManager;
use App\Contracts\Provisioning\ServiceManager;
use App\Contracts\Remote\RemoteCommandRunner;
use App\Contracts\Remote\RemoteFilesystem;
use App\Models\Server;
use App\Services\Provisioning\PhpService;
use App\Services\Provisioning\ProvisioningContext;

it('installs PHP from packages.sury.org on a new server, without adding the old PPA', function () {
    $steps = [];
    $runner = Mockery::mock(RemoteCommandRunner::class);
    $runner->shouldReceive('runQuietly')->andReturnUsing(function (string $command) use (&$steps) {
        $steps[] = $command;
    });
    $packages = Mockery::mock(PackageManager::class);
    $packages->shouldReceive('setup')->andReturnUsing(function () use (&$steps) {
        $steps[] = 'update package lists';
    });
    $packages->shouldReceive('ensureInstalled')->andReturnUsing(function (string $package) use (&$steps) {
        $steps[] = "install {$package}";
    });
    $context = new ProvisioningContext(
        server: Server::factory()->make(),
        runner: $runner,
        files: Mockery::mock(RemoteFilesystem::class),
        packages: $packages,
        services: Mockery::mock(ServiceManager::class)->shouldIgnoreMissing(),
        serverUser: 'artisan',
        sudoPassword: '',
        databasePassword: '',
    );

    app(PhpService::class)->install($context, '8.4');

    expect($steps[0])->toBe('install curl');
    expect($steps[1])->toStartWith('sh -c ')->toContain('https://packages.sury.org/php/');
    expect($steps[2])->toBe('update package lists');
    expect($steps[3])->toBe('install php8.4-fpm');
    expect(collect($steps)->filter(fn (string $step) => str_contains($step, 'add-apt-repository')))->toBeEmpty();
});
