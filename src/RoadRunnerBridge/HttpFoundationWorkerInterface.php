<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\RoadRunnerBridge;

use Spiral\RoadRunner\WorkerAwareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface HttpFoundationWorkerInterface extends WorkerAwareInterface
{
    public function waitRequest(): ?Request;

    public function respond(Response $response): void;
}
