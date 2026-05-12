<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\RoadRunnerBridge\HttpFoundationWorker;

use Spiral\RoadRunner\Http\HttpWorkerInterface;
use Symfony\Component\HttpFoundation\Response;

interface HttpFoundationResponder
{
    public function supports(Response $response): bool;

    public function respond(HttpWorkerInterface $httpWorker, Response $response): void;
}
