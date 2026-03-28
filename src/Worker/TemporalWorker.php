<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Worker;

use Baldinof\RoadRunnerBundle\Event\WorkerStartEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerStopEvent;
use Symfony\Component\HttpKernel\KernelInterface;
use Temporal\Exception\ExceptionInterceptorInterface;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Worker\WorkerInterface as TemporalWorkerInterface;
use Temporal\Worker\WorkerOptions;

final class TemporalWorker implements WorkerInterface
{
    /**
     * @var array<string, TemporalWorkerInterface>
     */
    private array $workers = [];

    public function __construct(
        private readonly KernelInterface $kernel,
        private readonly WorkerFactoryInterface $workerFactory,
    ) {
    }

    public function getWorkers(): array
    {
        return $this->workers;
    }

    public function addWorker(
        string $name,
        string $taskQueue,
        WorkerOptions $workerOptions,
        ExceptionInterceptorInterface $exceptionInterceptors,
        PipelineProvider $workerInterceptors,
    ): void {
        $this->workers[$name] = $this->workerFactory->newWorker(
            $taskQueue,
            $workerOptions,
            $exceptionInterceptors,
            $workerInterceptors
        );
    }

    /**
     * @param class-string $workflowClass
     */
    public function registerWorkflow(string $workflowClass, ?string $workerName = null): void
    {
        if (!is_null($workerName)) {
            if (\array_key_exists($workerName, $this->workers)) {
                $this->workers[$workerName]->registerWorkflowTypes($workflowClass);
                return;
            }
            throw new \InvalidArgumentException("Worker '$workerName' is not configured.");
        }

        foreach ($this->workers as $worker) {
            $worker->registerWorkflowTypes($workflowClass);
        }
    }

    public function registerActivity(string $class, ?string $workerName = null): void
    {
        $factory = fn () => $this->getDependencies()->getActivity($class);

        if (!is_null($workerName)) {
            if (\array_key_exists($workerName, $this->workers)) {
                $this->workers[$workerName]->registerActivity($class, $factory);
                return;
            }
            throw new \InvalidArgumentException("Worker '$workerName' is not configured.");
        }

        foreach ($this->workers as $worker) {
            $worker->registerActivity($class, $factory);
        }
    }

    public function start(): void
    {
        $this->getDependencies()->getEventDispatcher()->dispatch(new WorkerStartEvent());

        $this->workerFactory->run();

        $this->getDependencies()->getEventDispatcher()->dispatch(new WorkerStopEvent());
    }

    /**
     * @note Always get the dependencies from a fresh container in case
     *       an exception forced the kernel to reboot.
     */
    private function getDependencies(): TemporalDependencies
    {
        /** @var TemporalDependencies $deps */
        $deps = $this->kernel->getContainer()->get(TemporalDependencies::class);

        return $deps;
    }
}
