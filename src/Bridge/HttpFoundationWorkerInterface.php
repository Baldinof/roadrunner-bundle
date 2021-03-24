<?php

namespace Baldinof\RoadRunnerBundle\Bridge;

use Spiral\RoadRunner\WorkerAwareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

interface HttpFoundationWorkerInterface extends WorkerAwareInterface
{
    public function waitRequest(): ?Request;

    public function respond(Response $response): void;
}
