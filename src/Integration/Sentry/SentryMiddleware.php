<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Integration\Sentry;

use Baldinof\RoadRunnerBundle\Http\MiddlewareInterface;
use Sentry\State\HubInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Clear scope and flush transport after each request.
 */
final class SentryMiddleware implements MiddlewareInterface
{
    public function __construct(private HubInterface $hub)
    {
    }

    public function process(Request $request, HttpKernelInterface $next): \Iterator
    {
        $this->hub->pushScope();

        try {
            yield $next->handle($request);
        } finally {
            $client = $this->hub->getClient();
            $client?->flush()->wait(false);

            $this->hub->popScope();
        }
    }
}
