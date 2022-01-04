<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Integration\Sentry;

use Sentry\SentryBundle\EventListener\RequestListenerRequestEvent;
use Sentry\SentryBundle\EventListener\RequestListenerResponseEvent;
use Sentry\SentryBundle\EventListener\RequestListenerTerminateEvent;
use Sentry\SentryBundle\EventListener\TracingRequestListener;
use Sentry\State\HubInterface;

final class SentryTracingRequestListener
{
    private HubInterface $hub;

    private TracingRequestListener $innerListener;

    public function __construct(TracingRequestListener $innerListener, HubInterface $hub)
    {
        $this->innerListener = $innerListener;
        $this->hub = $hub;
    }

    public function handleKernelRequestEvent(RequestListenerRequestEvent $event): void
    {
        $this->innerListener->handleKernelRequestEvent($event);
    }

    public function handleKernelResponseEvent(RequestListenerResponseEvent $event): void
    {
        $this->innerListener->handleKernelResponseEvent($event);
        $transaction = $this->hub->getTransaction();

        if (null === $transaction) {
            return;
        }

        $transaction->finish();
    }

    public function handleKernelTerminateEvent(RequestListenerTerminateEvent $event): void
    {
       // do nothing
    }
}
