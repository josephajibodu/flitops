<?php

namespace App\Services\Providers\Concerns;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\RequestException;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * The HTTP client a cloud provider's API is called with.
 *
 * Only reads (GET) are tried again, and only when the failure may pass on
 * its own: the connection dropped, the provider was busy (429) or it had an
 * error of its own (5xx). A request that creates something, such as a
 * server or an SSH key, is never sent twice: the provider may have done it
 * even though no answer came back, and a second server would be billed.
 *
 * A failed answer is returned rather than thrown, so each provider can turn
 * it into a ProviderApiException with the provider's own message.
 */
trait BuildsProviderClient
{
    private function providerClient(string $baseUrl): PendingRequest
    {
        return Http::baseUrl($baseUrl)
            ->timeout(30)
            ->retry(
                3,
                100,
                fn (Throwable $exception, PendingRequest $request, ?string $method = null): bool => $method === 'GET'
                    && ($exception instanceof ConnectionException
                        || ($exception instanceof RequestException
                            && ($exception->response->serverError() || $exception->response->status() === 429))),
                throw: false,
            );
    }
}
