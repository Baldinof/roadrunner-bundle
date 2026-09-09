<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Grpc;

final class GrpcExceptionPolicy implements GrpcExceptionPolicyInterface
{
    /**
     * @param string[] $nonFatalExceptions Exception classes or interfaces that allow worker reuse
     */
    public function __construct(private array $nonFatalExceptions = [])
    {
    }

    public function shouldEscalate(\Throwable $exception): bool
    {
        foreach ($this->nonFatalExceptions as $exceptionClass) {
            if ($exception instanceof $exceptionClass) {
                return false;
            }
        }

        return true;
    }
}
