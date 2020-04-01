<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Metric;

use Spiral\RoadRunner\MetricsInterface;

/**
 * this service used, when metric collection disabled.
 */
final class NullMetrics implements MetricsInterface
{
    public function add(string $collector, float $value, array $labels = [])
    {
        return;
    }

    public function sub(string $collector, float $value, array $labels = [])
    {
        return;
    }

    public function observe(string $collector, float $value, array $labels = [])
    {
        return;
    }

    public function set(string $collector, float $value, array $labels = [])
    {
        return;
    }
}
