<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Grpc;

use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequest;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcTerminableInterface;
use Spiral\RoadRunner\GRPC\InvokerInterface;

/**
 * @internal
 */
final class InvocationHandler implements GrpcRequestHandlerInterface
{
    public function __construct(
        private readonly InvokerInterface $invoker,
    ) {
    }

    public function handle(GrpcRequest $request): \Iterator
    {
        $response = $this->invoker->invoke($request->getService(), $request->getMethod(), $request->getContext(), $request->getInput());

        yield $response;

        if ($this->invoker instanceof GrpcTerminableInterface) {
            $this->invoker->terminate($request, $response);
        }
    }
}
