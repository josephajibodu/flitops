<?php

use App\Enums\AdminAction;
use App\Filament\Pages\SecurityAdvisoryServers;
use App\Models\AdminAuditLog;
use App\Models\Deployment;
use App\Models\Server;
use App\Models\ServerSecurityCheck;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use App\Notifications\SecurityAdvisoryPublished;
use App\Services\SecurityAdvisories\Nginx2RootAdvisory;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Livewire\Livewire;

uses(RefreshDatabase::class);

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    Notification::fake();

    $this->admin = User::factory()->platformAdmin()->create();
});

it('sends signed-out visitors to the normal login', function () {
    $this->get('/admin')->assertRedirect(route('login'));
});

it('refuses customers, whatever their team role', function () {
    $owner = User::factory()->withTwoFactor()->create();
    Team::factory()->forUser($owner)->create();

    $this->actingAs($owner)->get('/admin/servers')->assertForbidden();
});

it('tells staff without two-factor sign-in to turn it on', function () {
    $staff = User::factory()->create(['email' => 'staff@flitops.com']);
    $staff->forceFill(['is_admin' => true])->save();

    $this->actingAs($staff)
        ->get('/admin')
        ->assertForbidden()
        ->assertSeeText('Turn on two-factor sign-in in your security settings to use the admin panel.');
});

it('lets staff without two-factor sign-in in when running locally', function () {
    app()->detectEnvironment(fn (): string => 'local');
    $staff = User::factory()->create();
    $staff->forceFill(['is_admin' => true])->save();

    $this->actingAs($staff)->get('/admin')->assertOk();
});

it('opens every admin page for staff', function () {
    $team = Team::factory()->forUser(User::factory()->create())->create();
    $server = Server::factory()->forTeam($team)->active()->create(['web_server' => 'nginx']);
    $site = Site::factory()->forServer($server)->deployed()->create();
    $deployment = Deployment::factory()->forSite($site)->failed()->create();
    $deployment->logs()->create(['message' => 'composer install failed', 'created_at' => now()]);
    AdminAuditLog::factory()->create();

    $pages = [
        '/admin',
        '/admin/teams',
        "/admin/teams/{$team->slug}",
        '/admin/users',
        "/admin/users/{$team->user_id}",
        '/admin/servers',
        "/admin/servers/{$server->getRouteKey()}",
        '/admin/sites',
        "/admin/sites/{$site->getRouteKey()}",
        '/admin/deployments',
        "/admin/deployments/{$deployment->getRouteKey()}",
        '/admin/audit-log',
        '/admin/security-advisories',
        '/admin/security-advisories/'.Nginx2RootAdvisory::KEY,
    ];

    $this->actingAs($this->admin);

    foreach ($pages as $page) {
        $this->get($page)->assertOk();
    }

    $this->get("/admin/deployments/{$deployment->getRouteKey()}")->assertSeeText('composer install failed');
});

it('says so when an advisory does not exist', function () {
    $this->actingAs($this->admin)->get('/admin/security-advisories/no-such-advisory')->assertNotFound();
});

it('never shows a server\'s or a site\'s secrets', function () {
    $team = Team::factory()->forUser(User::factory()->create())->create();
    $server = Server::factory()->forTeam($team)->active()->create([
        'sudo_password' => 'sudo-secret-123',
        'database_password' => 'db-secret-456',
    ]);
    $site = Site::factory()->forServer($server)->create([
        'env_content' => 'APP_KEY=base64:env-secret-789',
        'webhook_secret' => 'webhook-secret-000',
    ]);

    $this->actingAs($this->admin);

    $this->get("/admin/servers/{$server->getRouteKey()}")
        ->assertOk()
        ->assertDontSee('sudo-secret-123')
        ->assertDontSee('db-secret-456');
    $this->get("/admin/sites/{$site->getRouteKey()}")
        ->assertOk()
        ->assertDontSee('env-secret-789')
        ->assertDontSee('webhook-secret-000');
});

describe('publishing a security advisory', function () {
    beforeEach(function () {
        $this->firstOwner = User::factory()->create();
        $this->firstTeam = Team::factory()->forUser($this->firstOwner)->create();
        $this->firstServer = Server::factory()->forTeam($this->firstTeam)->active()->create(['web_server' => 'nginx']);

        $this->secondOwner = User::factory()->create();
        $this->secondTeam = Team::factory()->forUser($this->secondOwner)->create();
        $this->secondServer = Server::factory()->forTeam($this->secondTeam)->active()->create(['web_server' => 'nginx']);

        $this->actingAs($this->admin);
    });

    it('reaches every affected server', function () {
        Livewire::test(SecurityAdvisoryServers::class, ['advisory' => Nginx2RootAdvisory::KEY])
            ->callAction('publish', data: ['target' => 'all'])
            ->assertNotified('Advisory published');

        expect(ServerSecurityCheck::pluck('server_id')->sort()->values()->all())->toBe([$this->firstServer->id, $this->secondServer->id]);
        Notification::assertSentTo([$this->firstOwner, $this->secondOwner], SecurityAdvisoryPublished::class);
    });

    it('reaches only the chosen teams', function () {
        Livewire::test(SecurityAdvisoryServers::class, ['advisory' => Nginx2RootAdvisory::KEY])
            ->callAction('publish', data: ['target' => 'teams', 'teams' => [$this->secondTeam->id]]);

        expect(ServerSecurityCheck::pluck('server_id')->all())->toBe([$this->secondServer->id]);
        Notification::assertSentTo($this->secondOwner, SecurityAdvisoryPublished::class);
        Notification::assertNotSentTo($this->firstOwner, SecurityAdvisoryPublished::class);
    });

    it('reaches only the chosen servers and skips ones it cannot affect', function () {
        $caddyServer = Server::factory()->forTeam($this->firstTeam)->active()->create(['web_server' => 'caddy']);

        Livewire::test(SecurityAdvisoryServers::class, ['advisory' => Nginx2RootAdvisory::KEY])
            ->callAction('publish', data: ['target' => 'servers', 'servers' => [$this->firstServer->id, $caddyServer->id]])
            ->assertNotified('Advisory published');

        expect(ServerSecurityCheck::pluck('server_id')->all())->toBe([$this->firstServer->id]);
        Notification::assertSentTo($this->firstOwner, SecurityAdvisoryPublished::class);
        Notification::assertNotSentTo($this->secondOwner, SecurityAdvisoryPublished::class);
    });

    it('needs at least one team or server when choosing', function (string $target) {
        Livewire::test(SecurityAdvisoryServers::class, ['advisory' => Nginx2RootAdvisory::KEY])
            ->callAction('publish', data: ['target' => $target, $target => []])
            ->assertHasFormErrors([$target => 'required']);

        expect(ServerSecurityCheck::exists())->toBeFalse();
    })->with(['teams', 'servers']);

    it('records who published what in the audit log', function () {
        Livewire::test(SecurityAdvisoryServers::class, ['advisory' => Nginx2RootAdvisory::KEY])
            ->callAction('publish', data: ['target' => 'teams', 'teams' => [$this->firstTeam->id]]);

        $entry = AdminAuditLog::sole();

        expect($entry->user_id)->toBe($this->admin->id)
            ->and($entry->action)->toBe(AdminAction::PublishedSecurityAdvisory)
            ->and($entry->properties)->toBe([
                'advisory' => Nginx2RootAdvisory::KEY,
                'target' => 'teams',
                'chosen teams' => [$this->firstTeam->id],
                'teams told' => 1,
                'servers listed' => 1,
            ]);
    });
});

describe('the flitops:admin command', function () {
    it('gives a staff member admin access', function () {
        $staff = User::factory()->create(['email' => 'staff@flitops.com']);

        $this->artisan('flitops:admin', ['email' => 'staff@flitops.com'])
            ->expectsOutputToContain('staff@flitops.com can now open the admin panel')
            ->expectsOutputToContain('They need to turn on two-factor sign-in')
            ->assertSuccessful();

        expect($staff->fresh()->is_admin)->toBeTrue();
    });

    it('takes admin access away', function () {
        $this->artisan('flitops:admin', ['email' => $this->admin->email, '--revoke' => true])->assertSuccessful();

        expect($this->admin->fresh()->is_admin)->toBeFalse();
    });

    it('fails for an email nobody has signed up with', function () {
        $this->artisan('flitops:admin', ['email' => 'nobody@flitops.com'])->assertFailed();
    });
});
