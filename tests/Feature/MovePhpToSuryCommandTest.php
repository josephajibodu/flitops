<?php

use App\Actions\ServerPhp\MovePhpToSuryRepository;
use App\Enums\ServerStatus;
use App\Models\Server;

it('requires --server or --all', function () {
    $this->artisan('flitops:php:move-to-sury')
        ->assertFailed();
});

it('moves only the given --server ids', function () {
    $target = Server::factory()->create(['status' => ServerStatus::Active]);
    Server::factory()->create(['status' => ServerStatus::Active]);
    $action = Mockery::mock(MovePhpToSuryRepository::class);
    $action->shouldReceive('execute')->once()->with(Mockery::on(fn ($server) => $server->id === $target->id));
    $this->app->instance(MovePhpToSuryRepository::class, $action);

    $this->artisan('flitops:php:move-to-sury', ['--server' => [$target->id]])
        ->assertSuccessful();
});

it('moves every active server with PHP with --all, skipping pending servers and servers without PHP', function () {
    $phpServer = Server::factory()->create(['status' => ServerStatus::Active]);
    Server::factory()->create(['status' => ServerStatus::Pending]);
    Server::factory()->cache()->create(['status' => ServerStatus::Active]);
    $action = Mockery::mock(MovePhpToSuryRepository::class);
    $action->shouldReceive('execute')->once()->with(Mockery::on(fn ($server) => $server->id === $phpServer->id));
    $this->app->instance(MovePhpToSuryRepository::class, $action);

    $this->artisan('flitops:php:move-to-sury', ['--all' => true])
        ->assertSuccessful();
});

it('reports a server that fails and still moves the rest', function () {
    $failing = Server::factory()->create(['status' => ServerStatus::Active]);
    $healthy = Server::factory()->create(['status' => ServerStatus::Active]);
    $action = Mockery::mock(MovePhpToSuryRepository::class);
    $action->shouldReceive('execute')->once()->with(Mockery::on(fn ($server) => $server->id === $failing->id))
        ->andThrow(new RuntimeException('apt-get update failed'));
    $action->shouldReceive('execute')->once()->with(Mockery::on(fn ($server) => $server->id === $healthy->id));
    $this->app->instance(MovePhpToSuryRepository::class, $action);

    $this->artisan('flitops:php:move-to-sury', ['--all' => true])
        ->expectsOutputToContain('apt-get update failed')
        ->assertFailed();
});
