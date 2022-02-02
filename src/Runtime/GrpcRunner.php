<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Runtime;

use Baldinof\RoadRunnerBundle\Worker\GrpcWorkerInterface;
use Spiral\RoadRunner\GRPC\ServiceInterface;
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
        if (!interface_exists(ServiceInterface::class)) {
            error_log('Missing dependency, run `composer require spiral/roadrunner-grpc`');

            return 1;
        }

        $this->kernel->boot();

        /** @var GrpcWorkerInterface */
        $worker = $this->kernel->getContainer()->get(GrpcWorkerInterface::class);

        $worker->start();

        return 0;
    }
}
