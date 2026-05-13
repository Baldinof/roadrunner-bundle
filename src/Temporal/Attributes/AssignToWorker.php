<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Temporal\Attributes;

#[\Attribute(\Attribute::TARGET_CLASS)]
final class AssignToWorker
{
    public function __construct(public readonly string $workerName)
    {
    }
}
