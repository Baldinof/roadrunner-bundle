<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Profiler;

use Psr\Http\Message\ServerRequestInterface;

interface ProfilerInterface
{
    public function start(ServerRequestInterface $request): void;

    public function finish(): void;
}
