<?php

namespace Baldinof\RoadRunnerBundle\Event;

use Symfony\Contracts\EventDispatcher\Event;

final class ForceKernelRebootEvent extends Event
{
    private $reason;

    public function __construct(string $reason)
    {
        $this->reason = $reason;
    }

    public function getReason(): string
    {
        return $this->reason;
    }
}
