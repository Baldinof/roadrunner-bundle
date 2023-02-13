<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Worker;

/**
 * @interal
 */
final class WorkerRegistry implements WorkerRegistryInterface
{
    private array $workers = [];

    public function registerWorker(string $mode, WorkerInterface $worker): void
    {
        $this->workers[$mode] = $worker;
    }

    public function getWorker(string $mode): ?WorkerInterface
    {
        return $this->workers[$mode] ?? null;
    }
}
