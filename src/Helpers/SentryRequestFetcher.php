<?php

namespace Baldinof\RoadRunnerBundle\Helpers;

use Psr\Http\Message\ServerRequestInterface;
use Sentry\Integration\RequestFetcherInterface;

final class SentryRequestFetcher implements RequestFetcherInterface
{
    // Static because Sentry keep an a RequestIntegration reference in a singleton IntegrationRegistry.
    // If the kernel is rebooted, the DIC will be recreated, but sentry will keep, the first configured
    // request fetcher
    private static ?ServerRequestInterface $request = null;

    public function setRequest(ServerRequestInterface $request): void
    {
        self::$request = $request;
    }

    public function clear(): void
    {
        self::$request = null;
    }

    public function fetchRequest(): ?ServerRequestInterface
    {
        return self::$request;
    }
}
