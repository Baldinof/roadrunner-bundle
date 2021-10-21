<?php
declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Worker;

use Baldinof\RoadRunnerBundle\Exception\UnsupportedRoadRunnerModeException;
use Spiral\RoadRunner\Environment\Mode;
use Spiral\RoadRunner\EnvironmentInterface;

final class WorkerResolver implements WorkerResolverInterface
{
    private EnvironmentInterface $environment;
    private HttpWorker $httpWorker;
    private TemporalWorker $temporalWorker;

    public function __construct(
        EnvironmentInterface $environment,
        HttpWorker $httpWorker,
        TemporalWorker $temporalWorker
    ) {
        $this->environment = $environment;
        $this->httpWorker = $httpWorker;
        $this->temporalWorker = $temporalWorker;
    }

    public function resolve(string $mode): WorkerInterface
    {
        if ($this->environment->getMode() === Mode::MODE_HTTP) {
            return $this->httpWorker;
        }
        if ($this->environment->getMode() === Mode::MODE_TEMPORAL) {
            return $this->temporalWorker;
        }

        throw new UnsupportedRoadRunnerModeException($mode);
    }
}