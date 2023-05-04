<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class WorkerExceptionEvent extends Event
{
    public function __construct(private \Throwable $exception)
    {
    }

    public function getException(): \Throwable
    {
        return $this->exception;
    }
}
