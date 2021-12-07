<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Worker;

interface GrpcWorkerInterface
{
    public function start(): void;
}
