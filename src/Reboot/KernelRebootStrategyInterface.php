<?php

namespace Baldinof\RoadRunnerBundle\Reboot;

use Symfony\Component\HttpKernel\RebootableInterface;

interface KernelRebootStrategyInterface
{
    /**
     * Indicate if the kernel should be rebooted
     *
     * @return boolean
     */
    public function shouldReboot(): bool;

    /**
     * Clear any request related thing (caught exception, ...)
     *
     * @return void
     */
    public function clear(): void;
}
