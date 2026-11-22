<?php

use App\Actions\Database\CreateServerDatabase;
use App\Enums\DatabaseStatus;
use App\Enums\ServerStatus;
use App\Events\ServerDatabasesUpdated;
use App\Jobs\CreateServerDatabaseJob;
use App\Models\Server;
use App\Models\ServerCredential;
use App\Models\ServerDatabase;
use App\Services\Ssh\SshService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;

uses(RefreshDatabase::class);

it('creates database on server and sets status to ready', function () {
    Event::fake([ServerDatabasesUpdated::class]);

    $server = Server::factory()->create([
        'database_type' => 'mysql',
        'status' => ServerStatus::Active,
    ]);
    ServerCredential::create([
        'server_id' => $server->id,
        'type' => 'database_password',
        'value' => 'rootsecret',
    ]);

    $serverDatabase = ServerDatabase::factory()->create([
        'server_id' => $server->id,
        'name' => 'testdb',
        'status' => DatabaseStatus::Pending,
    ]);

    $mockConnection = mockDatabaseConnection();

    $sshService = Mockery::mock(SshService::class);
    $sshService->shouldReceive('connect')
        ->once()
        ->with(Mockery::on(fn ($s) => $s->id === $server->id))
        ->andReturn($mockConnection);

    $this->app->instance(SshService::class, $sshService);

    $job = new CreateServerDatabaseJob($serverDatabase);
    $job->handle(app(CreateServerDatabase::class));

    $serverDatabase->refresh();
    expect($serverDatabase->status)->toBe(DatabaseStatus::Ready);

    Event::assertDispatched(ServerDatabasesUpdated::class, fn ($e) => $e->server->id === $server->id);
});

it('sets status to failed when server has no database password credential', function () {
    Event::fake([ServerDatabasesUpdated::class]);

    $server = Server::factory()->create([
        'database_type' => 'mysql',
        'status' => ServerStatus::Active,
    ]);
    // No database_password credential

    $serverDatabase = ServerDatabase::factory()->create([
        'server_id' => $server->id,
        'name' => 'testdb',
        'status' => DatabaseStatus::Pending,
    ]);

    $job = new CreateServerDatabaseJob($serverDatabase);
    try {
        $job->handle(app(CreateServerDatabase::class));
    } catch (RuntimeException) {
        // Job sets status to failed then rethrows
    }

    $serverDatabase->refresh();
    expect($serverDatabase->status)->toBe(DatabaseStatus::Failed);

    Event::assertDispatched(ServerDatabasesUpdated::class);
});
