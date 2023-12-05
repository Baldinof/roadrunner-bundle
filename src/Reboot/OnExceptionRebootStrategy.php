<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Reboot;

use Baldinof\RoadRunnerBundle\Event\ForceKernelRebootEvent;
use Psr\Log\LoggerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class OnExceptionRebootStrategy implements KernelRebootStrategyInterface, EventSubscriberInterface
{
    private ?\Throwable $exceptionCaught = null;
    private ?ForceKernelRebootEvent $forceRebootEventCaught = null;
    private LoggerInterface $logger;

    /**
     * @var string[]
     */
    private array $allowedExceptions;

    /**
     * @param string[] $allowedExceptions
     */
    public function __construct(array $allowedExceptions, LoggerInterface $logger)
    {
        $this->allowedExceptions = $allowedExceptions;
        $this->logger = $logger;
    }

    public function onException(ExceptionEvent $event): void
    {
        if ($event->getRequestType() !== HttpKernelInterface::MAIN_REQUEST) {
            return;
        }

        $this->exceptionCaught = $event->getThrowable();
    }

    public function onForceKernelReboot(ForceKernelRebootEvent $event): void
    {
        $this->forceRebootEventCaught = $event;
    }

    public function shouldReboot(): bool
    {
        if ($this->forceRebootEventCaught !== null) {
            $this->logger->debug("The kernel has been forced to reboot: {$this->forceRebootEventCaught->getReason()}");

            return true;
        }

        if (null === $this->exceptionCaught) {
            return false;
        }

        foreach ($this->allowedExceptions as $exceptionClass) {
            if ($this->exceptionCaught instanceof $exceptionClass) {
                $this->logger->debug(sprintf(
                    'Allowed exception caught (%s), the kernel will not be rebooted.', \get_class($this->exceptionCaught)
                ), [
                    'allowed_exceptions' => $this->allowedExceptions,
                ]);

                return false;
            }
        }

        $this->logger->debug(sprintf(
            'Unexpected exception caught (%s), the kernel will be rebooted.', \get_class($this->exceptionCaught)
        ), [
            'allowed_exceptions' => $this->allowedExceptions,
            'exception' => $this->exceptionCaught,
        ]);

        return true;
    }

    public function clear(): void
    {
        $this->exceptionCaught = null;
        $this->forceRebootEventCaught = null;
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::EXCEPTION => 'onException',
            ForceKernelRebootEvent::class => 'onForceKernelReboot',
        ];
    }
}
