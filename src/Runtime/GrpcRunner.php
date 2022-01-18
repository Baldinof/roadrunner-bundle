<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Runtime;

use Baldinof\RoadRunnerBundle\Worker\GrpcWorkerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Runtime\RunnerInterface;

class GrpcRunner implements RunnerInterface
{
    private KernelInterface $kernel;

    public function __construct(KernelInterface $kernel)
    {
        $this->kernel = $kernel;
    }

    public function run(): int
    {
        $this->kernel->boot();

        /** @var GrpcWorkerInterface */
        $worker = $this->kernel->getContainer()->get(GrpcWorkerInterface::class);

        $worker->start();

        return 0;
    }
}
