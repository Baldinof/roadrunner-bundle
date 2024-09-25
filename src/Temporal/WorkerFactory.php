<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Temporal;

use Temporal\DataConverter\DataConverter;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\WorkerFactory as TemporalWorkerFactory;

final class WorkerFactory
{
    public function __construct(private readonly DataConverter $dataConverter)
    {
    }

    public function __invoke(): WorkerFactoryInterface
    {
        return TemporalWorkerFactory::create($this->dataConverter);
    }
}
