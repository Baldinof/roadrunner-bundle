<?php

namespace Baldinof\RoadRunnerBundle\Http\Middleware;

use Baldinof\RoadRunnerBundle\Helpers\SentryRequestFetcher;
use Baldinof\RoadRunnerBundle\Http\IteratorMiddlewareInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sentry\State\HubInterface;

/**
 * This middleware is mostly a copy of the the Sentry RequestIntegration.
 * The Sentry class does not allow to pass arbitrary request and always do
 * integrations against PHP globals.
 */
final class SentryMiddleware implements IteratorMiddlewareInterface
{
    private SentryRequestFetcher $requestFetcher;
    private HubInterface $hub;

    public function __construct(SentryRequestFetcher $requestFetcher, HubInterface $hub)
    {
        $this->requestFetcher = $requestFetcher;
        $this->hub = $hub;
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): \Iterator
    {
        $this->requestFetcher->setRequest($request);
        $this->hub->pushScope();

        try {
            yield $next->handle($request);
        } finally {
            $client = $this->hub->getClient();
            if ($client !== null) {
                $client->flush()->wait(false);
            }
            $this->hub->popScope();
            $this->requestFetcher->clear();
        }
    }
}
