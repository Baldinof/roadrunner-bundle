<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Runtime;

use Baldinof\RoadRunnerBundle\Worker\WorkerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Runtime\RunnerInterface;

class Runner implements RunnerInterface
{
    private KernelInterface $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    public function run(): int
    {
        $this->kernel->boot();

        /** @var WorkerInterface */
        $worker = $this->kernel->getContainer()->get(WorkerInterface::class);

        $worker->start();

        return 0;
    }
}
