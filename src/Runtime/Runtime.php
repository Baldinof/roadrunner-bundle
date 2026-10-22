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
        $mode = getenv('RR_MODE');
        if ($application instanceof KernelInterface && false !== $mode) {
            return new Runner($application, $mode);
        }

        return parent::getRunner($application);
    }
}
