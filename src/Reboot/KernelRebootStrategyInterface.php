<?php

namespace Baldinof\RoadRunnerBundle\Reboot;

interface KernelRebootStrategyInterface
{
    /**
     * Indicate if the kernel should be rebooted.
     */
    public function shouldReboot(): bool;

    /**
     * Clear any request related thing (caught exception, ...).
     */
    public function clear(): void;
}
