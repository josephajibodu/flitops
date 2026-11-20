<?php

use App\Enums\SecurityCheckStatus;
use App\Enums\TeamRoleKey;
use App\Events\SecurityChecksUpdated;
use App\Exceptions\SshCommandException;
use App\Jobs\CheckServerSecurityJob;
use App\Jobs\FixServerSecurityJob;
use App\Models\Server;
use App\Models\ServerSecurityCheck;
use App\Models\Team;
use App\Models\User;
use App\Notifications\SecurityAdvisoryPublished;
use App\Services\SecurityAdvisories\Nginx2RootAdvisory;
use App\Services\Ssh\SshConnection;
use App\Services\Ssh\SshService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Inertia\Testing\AssertableInertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    Event::fake([SecurityChecksUpdated::class]);

    $this->owner = User::factory()->create();
    $this->team = Team::factory()->forUser($this->owner)->create();
    $this->owner->switchTeam($this->team);
    $this->server = Server::factory()->forTeam($this->team)->active()->create(['name' => 'web-1', 'web_server' => 'nginx']);
});

/**
 * Answers for the check's commands on a 24.04 server, keyed by a piece of each command.
 *
 * @return array<string, string>
 */
function nginxOnNoble(string $comparison, string $runningProgram = '/usr/sbin/nginx'): array
{
    return [
        'os-release' => "24.04\n",
        'dpkg-query' => '1.24.0-2ubuntu7.20',
        'compare-versions' => "{$comparison}\n",
        '/proc/' => $runningProgram,
    ];
}

function runSecurityCheck(ServerSecurityCheck $check): ServerSecurityCheck
{
    app()->call([new CheckServerSecurityJob($check), 'handle']);

    return $check->fresh();
}

it('lists every active nginx server and tells the people who manage them', function () {
    Notification::fake();
    $admin = User::factory()->memberOf($this->team, TeamRoleKey::Admin)->create();
    $developer = User::factory()->memberOf($this->team, TeamRoleKey::Developer)->create();
    $caddyServer = Server::factory()->forTeam($this->team)->active()->create(['web_server' => 'caddy']);
    $provisioningServer = Server::factory()->forTeam($this->team)->provisioning()->create(['web_server' => 'nginx']);

    $this->artisan('flitops:security:publish', ['advisory' => Nginx2RootAdvisory::KEY])->assertSuccessful();

    expect(ServerSecurityCheck::pluck('server_id')->all())->toBe([$this->server->id])
        ->and($this->server->securityChecks()->first()->status)->toBe(SecurityCheckStatus::Unchecked);
    Notification::assertSentTo([$this->owner, $admin], SecurityAdvisoryPublished::class);
    Notification::assertNotSentTo($developer, SecurityAdvisoryPublished::class);
    expect($caddyServer->securityChecks()->exists())->toBeFalse()
        ->and($provisioningServer->securityChecks()->exists())->toBeFalse();
});

it('does not tell a team twice when published again', function () {
    Notification::fake();

    $this->artisan('flitops:security:publish', ['advisory' => Nginx2RootAdvisory::KEY]);
    $this->artisan('flitops:security:publish', ['advisory' => Nginx2RootAdvisory::KEY]);

    Notification::assertSentToTimes($this->owner, SecurityAdvisoryPublished::class, 1);
    expect(ServerSecurityCheck::count())->toBe(1);
});

it('refuses an advisory that does not exist', function () {
    $this->artisan('flitops:security:publish', ['advisory' => 'nope'])->assertFailed();
});

it('shows the team only its own servers', function () {
    ServerSecurityCheck::factory()->for($this->server)->create();
    ServerSecurityCheck::factory()->create();

    $this->actingAs($this->owner)
        ->get(route('security-updates.index', $this->team))
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page) => $page
            ->component('security-updates/index')
            ->has('advisories', 1)
            ->has('advisories.0.servers', 1)
            ->where('advisories.0.servers.0.server.name', 'web-1')
            ->where('advisories.0.servers.0.status', 'unchecked'));
});

it('refreshes the page when a check or fix on the team\'s servers changes', function () {
    $response = $this->actingAs($this->owner)->get(route('security-updates.index', $this->team));

    expect(liveProps($response))->toBe([
        'advisories' => ["private:servers.{$this->team->id} security.checks.updated"],
    ]);
});

it('starts a check that only reads from the server', function () {
    Queue::fake();
    $check = ServerSecurityCheck::factory()->for($this->server)->create();

    $this->actingAs($this->owner)
        ->post(route('security-updates.check', [$this->team, $check]))
        ->assertRedirect();

    expect($check->fresh()->status)->toBe(SecurityCheckStatus::Checking);
    Queue::assertPushed(CheckServerSecurityJob::class);
});

it('hides another team\'s server', function (string $routeName) {
    Queue::fake();
    $otherCheck = ServerSecurityCheck::factory()->status(SecurityCheckStatus::NeedsFix)->create();

    $this->actingAs($this->owner)
        ->post(route($routeName, [$this->team, $otherCheck]))
        ->assertNotFound();

    Queue::assertNothingPushed();
})->with(['security-updates.check', 'security-updates.fix']);

it('does not start a second check while one is running', function () {
    Queue::fake();
    $check = ServerSecurityCheck::factory()->for($this->server)->status(SecurityCheckStatus::Fixing)->create();

    $this->actingAs($this->owner)
        ->post(route('security-updates.check', [$this->team, $check]))
        ->assertSessionHas('error');

    Queue::assertNothingPushed();
});

it('installs the fix when asked', function () {
    Queue::fake();
    $check = ServerSecurityCheck::factory()->for($this->server)->status(SecurityCheckStatus::NeedsFix)->create();

    $this->actingAs($this->owner)
        ->post(route('security-updates.fix', [$this->team, $check]))
        ->assertRedirect();

    expect($check->fresh())
        ->status->toBe(SecurityCheckStatus::Fixing)
        ->fixed_by->toBe($this->owner->id);
    Queue::assertPushed(FixServerSecurityJob::class);
});

it('refuses to fix a server nobody has checked yet', function () {
    Queue::fake();
    $check = ServerSecurityCheck::factory()->for($this->server)->create();

    $this->actingAs($this->owner)
        ->post(route('security-updates.fix', [$this->team, $check]))
        ->assertSessionHas('error');

    Queue::assertNothingPushed();
});

it('refuses fixes from members who cannot manage servers', function () {
    Queue::fake();
    $developer = User::factory()->memberOf($this->team, TeamRoleKey::Developer)->create();
    $check = ServerSecurityCheck::factory()->for($this->server)->status(SecurityCheckStatus::NeedsFix)->create();

    $this->actingAs($developer)
        ->post(route('security-updates.fix', [$this->team, $check]))
        ->assertForbidden();

    Queue::assertNothingPushed();
});

it('finds what the server needs', function (array $answers, SecurityCheckStatus $expected) {
    $commands = [];
    $uploads = [];
    fakeHostSsh($commands, $uploads, $answers);
    $check = ServerSecurityCheck::factory()->for($this->server)->create();

    expect(runSecurityCheck($check)->status)->toBe($expected);
})->with([
    'older nginx' => [nginxOnNoble('vulnerable'), SecurityCheckStatus::NeedsFix],
    'fixed nginx, old copy still running' => [nginxOnNoble('patched', '/usr/sbin/nginx (deleted)'), SecurityCheckStatus::NeedsRestart],
    'fixed nginx running' => [nginxOnNoble('patched'), SecurityCheckStatus::Covered],
    'ubuntu 20.04' => [['os-release' => "20.04\n"], SecurityCheckStatus::Unsupported],
]);

it('compares against the fixed package for the server\'s Ubuntu release', function () {
    $commands = [];
    $uploads = [];
    fakeHostSsh($commands, $uploads, ['os-release' => "22.04\n", 'dpkg-query' => '1.18.0-6ubuntu14.22', 'compare-versions' => 'vulnerable']);

    runSecurityCheck(ServerSecurityCheck::factory()->for($this->server)->create());

    expect(implode("\n", $commands))->toContain("dpkg --compare-versions '1.18.0-6ubuntu14.22' ge '1.18.0-6ubuntu14.23'");
});

it('records a check that could not reach the server', function () {
    $sshService = Mockery::mock(SshService::class);
    $sshService->shouldReceive('connect')->andThrow(new RuntimeException('Connection timed out'));
    app()->instance(SshService::class, $sshService);

    $check = runSecurityCheck(ServerSecurityCheck::factory()->for($this->server)->create());

    expect($check->status)->toBe(SecurityCheckStatus::CheckFailed)
        ->and($check->detail)->toContain('Connection timed out');
});

it('clears the banner once every affected server in the team is protected', function () {
    $otherServer = Server::factory()->forTeam($this->team)->active()->create();
    $check = ServerSecurityCheck::factory()->for($this->server)->create();
    $otherCheck = ServerSecurityCheck::factory()->for($otherServer)->create();
    $this->owner->notify(new SecurityAdvisoryPublished($this->team, app(Nginx2RootAdvisory::class), 2));
    $commands = [];
    $uploads = [];
    fakeHostSsh($commands, $uploads, nginxOnNoble('patched'));

    runSecurityCheck($check);
    expect($this->owner->unreadNotifications()->count())->toBe(1);

    runSecurityCheck($otherCheck);
    expect($this->owner->unreadNotifications()->count())->toBe(0);
});

it('upgrades nginx, tests its configuration and restarts it', function () {
    $commands = [];
    $uploads = [];
    fakeHostSsh($commands, $uploads, nginxOnNoble('patched'));
    $check = ServerSecurityCheck::factory()->for($this->server)->status(SecurityCheckStatus::Fixing)->create();

    app()->call([new FixServerSecurityJob($check), 'handle']);

    $ran = implode("\n", $commands);
    expect($check->fresh())
        ->status->toBe(SecurityCheckStatus::Covered)
        ->fixed_at->not->toBeNull()
        ->output->toContain('systemctl restart nginx')
        ->and(strpos($ran, 'install --only-upgrade -y nginx'))->toBeLessThan(strpos($ran, 'nginx -t'))
        ->and(strpos($ran, 'nginx -t'))->toBeLessThan(strpos($ran, 'systemctl restart nginx'));
});

it('leaves nginx running when the new version rejects the configuration', function () {
    $commands = [];
    $connection = Mockery::mock(SshConnection::class)->makePartial();
    $connection->shouldReceive('exec')->andReturnUsing(function (string $command) use (&$commands) {
        $commands[] = $command;

        if (str_contains($command, 'nginx -t')) {
            throw new SshCommandException($command, 1, 'nginx: [emerg] unknown directive "brotli"');
        }

        return str_contains($command, 'os-release') ? "24.04\n" : '';
    });
    $connection->shouldReceive('disconnect');
    $sshService = Mockery::mock(SshService::class);
    $sshService->shouldReceive('connect')->andReturn($connection);
    app()->instance(SshService::class, $sshService);
    $check = ServerSecurityCheck::factory()->for($this->server)->status(SecurityCheckStatus::Fixing)->create();

    app()->call([new FixServerSecurityJob($check), 'handle']);

    expect($check->fresh())
        ->status->toBe(SecurityCheckStatus::FixFailed)
        ->detail->toContain('nginx was not restarted')
        ->detail->toContain('unknown directive "brotli"')
        ->and(implode("\n", $commands))->not->toContain('systemctl restart nginx');
});
