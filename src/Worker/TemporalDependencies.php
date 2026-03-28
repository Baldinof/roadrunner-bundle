<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Worker;

use Baldinof\RoadRunnerBundle\Reboot\KernelRebootStrategyInterface;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * A simple container class holding services needed by the Temporal Worker.
 *
 * It's used to ease worker dependencies retrieval when the kernel
 * has been rebooted.
 *
 * @internal
 */
final class TemporalDependencies
{
    public function __construct(
        private readonly KernelRebootStrategyInterface $kernelRebootStrategy,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly ContainerInterface $activities,
    ) {
    }

    public function getKernelRebootStrategy(): KernelRebootStrategyInterface
    {
        return $this->kernelRebootStrategy;
    }

    public function getEventDispatcher(): EventDispatcherInterface
    {
        return $this->eventDispatcher;
    }

    public function getActivity(string $class): object
    {
        /** @var object $activity */
        $activity = $this->activities->get($class);
        return $activity;
    }
}
