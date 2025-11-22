<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Runtime;

use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Runtime\RunnerInterface;
use Symfony\Component\Runtime\SymfonyRuntime;

class Runtime extends SymfonyRuntime
{
    public function getRunner(?object $application): RunnerInterface
    {
        if ($application instanceof KernelInterface && false !== ($rrMode = getenv('RR_MODE'))) {
            return new Runner($application, $rrMode);
        }

        return parent::getRunner($application);
    }
}
