<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Worker;

use Temporal\Exception\ExceptionInterceptorInterface;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Worker\WorkerInterface as TemporalWorkerInterface;
use Temporal\Worker\WorkerOptions;

final class TemporalWorker implements WorkerInterface
{
    private WorkerFactoryInterface $workerFactory;

    /**
     * @var array<string, TemporalWorkerInterface>
     */
    private array $workers = [];

    public function __construct(
        WorkerFactoryInterface $workerFactory,
    ) {
        $this->workerFactory = $workerFactory;
    }

    public function addWorker(
        string $name,
        string $queue,
        SimplePipelineProvider $workerInterceptors,
        ExceptionInterceptorInterface $exceptionInterceptors,
        WorkerOptions $workerOptions,
    ): void {
        /** @phpstan-ignore-next-line */
        $this->workers[$name] = $this->workerFactory->newWorker(
            $queue,
            $workerOptions,
            $exceptionInterceptors,
            $workerInterceptors
        );
    }

    /**
     * @param class-string $workflowClass
     * @param ?string $workerName
     * @return void
     */
    public function registerWorkflow(string $workflowClass, ?string $workerName = null): void
    {
        if (\array_key_exists((string) $workerName, $this->workers)) {
            $this->workers[$workerName]->registerWorkflowTypes($workflowClass);

            return;
        }

        foreach ($this->workers as $name => $worker) {
            if ($name === $workerName) {
                continue;
            }
            $worker->registerWorkflowTypes($workflowClass);
        }
    }

    public function registerActivity(object $activity, ?string $workerName = null): void
    {
        if (\array_key_exists((string) $workerName, $this->workers)) {
            $this->workers[$workerName]->registerActivity($activity::class, fn() => $activity);

            return;
        }

        foreach ($this->workers as $name => $worker) {
            if ($name === $workerName) {
                continue;
            }
            $worker->registerActivity($$activity::class, fn() => $activity);
        }
    }

    public function start(): void
    {
        $this->workerFactory->run();
    }
}
