<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Integration\Sentry;

use Sentry\SentryBundle\EventListener\TracingRequestListener;
use Sentry\State\HubInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\Event\TerminateEvent;

final class SentryTracingRequestListenerDecorator
{
    public function __construct(
        private TracingRequestListener $innerListener,
        private HubInterface $hub
    ) {
    }

    public function handleKernelRequestEvent(RequestEvent $event): void
    {
        $this->innerListener->handleKernelRequestEvent($event);
    }

    public function handleKernelResponseEvent(ResponseEvent $event): void
    {
        $this->innerListener->handleKernelResponseEvent($event);

        $this->hub->getTransaction()?->finish();
    }

    public function handleKernelTerminateEvent(TerminateEvent $event): void
    {
        // do nothing
    }
}
