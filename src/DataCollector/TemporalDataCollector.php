<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\DataCollector;

use Baldinof\RoadRunnerBundle\Temporal\Interceptors\CollectingClientInterceptor;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

final class TemporalDataCollector extends DataCollector
{
    public function __construct(
        private readonly CollectingClientInterceptor $interceptor,
        private readonly iterable $workflows,
        private readonly iterable $activities,
        private readonly array $clients,
        private readonly array $workers,
    ) {
    }

    public function collect(Request $request, Response $response, ?\Throwable $exception = null): void
    {
        $this->data = [
            'interactions' => array_map($this->cloneVar(...), $this->interceptor->getInteractions()),
            'workflows' => $this->workflows,
            'activities' => $this->activities,
            'clients' => $this->clients,
            'workers' => $this->workers,
        ];
    }

    public function getName(): string
    {
        return 'temporal';
    }

    public function reset(): void
    {
        $this->data = [];
        $this->interceptor->reset();
    }

    public function getInteractions(): array
    {
        return $this->data['interactions'] ?? [];
    }

    public function getWorkflows(): array
    {
        return $this->data['workflows'] ?? [];
    }

    public function getActivities(): array
    {
        return $this->data['activities'] ?? [];
    }

    public function getClients(): array
    {
        return $this->data['clients'] ?? [];
    }

    public function getWorkers(): array
    {
        return $this->data['workers'] ?? [];
    }
}
