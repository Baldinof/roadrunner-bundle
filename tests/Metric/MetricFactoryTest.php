<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\Metric;

use Baldinof\RoadRunnerBundle\Exception\BadConfigurationException;
use Baldinof\RoadRunnerBundle\Exception\UnknownRpcTransportException;
use Baldinof\RoadRunnerBundle\Metric\MetricFactory;
use Baldinof\RoadRunnerBundle\Metric\NullMetrics;
use PHPUnit\Framework\TestCase;
use Spiral\RoadRunner\MetricsInterface;

class MetricFactoryTest extends TestCase
{
    public function test_is_metrics_enabled_but_run_without_rr(): void
    {
        $metricFactory = new MetricFactory(null, false, '', true);
        $this->assertInstanceOf(NullMetrics::class, $metricFactory->getMetricService());
    }

    public function test_is_metrics_enabled_but_rpc_not_configured(): void
    {
        $metricFactory = new MetricFactory(null, true, '', true);
        $this->expectException(BadConfigurationException::class);
        $metricFactory->getMetricService();
    }

    public function test_is_null_metrics_created_when_metrics_disabled()
    {
        $metricFactory = new MetricFactory(null, true, '', false);
        $this->assertInstanceOf(NullMetrics::class, $metricFactory->getMetricService());
    }

    /**
     * @dataProvider correctDsnProvider
     */
    public function test_correct_dsn(string $dsn)
    {
        $metricFactory = new MetricFactory($dsn, true, '', true);
        $this->assertInstanceOf(MetricsInterface::class, $metricFactory->getMetricService());
    }

    public function correctDsnProvider($dsn)
    {
        return [
            ['unix://var/socket.sock'],
            ['unix:///var/socket.sock'],
            ['tcp://:6000'],
            ['tcp://0.0.0.0:6000'],
            ['tcp://127.0.0.1:6000'],
        ];
    }

    /**
     * @dataProvider wrongDsnProvider
     */
    public function test_wrong_rpc_dsn(string $dsn)
    {
        $metricFactory = new MetricFactory($dsn, true, '', true);
        $this->expectException(UnknownRpcTransportException::class);
        $metricFactory->getMetricService();
    }

    public function wrongDsnProvider()
    {
        return [
            ['wrongdsn'],
            ['aaa://0.0.0.0:80'],
        ];
    }
}
