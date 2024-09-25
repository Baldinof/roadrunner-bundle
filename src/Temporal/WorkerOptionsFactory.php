<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Temporal;

use Temporal\Worker\WorkerOptions;

final class WorkerOptionsFactory
{
    public static function createFromArray(array $options): WorkerOptions
    {
        $workerOptions = new WorkerOptions();

        if (\array_key_exists('max_concurrent_activity_execution_size', $options)) {
            /** @phpstan-ignore-next-line */
            $workerOptions = $workerOptions->withMaxConcurrentActivityExecutionSize((int) $options['max_concurrent_activity_execution_size']);
        }

        if (\array_key_exists('worker_activities_per_second', $options)) {
            $workerOptions = $workerOptions->withWorkerActivitiesPerSecond((float) $options['worker_activities_per_second']);
        }

        if (\array_key_exists('max_concurrent_local_activity_execution_size', $options)) {
            /** @phpstan-ignore-next-line */
            $workerOptions = $workerOptions->withMaxConcurrentLocalActivityExecutionSize((int) $options['max_concurrent_local_activity_execution_size']);
        }

        if (\array_key_exists('worker_local_activities_per_second', $options)) {
            $workerOptions = $workerOptions->withWorkerLocalActivitiesPerSecond((float) $options['worker_local_activities_per_second']);
        }

        if (\array_key_exists('task_queue_activities_per_second', $options)) {
            $workerOptions = $workerOptions->withTaskQueueActivitiesPerSecond((float) $options['task_queue_activities_per_second']);
        }

        if (\array_key_exists('max_concurrent_activity_task_pollers', $options)) {
            /** @phpstan-ignore-next-line */
            $workerOptions = $workerOptions->withMaxConcurrentActivityTaskPollers((int) $options['max_concurrent_activity_task_pollers']);
        }

        if (\array_key_exists('max_concurrent_workflow_task_execution_size', $options)) {
            /** @phpstan-ignore-next-line */
            $workerOptions = $workerOptions->withMaxConcurrentWorkflowTaskExecutionSize((int) $options['max_concurrent_workflow_task_execution_size']);
        }

        if (\array_key_exists('max_concurrent_workflow_task_pollers', $options)) {
            /** @phpstan-ignore-next-line */
            $workerOptions = $workerOptions->withMaxConcurrentActivityTaskPollers((int) $options['max_concurrent_workflow_task_pollers']);
        }

        if (\array_key_exists('sticky_schedule_to_start_timeout', $options)) {
            $workerOptions = $workerOptions->withStickyScheduleToStartTimeout((int) $options['sticky_schedule_to_start_timeout']);
        }

        if (\array_key_exists('worker_stop_timeout', $options)) {
            $workerOptions = $workerOptions->withWorkerStopTimeout((int) $options['worker_stop_timeout']);
        }

        if (\array_key_exists('enable_session_worker', $options)) {
            $workerOptions = $workerOptions->withEnableSessionWorker((bool) $options['enable_session_worker']);
        }

        if (\array_key_exists('session_resource_id', $options)) {
            $workerOptions = $workerOptions->withSessionResourceId($options['session_resource_id']);
        }

        if (\array_key_exists('max_concurrent_session_execution_size', $options)) {
            /** @phpstan-ignore-next-line */
            $workerOptions = $workerOptions->withMaxConcurrentSessionExecutionSize((int) $options['max_concurrent_session_execution_size']);
        }

        return $workerOptions;
    }
}
