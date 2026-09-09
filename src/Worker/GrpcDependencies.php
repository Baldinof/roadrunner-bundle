<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Worker;

use Baldinof\RoadRunnerBundle\Grpc\GrpcExceptionPolicyInterface;
use Baldinof\RoadRunnerBundle\Grpc\InterceptorStack;
use Baldinof\RoadRunnerBundle\Reboot\KernelRebootStrategyInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * A simple container class holding services needed by the Grpc Invoker.
 *
 * It's used to ease worker dependencies retrieval when the kernel
 * has been rebooted.
 *
 * @internal
 */
final class GrpcDependencies
{
    public function __construct(
        private InterceptorStack $requestHandler,
        private KernelRebootStrategyInterface $kernelRebootStrategy,
        private EventDispatcherInterface $eventDispatcher,
        private GrpcExceptionPolicyInterface $exceptionPolicy,
    ) {
    }

    public function getRequestHandler(): InterceptorStack
    {
        return $this->requestHandler;
    }

    public function getKernelRebootStrategy(): KernelRebootStrategyInterface
    {
        return $this->kernelRebootStrategy;
    }

    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    public function getExceptionPolicy(): GrpcExceptionPolicyInterface
    {
        return $this->exceptionPolicy;
    }
}
