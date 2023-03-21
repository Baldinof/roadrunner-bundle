<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\EventListener;

use Baldinof\RoadRunnerBundle\Event\WorkerStartEvent;
use Spiral\RoadRunner\Metrics\Collector;
use Spiral\RoadRunner\Metrics\CollectorInterface;
use Spiral\RoadRunner\Metrics\MetricsInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class DeclareMetricsListener implements EventSubscriberInterface
{
    private MetricsInterface $metrics;

    /**
     * @var array<string, CollectorInterface>
     */
    private array $collectors = [];

    public function __construct(MetricsInterface $metrics)
    {
        $this->metrics = $metrics;
    }

    /**
     * @param array{
     *   type: string,
     *   buckets?: float[],
     *   help?: string,
     *   namespace?: string,
     *   subsystem?: string,
     *   labels?: string[]
     * } $definition
     */
    public function addCollector(string $name, array $definition): void
    {
        $factories = [
            CollectorInterface::TYPE_HISTOGRAM => fn () => Collector::histogram(...$definition['buckets'] ?? []),
            CollectorInterface::TYPE_COUNTER => fn () => Collector::counter(),
            CollectorInterface::TYPE_GAUGE => fn () => Collector::gauge(),
        ];

        if (!isset($factories[$definition['type']])) {
            throw new \InvalidArgumentException(sprintf('Metric type should be "gauge", "counter" or "histogram". "%s" given', $definition['type']));
        }

        /** @var Collector $collector */
        $collector = ($factories[$definition['type']])();

        $help = $definition['help'] ?? null;
        $namespace = $definition['namespace'] ?? null;
        $subsystem = $definition['subsystem'] ?? null;
        $labels = $definition['labels'] ?? [];

        if ($help !== null) {
            $collector = $collector->withHelp((string) $help);
        }

        if ($namespace !== null) {
            $collector = $collector->withNamespace((string) $namespace);
        }

        if ($subsystem !== null) {
            $collector = $collector->withSubsystem((string) $subsystem);
        }

        if (\count($labels) > 0) {
            $collector = $collector->withLabels(...$labels);
        }

        $this->collectors[$name] = $collector;
    }

    public function declareMetrics(WorkerStartEvent $event): void
    {
        foreach ($this->collectors as $name => $collector) {
            $this->metrics->declare($name, $collector);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerStartEvent::class => 'declareMetrics',
        ];
    }
}
