<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Metric;

use Spiral\RoadRunner\Metrics;
use Spiral\RoadRunner\MetricsInterface;

class MetricFactory
{
    /**
     * @var NullMetrics
     */
    private $nullMetrics;

    /**
     * @var Metrics
     */
    private $metrics;

    /**
     * @var bool
     */
    private $metricsEnabled;

    public function __construct(NullMetrics $nullMetrics, Metrics $metrics, bool $metricsEnabled)
    {
        $this->nullMetrics = $nullMetrics;
        $this->metrics = $metrics;
        $this->metricsEnabled = $metricsEnabled;
    }

    public function getMetricService(): MetricsInterface
    {
        return $this->metricsEnabled ? $this->metrics : $this->nullMetrics;
    }
}
