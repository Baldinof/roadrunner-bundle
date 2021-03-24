<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Worker;

interface WorkerInterface
{
    public function start(): void;
}
