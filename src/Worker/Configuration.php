<?php

namespace Baldinof\RoadRunnerBundle\Worker;

/**
 * @deprecated Since 1.3.0
 */
final class Configuration
{
    private $shouldRebootKernel;

    public function __construct(bool $shouldRebootKernel = false)
    {
        $this->shouldRebootKernel = $shouldRebootKernel;
    }

    public function shouldRebootKernel(): bool
    {
        return $this->shouldRebootKernel;
    }
}
