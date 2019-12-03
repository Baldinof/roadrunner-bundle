<?php

namespace Baldinof\RoadRunnerBundle\Worker;

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
