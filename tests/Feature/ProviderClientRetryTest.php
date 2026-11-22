<?php

use App\Contracts\ProviderContract;
use App\Exceptions\ProviderApiException;
use App\Services\Providers\DigitalOceanProvider;
use App\Services\Providers\HetznerProvider;
use App\Services\Providers\VultrProvider;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;

dataset('providers', [
    'DigitalOcean' => [DigitalOceanProvider::class, 'api.digitalocean.com/v2/account/keys'],
    'Hetzner' => [HetznerProvider::class, 'api.hetzner.cloud/v1/ssh_keys'],
    'Vultr' => [VultrProvider::class, 'api.vultr.com/v2/ssh-keys'],
]);

function providerWithToken(string $providerClass): ProviderContract
{
    $provider = new $providerClass;
    $provider->setCredentials(['api_token' => 'test-token']);

    return $provider;
}

it('sends a request that creates something only once, even when the provider fails', function (string $providerClass, string $sshKeysUrl) {
    Http::preventStrayRequests();
    Sleep::fake();
    Http::fake([$sshKeysUrl => Http::response(['message' => 'Service unavailable'], 503)]);

    expect(fn () => providerWithToken($providerClass)->createSshKey('flitops-web-1', 'ssh-ed25519 AAAA', 'nyc1'))
        ->toThrow(ProviderApiException::class, 'Service unavailable');

    Http::assertSentCount(1);
})->with('providers');

it('tries a read again when the provider has a passing error', function (string $providerClass) {
    Http::preventStrayRequests();
    Sleep::fake();
    Http::fakeSequence()
        ->push(['message' => 'Busy'], 503)
        ->push(['account' => [], 'servers' => []], 200);

    expect(providerWithToken($providerClass)->validateCredentials())->toBeTrue();

    Http::assertSentCount(2);
})->with('providers');

it('does not try a read again when the provider refuses the API key', function (string $providerClass) {
    Http::preventStrayRequests();
    Sleep::fake();
    Http::fake(fn (Request $request) => Http::response(['message' => 'Unauthorized'], 401));

    expect(providerWithToken($providerClass)->validateCredentials())->toBeFalse();

    Http::assertSentCount(1);
})->with('providers');
