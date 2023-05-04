<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class ForceKernelRebootEvent extends Event
{
    public function __construct(private string $reason)
    {
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
