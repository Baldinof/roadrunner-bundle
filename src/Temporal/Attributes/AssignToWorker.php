<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Temporal\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
class AssignToWorker
{
    public ?string $workerName = null;

    public function __construct(?string $workerName = null)
    {
        $this->workerName = $workerName;
    }
}
