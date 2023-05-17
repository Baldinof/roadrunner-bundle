<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Worker;

use Baldinof\RoadRunnerBundle\Http\MiddlewareStack;
use Baldinof\RoadRunnerBundle\Reboot\KernelRebootStrategyInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * A simple container class holding services needed by the Http Worker.
 *
 * It's used to ease worker dependencies retrieval when the kernel
 * has been rebooted.
 *
 * @internal
 */
final class HttpDependencies
{
    public function __construct(
        private MiddlewareStack $requestHandler,
        private KernelRebootStrategyInterface $kernelRebootStrategy,
        private EventDispatcherInterface $eventDispatcher
    ) {
    }

    public function getRequestHandler(): MiddlewareStack
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
}
