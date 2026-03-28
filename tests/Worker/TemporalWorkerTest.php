<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\Worker;

use Baldinof\RoadRunnerBundle\Worker\TemporalWorker;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Prophecy\Prophecy\ObjectProphecy;
use Symfony\Component\HttpKernel\KernelInterface;
use Temporal\Exception\ExceptionInterceptorInterface;
use Temporal\Interceptor\PipelineProvider;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Worker\WorkerInterface as TemporalWorkerInterface;
use Temporal\Worker\WorkerOptions;

#[\AllowDynamicProperties]
class TemporalWorkerTest extends TestCase
{
    use ProphecyTrait;

    private TemporalWorker $worker;

    /**
     * @var ObjectProphecy<WorkerFactoryInterface>
     */
    private ObjectProphecy $sdkWorkerFactory;

    public function setUp(): void
    {
        $this->sdkWorkerFactory = $this->prophesize(WorkerFactoryInterface::class);

        $kernel = $this->prophesize(KernelInterface::class);

        $this->worker = new TemporalWorker(
            $kernel->reveal(),
            $this->sdkWorkerFactory->reveal(),
        );
    }

    /**
     * @return ObjectProphecy<TemporalWorkerInterface>
     */
    private function addWorker(string $name = 'default'): ObjectProphecy
    {
        $worker = $this->prophesize(TemporalWorkerInterface::class);
        $worker->registerWorkflowTypes(Argument::cetera())->willReturn($worker->reveal());
        $worker->registerActivity(Argument::cetera())->willReturn($worker->reveal());

        $this->sdkWorkerFactory
            ->newWorker('default', Argument::cetera())
            ->willReturn($worker->reveal());

        $this->worker->addWorker(
            $name, 'default',
            new WorkerOptions(),
            $this->prophesize(ExceptionInterceptorInterface::class)->reveal(),
            $this->prophesize(PipelineProvider::class)->reveal(),
        );

        return $worker;
    }

    public function test_registerWorkflow_throws_on_unknown_worker(): void
    {
        self::expectException(\InvalidArgumentException::class);
        $this->worker->registerWorkflow(DummyWorkflow::class, 'default');
    }

    public function test_registerWorkflow_succeeds_on_default(): void
    {
        $sdkWorker = $this->addWorker();
        $this->worker->registerWorkflow(DummyWorkflow::class, 'default');

        $sdkWorker
            ->registerWorkflowTypes(DummyWorkflow::class)
            ->shouldHaveBeenCalled();
    }

    public function test_registerWorkflow_succeeds_on_default_without_specifying(): void
    {
        $sdkWorker = $this->addWorker();
        $this->worker->registerWorkflow(DummyWorkflow::class);

        $sdkWorker
            ->registerWorkflowTypes(DummyWorkflow::class)
            ->shouldHaveBeenCalled();
    }

    public function test_registerWorkflow_throws_on_unknown_worker_with_default_present(): void
    {
        self::expectException(\InvalidArgumentException::class);
        $this->addWorker();
        $this->worker->registerWorkflow(DummyWorkflow::class, 'other-worker');
    }

    public function test_registerWorkflow_adds_to_all_workers(): void
    {
        $sdkWorkerA = $this->addWorker('A');
        $sdkWorkerB = $this->addWorker('B');

        $this->worker->registerWorkflow(DummyWorkflow::class);

        $sdkWorkerA
            ->registerWorkflowTypes(DummyWorkflow::class)
            ->shouldHaveBeenCalled();
        $sdkWorkerB
            ->registerWorkflowTypes(DummyWorkflow::class)
            ->shouldHaveBeenCalled();
    }

    public function test_registerActivity_throws_on_unknown_worker(): void
    {
        self::expectException(\InvalidArgumentException::class);
        $this->worker->registerActivity(DummyActivity::class, 'default');
    }

    public function test_registerActivity_succeeds_on_default(): void
    {
        $sdkWorker = $this->addWorker();
        $this->worker->registerActivity(DummyActivity::class, 'default');

        $sdkWorker
            ->registerActivity(DummyActivity::class, Argument::any())
            ->shouldHaveBeenCalled();
    }

    public function test_registerActivity_succeeds_on_default_without_specifying(): void
    {
        $sdkWorker = $this->addWorker();
        $this->worker->registerActivity(DummyActivity::class);

        $sdkWorker
            ->registerActivity(DummyActivity::class, Argument::any())
            ->shouldHaveBeenCalled();
    }

    public function test_registerActivity_throws_on_unknown_worker_with_default_present(): void
    {
        self::expectException(\InvalidArgumentException::class);
        $this->addWorker();
        $this->worker->registerActivity(DummyActivity::class, 'other-worker');
    }

    public function test_registerActivity_adds_to_all_workers(): void
    {
        $sdkWorkerA = $this->addWorker('A');
        $sdkWorkerB = $this->addWorker('B');

        $this->worker->registerActivity(DummyActivity::class);

        $sdkWorkerA
            ->registerActivity(DummyActivity::class, Argument::any())
            ->shouldHaveBeenCalled();
        $sdkWorkerB
            ->registerActivity(DummyActivity::class, Argument::any())
            ->shouldHaveBeenCalled();
    }

}
#[\Temporal\Workflow\WorkflowInterface]
class DummyWorkflow {}

#[\Temporal\Activity\ActivityInterface]
class DummyActivity {}