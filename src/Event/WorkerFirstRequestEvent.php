<?php

namespace Baldinof\RoadRunnerBundle\Event;

use Baldinof\RoadRunnerBundle\EventListener\DeclareMetricsListener;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * @internal Used only to declare metrics on first request with {@link DeclareMetricsListener}
 *           Will be removed when https://github.com/spiral/roadrunner/issues/571 will be closed
 */
final class WorkerFirstRequestEvent extends Event
{
}
