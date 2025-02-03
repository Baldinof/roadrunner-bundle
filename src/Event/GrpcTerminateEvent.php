<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Event;

use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequest;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @see \Symfony\Component\HttpKernel\Event\TerminateEvent
 */
final class GrpcTerminateEvent extends Event
{
    public function __construct(
        private GrpcRequest $request,
        private string $response,
    ) {
    }

    public function getRequest(): GrpcRequest
    {
        return $this->request;
    }

    public function getResponse(): string
    {
        return $this->response;
    }
}
