<?php

namespace Baldinof\RoadRunnerBundle\Worker;

use Baldinof\RoadRunnerBundle\Http\IteratorRequestHandlerInterface;
use Baldinof\RoadRunnerBundle\Reboot\KernelRebootStrategyInterface;

/**
 * A simple container class holding services needed by the Worker.
 *
 * It's used to ease worker dependencies retrieval when the kernel
 * has been rebooted.
 *
 * @internal
 */
final class Dependencies
{
    private $requestHandler;
    private $kernelRebootStrategy;

    public function __construct(
        IteratorRequestHandlerInterface $requestHandler,
        KernelRebootStrategyInterface $kernelRebootStrategy
    ) {
        $this->requestHandler = $requestHandler;
        $this->kernelRebootStrategy = $kernelRebootStrategy;
    }

    public function getRequestHandler(): IteratorRequestHandlerInterface
    {
        return $this->requestHandler;
    }

    public function getKernelRebootStrategy(): KernelRebootStrategyInterface
    {
        return $this->kernelRebootStrategy;
    }
}
