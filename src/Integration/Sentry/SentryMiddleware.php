<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Integration\Sentry;

use Baldinof\RoadRunnerBundle\Grpc\InterceptorInterface;
use Baldinof\RoadRunnerBundle\Http\MiddlewareInterface;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequest;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequestInvokerInterface;
use GuzzleHttp\Promise\PromiseInterface;
use Sentry\State\HubInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Clear scope and flush transport after each request.
 */
final class SentryMiddleware implements MiddlewareInterface, InterceptorInterface
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
            $result = $this->hub->getClient()?->flush();
            if (class_exists(PromiseInterface::class) && $result instanceof PromiseInterface) {
                $result->wait(false);
            }

            $this->hub->popScope();
        }
    }

    public function intercept(GrpcRequest $invocation, GrpcRequestInvokerInterface $next): \Iterator
    {
        $this->hub->pushScope();

        try {
            yield $next->invoke($invocation);
        } finally {
            $result = $this->hub->getClient()?->flush();
            if (class_exists(PromiseInterface::class) && $result instanceof PromiseInterface) {
                $result->wait(false);
            }

            $this->hub->popScope();
        }
    }
}
