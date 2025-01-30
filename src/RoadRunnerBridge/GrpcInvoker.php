<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\RoadRunnerBridge;

use Baldinof\RoadRunnerBundle\Grpc\GrpcInvocation;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\InvokerInterface;
use Spiral\RoadRunner\GRPC\Method;
use Spiral\RoadRunner\GRPC\ServiceInterface;

final class GrpcInvoker implements GrpcInvokerInterface
{
    public function __construct(
        private InvokerInterface $invoker,
    ) {
    }

    public function onServerStart(): void
    {
        // TODO: Implement onServerStart() method.
    }

    public function invoke(ServiceInterface $service, Method $method, ContextInterface $ctx, ?string $input): string
    {
        try {
            $invocation = new GrpcInvocation($service, $method, $ctx, $input);

            // TODO: call middlewares

            $response = $this->invoker->invoke(
                $invocation->getService(),
                $invocation->getMethod(),
                $invocation->getContext(),
                $invocation->getInput()
            );

            // TODO: other stuff?

            return $response;
        } catch (\Throwable $e) {
            // TODO: deal with immediate errors
        } finally {
            // TODO: finalize things?
        }
    }

    public function invokeFinalize(?\Throwable $e = null): void
    {
        // TODO: finalize things?
    }

    public function onServerStop(): void
    {
        // TODO: Implement onServerStop() method.
    }
}
