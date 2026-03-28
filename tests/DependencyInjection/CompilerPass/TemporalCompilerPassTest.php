<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass;

use Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass\TemporalCompilerPass;
use Baldinof\RoadRunnerBundle\Reboot\KernelRebootStrategyInterface;
use Baldinof\RoadRunnerBundle\Worker\TemporalWorker;
use Baldinof\RoadRunnerBundle\Worker\WorkerRegistryInterface;
use Baldinof\RoadRunnerBundle\Worker\WorkerRegistry;
use Baldinof\RoadRunnerBundle\Worker\TemporalDependencies;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\Exception\ExceptionInterceptor;
use Temporal\WorkerFactory as TemporalWorkerFactory;


class TemporalCompilerPassTest extends TestCase
{
    private function defaultOptions(): array
    {
        return [
            'max_concurrent_activity_execution_size' => 0,
            'worker_activities_per_second' => 0,
            'max_concurrent_local_activity_execution_size' => 0,
            'worker_local_activities_per_second' => 0,
            'task_queue_activities_per_second' => 0,
            'max_concurrent_activity_task_pollers' => 0,
            'max_concurrent_workflow_task_execution_size' => 0,
            'max_concurrent_workflow_task_pollers' => 0,
            'sticky_schedule_to_start_timeout' => 0,
            'worker_stop_timeout' => 0,
            'enable_session_worker' => false,
            'session_resource_id' => null,
            'max_concurrent_session_execution_size' => 1000,
        ];
    }

    protected function setUp(): void
    {
        $this->container = new ContainerBuilder();
        $this->container->setParameter('temporal.config', [
            'workers' => [
                'default' => [
                    'queue' => 'default',
                    'exception_interceptor' => 'temporal.exception_interceptor',
                    'default_interceptors' => false,
                    'interceptors' => [],
                    'options' => $this->defaultOptions(),
                ],
            ],
        ]);

        $this->container->setParameter('temporal.default_interceptors', []);

        $this->container->register(TemporalWorker::class, TemporalWorker::class);
        $this->container->register(TemporalWorkerFactory::class, TemporalWorkerFactory::class);
        $this->container->register(DataConverter::class, DataConverter::class);
        $this->container->register(WorkerRegistryInterface::class, WorkerRegistry::class);
        $this->container->register('temporal.exception_interceptor', ExceptionInterceptor::class);
        $this->container->register(KernelRebootStrategyInterface::class);
        $this->container->register(EventDispatcherInterface::class);

    }

    public function test_workflow_is_registered_on_worker(): void
    {
        $this->container->register(DummyWorkflow::class, DummyWorkflow::class)
            ->addTag('temporal.workflow', ['worker_name' => 'default']);

        (new TemporalCompilerPass())->process($this->container);

        $calls = $this->container->getDefinition(TemporalWorker::class)->getMethodCalls();
        $this->assertContains(
            ['registerWorkflow', [DummyWorkflow::class, 'default']],
            $calls
        );
    }

    public function test_activity_is_registered_on_worker(): void
    {
        $this->container->register(DummyActivity::class, DummyActivity::class)
            ->addTag('temporal.activity', ['worker_name' => 'default']);

        (new TemporalCompilerPass())->process($this->container);

        $calls = $this->container->getDefinition(TemporalWorker::class)->getMethodCalls();

        $this->assertContains(
            ['registerActivity', [DummyActivity::class, 'default']],
            $calls
        );
    }
    public function test_duplicate_queue_throws(): void
    {
        $this->container->setParameter('temporal.config', [
            'workers' => [
                'default' => [
                    'queue' => 'same-queue',
                    'exception_interceptor' => 'temporal.exception_interceptor',
                    'default_interceptors' => false,
                    'interceptors' => [],
                    'options' => $this->defaultOptions(),
                ],
                'other' => [
                    'queue' => 'same-queue',
                    'exception_interceptor' => 'temporal.exception_interceptor',
                    'default_interceptors' => false,
                    'interceptors' => [],
                    'options' => $this->defaultOptions(),
                ],
            ],
        ]);

        $this->expectException(\InvalidArgumentException::class);
        (new TemporalCompilerPass())->process($this->container);
    }

    public function test_workflow_without_worker_name_passes_null(): void
    {
        $this->container->register(DummyWorkflow::class, DummyWorkflow::class)
            ->addTag('temporal.workflow', ['worker_name' => null]);

        (new TemporalCompilerPass())->process($this->container);

        $calls = $this->container->getDefinition(TemporalWorker::class)->getMethodCalls();

        $this->assertContains(
            ['registerWorkflow', [DummyWorkflow::class, null]],
            $calls
        );
    }

    public function test_activity_ends_up_in_service_locator(): void
    {
        $this->container->register(DummyActivity::class, DummyActivity::class)
            ->addTag('temporal.activity', ['worker_name' => null]);

        (new TemporalCompilerPass())->process($this->container);

        $deps = $this->container->getDefinition(TemporalDependencies::class);
        $locatorArg = $deps->getArgument(2);

        $this->assertInstanceOf(Reference::class, $locatorArg);
    }
}

#[\Temporal\Workflow\WorkflowInterface]
class DummyWorkflow {}

#[\Temporal\Activity\ActivityInterface]
class DummyActivity {}