<?php

namespace Baldinof\RoadRunnerBundle\Http\Middleware;

use Baldinof\RoadRunnerBundle\Http\MiddlewareInterface;
use Sentry\State\HubInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * This middleware is mostly a copy of the the Sentry RequestIntegration.
 * The Sentry class does not allow to pass arbitrary request and always do
 * integrations against PHP globals.
 */
final class SentryMiddleware implements MiddlewareInterface
{
    private HubInterface $hub;

    public function __construct(HubInterface $hub)
    {
        $this->hub = $hub;
    }

    public function process(Request $request, HttpKernelInterface $next): \Iterator
    {
        $this->hub->pushScope();

        try {
            yield $next->handle($request);
        } finally {
            $client = $this->hub->getClient();
            if ($client !== null) {
                $client->flush()->wait(false);
            }
            $this->hub->popScope();
        }
    }
}
