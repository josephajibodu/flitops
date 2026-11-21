<?php

use App\Actions\ServerPhp\InstallPhpVersion;
use App\Models\Server;
use App\Services\Ssh\SshConnection;
use App\Services\Ssh\SshService;

it('moves the server to packages.sury.org before installing the PHP version, without adding the old PPA', function () {
    $commands = [];
    $connection = Mockery::mock(SshConnection::class);
    $connection->shouldReceive('sudo')->andReturnUsing(function (string $command) use (&$commands) {
        $commands[] = $command;

        return '';
    });
    $connection->shouldReceive('disconnect');
    $ssh = Mockery::mock(SshService::class);
    $ssh->shouldReceive('connect')->andReturn($connection);
    app()->instance(SshService::class, $ssh);

    app(InstallPhpVersion::class)->execute(Server::factory()->active()->make(), '8.5');

    $movedAt = collect($commands)->search(fn (string $command) => str_contains($command, 'https://packages.sury.org/php/'));
    $installedAt = collect($commands)->search(fn (string $command) => str_contains($command, 'apt-get install -y php8.5-fpm'));
    expect($movedAt)->toBeInt();
    expect($installedAt)->toBeGreaterThan($movedAt);
    expect(collect($commands)->filter(fn (string $command) => str_contains($command, 'add-apt-repository')))->toBeEmpty();
});
