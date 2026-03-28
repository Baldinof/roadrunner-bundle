<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Integration\Doctrine;

use Baldinof\RoadRunnerBundle\Grpc\InterceptorInterface;
use Baldinof\RoadRunnerBundle\Http\MiddlewareInterface;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequest;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequestInvokerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class DoctrineORMMiddleware implements MiddlewareInterface, InterceptorInterface
{
    public function __construct(private readonly DoctrineORMIntegration $integration)
    {
    }

    public function process(Request $request, HttpKernelInterface $next): \Iterator
    {
        $this->integration->preRequest();

        yield $next->handle($request);

        $this->integration->postResponse();
    }

    public function intercept(GrpcRequest $invocation, GrpcRequestInvokerInterface $next): \Iterator
    {
        $this->integration->preRequest();

        yield $next->invoke($invocation);

        $this->integration->postResponse();
    }
}
