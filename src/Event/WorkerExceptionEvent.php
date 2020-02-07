<?php

namespace Baldinof\RoadRunnerBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class WorkerExceptionEvent extends Event
{
    private $exception;

    public function __construct(\Throwable $exception)
    {
        $this->exception = $exception;
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }
}
