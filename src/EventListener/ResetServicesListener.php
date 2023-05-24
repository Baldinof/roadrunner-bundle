<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\EventListener;

use Baldinof\RoadRunnerBundle\Event\WorkerRequestHandledEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerStopEvent;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\DependencyInjection\ServicesResetter;

final class ResetServicesListener implements EventSubscriberInterface
{
    public function __construct(private ServicesResetter $servicesResetter)
    {
    }

    public function resetServices(): void
    {
        $this->servicesResetter->reset();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            WorkerRequestHandledEvent::class => ['resetServices', -1024],
            WorkerStopEvent::class => ['resetServices', -1024],
        ];
    }
}
