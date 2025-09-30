<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Reboot;

class MemoryRebootStrategy implements KernelRebootStrategyInterface
{
    private int $memoryThresholdBytes;

    public function __construct(int $memoryThresholdMb)
    {
        if ($memoryThresholdMb <= 0) {
            throw new \InvalidArgumentException('Memory threshold must be greater than 0');
        }

        $this->memoryThresholdBytes = $memoryThresholdMb * 1024 * 1024;
    }

    public function shouldReboot(): bool
    {
        return memory_get_usage(true) >= $this->memoryThresholdBytes;
    }

    public function clear(): void
    {
        // No state to clear
    }
}
