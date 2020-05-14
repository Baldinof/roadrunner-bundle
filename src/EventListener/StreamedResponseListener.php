<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\EventListener;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\EventListener\StreamedResponseListener as SymfonyStreamedResponseListener;
use Symfony\Component\HttpKernel\KernelEvents;

/**
 * This listener decorates the default Symfony StreamedResponseListener and
 * disables it when running RoadRunner workers.
 *
 * This is to prevent the response frome being sent by the listener.
 */
final class StreamedResponseListener implements EventSubscriberInterface
{
    private $symfonyListener;
    private $rrEnabled;

    public function __construct(SymfonyStreamedResponseListener $symfonyListener, ?bool $rrEnabled)
    {
        $this->symfonyListener = $symfonyListener;
        $this->rrEnabled = $rrEnabled;
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$this->rrEnabled) {
            $this->symfonyListener->onKernelResponse($event);
        }
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::RESPONSE => ['onKernelResponse', -1024],
        ];
    }
}
