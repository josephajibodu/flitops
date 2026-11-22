<?php

use App\Models\Deployment;
use App\Models\Server;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Broadcast;

uses(RefreshDatabase::class);

beforeEach(function () {
    config([
        'broadcasting.default' => 'reverb',
        'broadcasting.connections.reverb.key' => 'test-key',
        'broadcasting.connections.reverb.secret' => 'test-secret',
        'broadcasting.connections.reverb.app_id' => 'test-app',
    ]);
    Broadcast::purge('reverb');
    require base_path('routes/channels.php');

    $this->team = Team::factory()->forUser(User::factory()->create())->create();
    $this->server = Server::factory()->forTeam($this->team)->create();
    $this->site = Site::factory()->forServer($this->server)->create();
    $this->deployment = Deployment::factory()->for($this->site)->create();
});

function channelsFor(object $test): array
{
    return [
        "servers.{$test->team->id}",
        "server.{$test->server->id}",
        "site.{$test->site->id}",
        "deployments.{$test->deployment->id}",
    ];
}

it('lets a team member listen to their team\'s channels', function () {
    $member = User::factory()->viewerOf($this->team)->create();

    foreach (channelsFor($this) as $channel) {
        $this->actingAs($member)
            ->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-{$channel}"])
            ->assertOk();
    }
});

it('refuses someone outside the team', function () {
    $outsider = User::factory()->create();

    foreach (channelsFor($this) as $channel) {
        $this->actingAs($outsider)
            ->post('/broadcasting/auth', ['socket_id' => '1234.5678', 'channel_name' => "private-{$channel}"])
            ->assertForbidden();
    }
});
