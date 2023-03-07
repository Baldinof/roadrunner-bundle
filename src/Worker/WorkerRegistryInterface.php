<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Worker;

interface WorkerRegistryInterface
{
    public function registerWorker(string $mode, WorkerInterface $worker): void;

    public function getWorker(string $mode): ?WorkerInterface;
}
