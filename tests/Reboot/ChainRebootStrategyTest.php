<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\Reboot;

use Baldinof\RoadRunnerBundle\Reboot\ChainRebootStrategy;
use Baldinof\RoadRunnerBundle\Reboot\KernelRebootStrategyInterface;
use PHPUnit\Framework\TestCase;

class ChainRebootStrategyTest extends TestCase
{
    public function test_it_does_not_reboot_by_default()
    {
        $strategy = new ChainRebootStrategy([]);
        $this->assertFalse($strategy->shouldReboot());
    }

    public function test_it_reboot_if_one_strategy_reboot()
    {
        $strategy = new ChainRebootStrategy([
            $this->createStrategy(true),
            $this->createStrategy(false),
        ]);

        $this->assertTrue($strategy->shouldReboot());

        $strategy = new ChainRebootStrategy([
            $this->createStrategy(false),
            $this->createStrategy(true),
        ]);
    }

    public function test_it_does_not_reboot_if_no_strategy_reboot()
    {
        $strategy = new ChainRebootStrategy([
            $this->createStrategy(false),
            $this->createStrategy(false),
        ]);
        $this->assertFalse($strategy->shouldReboot());
    }

    private function createStrategy(bool $shouldReboot): KernelRebootStrategyInterface
    {
        return new class($shouldReboot) implements KernelRebootStrategyInterface {
            private bool $shouldReboot;

            public function __construct(bool $shouldReboot)
            {
                $this->shouldReboot = $shouldReboot;
            }

            public function shouldReboot(): bool
            {
                return $this->shouldReboot;
            }

            public function clear(): void
            {
            }
        };
    }
}
