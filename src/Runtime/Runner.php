<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Runtime;

use Baldinof\RoadRunnerBundle\Worker\WorkerRegistryInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Runtime\RunnerInterface;

class Runner implements RunnerInterface
{
    private KernelInterface $kernel;
    private string $mode;

    public function __construct(KernelInterface $kernel, string $mode)
    {
        $this->kernel = $kernel;
        $this->mode = $mode;
    }

    public function run(): int
    {
        $this->kernel->boot();

        /** @var WorkerRegistryInterface $registry */
        $registry = $this->kernel->getContainer()->get(WorkerRegistryInterface::class);
        $worker = $registry->getWorker($this->mode);

        if (null === $worker) {
            error_log(sprintf('Missing RR worker implementation for %s mode', $this->mode));

            return 1;
        }

        $worker->start();

        return 0;
    }
}
