<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Runtime;

use Spiral\RoadRunner\Environment\Mode;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Runtime\RunnerInterface;
use Symfony\Component\Runtime\SymfonyRuntime;

class GrpcRuntime extends SymfonyRuntime
{
    public function getRunner(?object $application): RunnerInterface
    {
        if ($application instanceof KernelInterface && \getenv('RR_MODE') === Mode::MODE_GRPC) {
            return new GrpcRunner($application);
        }

        return parent::getRunner($application);
    }
}
