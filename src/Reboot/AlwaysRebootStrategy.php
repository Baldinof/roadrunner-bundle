<?php

namespace Baldinof\RoadRunnerBundle\Reboot;

class AlwaysRebootStrategy implements KernelRebootStrategyInterface
{
    public function shouldReboot(): bool
    {
        return true;
    }

    public function clear(): void
    {
    }
}
