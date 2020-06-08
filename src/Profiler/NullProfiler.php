<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Profiler;

use Psr\Http\Message\ServerRequestInterface;

class NullProfiler implements ProfilerInterface
{
    public function start(ServerRequestInterface $request): void
    {
        // noop
    }

    public function finish(): void
    {
        // noop
    }
}
