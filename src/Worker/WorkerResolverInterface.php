<?php
declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Worker;

interface WorkerResolverInterface
{
    public function resolve(string $mode): WorkerInterface;
}