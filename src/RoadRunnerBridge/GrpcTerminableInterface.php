<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\RoadRunnerBridge;

/**
 * @see \Symfony\Component\HttpKernel\TerminableInterface
 */
interface GrpcTerminableInterface
{
    /**
     * Terminates a request/response cycle.
     *
     * Should be called after sending the response and before shutting down the kernel.
     */
    public function terminate(GrpcRequest $request, string $response): void;
}
