<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Reboot;

class ChainRebootStrategy implements KernelRebootStrategyInterface
{
    /**
     * @var iterable<KernelRebootStrategyInterface>
     */
    private iterable $strategies;

    /**
     * @param iterable<KernelRebootStrategyInterface> $strategies
     */
    public function __construct(iterable $strategies)
    {
        $this->strategies = $strategies;
    }

    public function shouldReboot(): bool
    {
        foreach ($this->strategies as $strategy) {
            if ($strategy->shouldReboot()) {
                return true;
            }
        }

        return false;
    }

    public function clear(): void
    {
        foreach ($this->strategies as $strategy) {
            $strategy->clear();
        }
    }
}
