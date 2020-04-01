<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Metric;

use Spiral\Goridge\RPC;
use Spiral\RoadRunner\Metrics;
use Spiral\RoadRunner\MetricsInterface;

class MetricFactory
{
    /**
     * @var RPC
     */
    private $rpcService;

    /**
     * @var bool
     */
    private $metricsEnabled;

    public function __construct(RPC $rpcService, bool $metricsEnabled)
    {
        $this->rpcService = $rpcService;
        $this->metricsEnabled = $metricsEnabled;
    }

    public function getMetricService(): MetricsInterface
    {
        if ($this->metricsEnabled) {
            return new Metrics($this->rpcService);
        }

        return new NullMetrics();
    }
}
