<?php

use App\Models\Deployment;
use App\Models\Server;
use App\Models\Site;
use App\Models\Team;
use App\Models\User;
use App\Realtime\RealtimeDiagnosticsChannelAuthorizer;
use Illuminate\Support\Facades\Broadcast;
use Illuminate\Support\Facades\Log;

Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Each channel asks the same policy as the page it feeds, so whoever can
// see a team, server, site or deployment can hear its live updates.

// Server list channel for a team (named by the team's numeric id, not its slug)
Broadcast::channel('servers.{teamId}', function (User $user, string $teamId) {
    $team = Team::find($teamId);

    return $team !== null && $user->can('view', $team);
});

Broadcast::channel('server.{server}', function (User $user, Server $server) {
    return $user->can('view', $server);
});

// Deployment logs follow the deployment's site
Broadcast::channel('deployments.{deployment}', function (User $user, Deployment $deployment) {
    $authorized = $user->can('view', $deployment->site);

    Log::debug('Broadcast channel authorization', [
        'channel' => 'deployments',
        'deployment_id' => $deployment->id,
        'user_id' => $user->id,
        'authorized' => $authorized,
    ]);

    return $authorized;
});

Broadcast::channel('site.{site}', function (User $user, Site $site) {
    return $user->can('view', $site);
});

Broadcast::channel('realtime.diagnostics.{userId}', function ($user, $userId) {
    $authorized = app(RealtimeDiagnosticsChannelAuthorizer::class)
        ->canAccess($user, (int) $userId);

    Log::debug('Broadcast channel authorization', [
        'channel' => 'realtime.diagnostics',
        'requested_user_id' => (int) $userId,
        'user_id' => $user->id,
        'authorized' => $authorized,
    ]);

    return $authorized;
});
