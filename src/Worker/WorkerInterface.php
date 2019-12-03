<?php

namespace Baldinof\RoadRunnerBundle\Worker;

interface WorkerInterface
{
    public function start(): void;
}
