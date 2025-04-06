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
        private KernelInterface $kernel,
        private WorkerFactoryInterface $workerFactory,
    ) {
    }

    public function addWorker(
        string $name,
        string $queue,
        PipelineProvider $workerInterceptors,
        ExceptionInterceptorInterface $exceptionInterceptors,
        WorkerOptions $workerOptions,
    ): void {
        /* @phpstan-ignore-next-line */
        $this->workers[$name] = $this->workerFactory->newWorker(
            $queue,
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
        if (\array_key_exists((string) $workerName, $this->workers)) {
            $this->workers[$workerName]->registerWorkflowTypes($workflowClass);

            return;
        }

        foreach ($this->workers as $worker) {
            $worker->registerWorkflowTypes($workflowClass);
        }
    }

    public function registerActivity(string $class, ?string $workerName = null): void
    {
        $factory = fn () => $this->getDependencies()->getActivity($class);

        if (\array_key_exists((string) $workerName, $this->workers)) {
            $this->workers[$workerName]->registerActivity($class, $factory);

            return;
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
