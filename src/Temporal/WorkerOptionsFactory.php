<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Temporal;

use Temporal\Worker\WorkerOptions;

final class WorkerOptionsFactory
{
    /**
     * @param array{
     *     max_concurrent_activity_execution_size: int<0, max>,
     *     worker_activities_per_second: float,
     *     max_concurrent_local_activity_execution_size: int<0, max>,
     *     worker_local_activities_per_second: float,
     *     task_queue_activities_per_second: float,
     *     max_concurrent_activity_task_pollers: int<0, max>,
     *     max_concurrent_workflow_task_execution_size: int<0, max>,
     *     max_concurrent_workflow_task_pollers: int<0, max>,
     *     sticky_schedule_to_start_timeout: int,
     *     worker_stop_timeout: int,
     *     enable_session_worker: bool,
     *     session_resource_id: string,
     *     max_concurrent_session_execution_size: int<0, max>,
     * } $options
     */
    public static function createFromArray(array $options): WorkerOptions
    {
        $workerOptions = (new WorkerOptions())
            ->withMaxConcurrentActivityExecutionSize($options['max_concurrent_activity_execution_size'])
            ->withWorkerActivitiesPerSecond($options['worker_activities_per_second'])
            ->withMaxConcurrentLocalActivityExecutionSize($options['max_concurrent_local_activity_execution_size'])
            ->withWorkerLocalActivitiesPerSecond($options['worker_local_activities_per_second'])
            ->withTaskQueueActivitiesPerSecond($options['task_queue_activities_per_second'])
            ->withMaxConcurrentActivityTaskPollers($options['max_concurrent_activity_task_pollers'])
            ->withMaxConcurrentWorkflowTaskExecutionSize($options['max_concurrent_workflow_task_execution_size'])
            ->withMaxConcurrentWorkflowTaskPollers($options['max_concurrent_workflow_task_pollers'])
            ->withWorkerStopTimeout($options['worker_stop_timeout'])
            ->withEnableSessionWorker($options['enable_session_worker'])
            ->withSessionResourceId($options['session_resource_id'])
            ->withMaxConcurrentSessionExecutionSize($options['max_concurrent_session_execution_size']);

        if ($options['sticky_schedule_to_start_timeout'] > 0) {
            $workerOptions = $workerOptions->withStickyScheduleToStartTimeout($options['sticky_schedule_to_start_timeout']);
        }

        if ($options['worker_stop_timeout'] > 0) {
            $workerOptions = $workerOptions->withWorkerStopTimeout($options['worker_stop_timeout']);
        }

        return $workerOptions;
    }
}
