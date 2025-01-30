<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Grpc;

use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequest;
use Spiral\RoadRunner\GRPC\InvokerInterface;

/**
 * @internal
 */
final class InvocationHandler implements RequestHandlerInterface
{
    public function __construct(
        private readonly InvokerInterface $invoker,
    ) {
    }

    public function handle(GrpcRequest $request): \Iterator
    {
        yield $this->invoker->invoke($request->getService(), $request->getMethod(), $request->getContext(), $request->getInput());
    }
}
