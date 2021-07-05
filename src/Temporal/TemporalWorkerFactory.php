<?php
declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Temporal;

use Temporal\Worker\WorkerFactoryInterface;
use Temporal\Worker\WorkerInterface;
use Temporal\Worker\WorkerOptions;

final class TemporalWorkerFactory
{
    private WorkerFactoryInterface $workerFactory;

    public function __construct(WorkerFactoryInterface $workerFactory)
    {
        $this->workerFactory = $workerFactory;
    }

    public function create(string $queue = WorkerFactoryInterface::DEFAULT_TASK_QUEUE, WorkerOptions $workerOptions = null): WorkerInterface
    {
        return $this->workerFactory->newWorker(
            $queue,
            $workerOptions
        );
    }
}