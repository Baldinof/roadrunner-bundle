<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Grpc;

interface GrpcExceptionPolicyInterface
{
    /**
     * Whether to log the exception, dispatch WorkerExceptionEvent and stop the worker.
     *
     * Returning false preserves request finalization and the kernel reboot strategy.
     */
    public function shouldEscalate(\Throwable $exception): bool;
}
