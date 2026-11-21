<?php

use App\Actions\ServerPhp\MovePhpToSuryRepository;
use App\Models\Server;
use App\Services\Ssh\SshConnection;
use App\Services\Ssh\SshService;

/**
 * Fake the server's SSH connection, recording each sudo command. `$failOn`
 * makes the sudo command containing that text fail.
 *
 * @param  array<int, string>  $commands
 */
function fakeSuryMoveServer(array &$commands, bool &$disconnected, ?string $failOn = null): void
{
    $connection = Mockery::mock(SshConnection::class);
    $connection->shouldReceive('sudo')->andReturnUsing(function (string $command) use (&$commands, $failOn) {
        $commands[] = $command;

        if ($failOn !== null && str_contains($command, $failOn)) {
            throw new RuntimeException('The ondrej/php PPA is still listed in /etc/apt/sources.list.');
        }

        return '';
    });
    $connection->shouldReceive('disconnect')->andReturnUsing(function () use (&$disconnected) {
        $disconnected = true;
    });

    $ssh = Mockery::mock(SshService::class);
    $ssh->shouldReceive('connect')->andReturn($connection);
    app()->instance(SshService::class, $ssh);
}

it('switches to packages.sury.org, refreshes the package lists, then reinstalls the PPA builds from there', function () {
    $commands = [];
    $disconnected = false;
    fakeSuryMoveServer($commands, $disconnected);

    app(MovePhpToSuryRepository::class)->execute(Server::factory()->active()->make());

    expect($commands)->toHaveCount(3);
    expect($commands[0])->toStartWith('sh -c ')->toContain('https://packages.sury.org/php/')->toContain('ondrej-ubuntu-php-');
    expect($commands[1])->toBe('apt-get -o DPkg::Lock::Timeout=120 update');
    expect($commands[2])->toStartWith('sh -c ')->toContain('--no-remove --allow-downgrades');
    expect($disconnected)->toBeTrue();
});

it('changes no packages when the package source cannot be switched', function () {
    $commands = [];
    $disconnected = false;
    fakeSuryMoveServer($commands, $disconnected, failOn: 'packages.sury.org/php/');

    expect(fn () => app(MovePhpToSuryRepository::class)->execute(Server::factory()->active()->make()))
        ->toThrow(RuntimeException::class, 'The ondrej/php PPA is still listed in /etc/apt/sources.list.');

    expect($commands)->toHaveCount(1);
    expect($disconnected)->toBeTrue();
});
