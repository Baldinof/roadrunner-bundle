<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\EventListener;

use Baldinof\RoadRunnerBundle\Event\WorkerStartEvent;
use Spiral\RoadRunner\Metrics\Collector;
use Spiral\RoadRunner\Metrics\CollectorInterface;
use Spiral\RoadRunner\Metrics\CollectorType;
use Spiral\RoadRunner\Metrics\MetricsInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

final class DeclareMetricsListener implements EventSubscriberInterface
{
    private MetricsInterface $metrics;

    /**
     * @var array<non-empty-string, CollectorInterface>
     */
    private array $collectors = [];

    public function __construct(MetricsInterface $metrics)
    {
        $this->metrics = $metrics;
    }

    /**
     * @param non-empty-string $name
     * @param array{
     *   type: string,
     *   buckets?: float[],
     *   help?: string,
     *   namespace?: string,
     *   subsystem?: string,
     *   labels?: non-empty-string[]
     * } $definition
     */
    public function addCollector(string $name, array $definition): void
    {
        /** @var Collector $collector */
        $collector = match ($definition['type'] ?? null) {
            CollectorType::Histogram->value => Collector::histogram(...$definition['buckets'] ?? []),
            CollectorType::Counter->value => Collector::counter(),
            CollectorType::Gauge->value => Collector::gauge(),
            default => throw new \InvalidArgumentException(sprintf('Metric type should be "gauge", "counter" or "histogram". "%s" given', $definition['type'])),
        };

        $help = $definition['help'] ?? '';
        $namespace = $definition['namespace'] ?? '';
        $subsystem = $definition['subsystem'] ?? '';
        $labels = $definition['labels'] ?? [];

        if ($help !== '') {
            $collector = $collector->withHelp((string) $help);
        }

        if ($namespace !== '') {
            $collector = $collector->withNamespace((string) $namespace);
        }

        if ($subsystem !== '') {
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
