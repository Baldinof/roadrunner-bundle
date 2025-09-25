<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\Reboot;

use Baldinof\RoadRunnerBundle\Reboot\MemoryRebootStrategy;
use PHPUnit\Framework\TestCase;

class MemoryRebootStrategyTest extends TestCase
{
    public function test_it_does_not_reboot_when_memory_is_below_threshold()
    {
        // Create strategy with very high threshold so current memory usage is below
        $strategy = new MemoryRebootStrategy(1000); // 1000MB

        $this->assertFalse($strategy->shouldReboot());
    }

    public function test_it_reboots_when_memory_exceeds_threshold()
    {
        // Create strategy with very low threshold to trigger reboot
        $strategy = new MemoryRebootStrategy(1); // 1MB - very low

        $this->assertTrue($strategy->shouldReboot());
    }

    public function test_it_returns_consistent_results()
    {
        $strategy = new MemoryRebootStrategy(1000); // High threshold

        // Multiple calls should return consistent results
        $result1 = $strategy->shouldReboot();
        $result2 = $strategy->shouldReboot();

        $this->assertSame($result1, $result2);
        $this->assertFalse($result1); // Current memory usage should be below 1000MB
    }

    public function test_it_throws_exception_for_zero_or_negative_threshold()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Memory threshold must be greater than 0');

        new MemoryRebootStrategy(0);
    }

    public function test_it_throws_exception_for_negative_threshold()
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Memory threshold must be greater than 0');

        new MemoryRebootStrategy(-100);
    }

    public function test_clear_does_nothing()
    {
        $strategy = new MemoryRebootStrategy(1000);

        // clear() should not throw exception and strategy should continue working
        $strategy->clear();

        $this->assertFalse($strategy->shouldReboot());
    }

    public function test_multiple_instances_work_independently()
    {
        $strategy1 = new MemoryRebootStrategy(1000); // High threshold
        $strategy2 = new MemoryRebootStrategy(1);   // Low threshold

        // They should behave independently based on the same memory usage
        $this->assertFalse($strategy1->shouldReboot());
        $this->assertTrue($strategy2->shouldReboot());
    }
}