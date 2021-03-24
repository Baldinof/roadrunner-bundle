<?php

namespace Tests\Baldinof\RoadRunnerBundle\EventListener;

use Baldinof\RoadRunnerBundle\Event\WorkerStartEvent;
use Baldinof\RoadRunnerBundle\EventListener\DeclareMetricsListener;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Spiral\RoadRunner\Metrics\Collector;
use Spiral\RoadRunner\Metrics\MetricsInterface;

class DeclareMetricsListenerTest extends TestCase
{
    use ProphecyTrait;

    public function test_declare_metrics()
    {
        $metrics = $this->prophesize(MetricsInterface::class);
        $gauge = Collector::gauge()->withLabels('hello');
        $counter = Collector::counter()->withNamespace('foo')->withHelp('count something');
        $histogram = Collector::histogram(0.1, 0.5, 1)->withSubsystem('bar');

        $listener = new DeclareMetricsListener($metrics->reveal());
        $listener->addCollector('gauge', [
            'type' => 'gauge',
            'labels' => ['hello'],
        ]);
        $listener->addCollector('counter', [
            'type' => 'counter',
            'namespace' => 'foo',
            'help' => 'count something',
        ]);
        $listener->addCollector('histo', [
            'type' => 'histogram',
            'buckets' => [0.1, 0.5, 1],
            'subsystem' => 'bar',
        ]);

        $metrics->declare('gauge', $gauge)->shouldBeCalled();
        $metrics->declare('counter', $counter)->shouldBeCalled();
        $metrics->declare('histo', $histogram)->shouldBeCalled();

        $listener->declareMetrics(new WorkerStartEvent());
    }
}
