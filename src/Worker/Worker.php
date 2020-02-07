<?php

namespace Baldinof\RoadRunnerBundle\Worker;

use function Baldinof\RoadRunnerBundle\consumes;
use Baldinof\RoadRunnerBundle\Event\WorkerExceptionEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerStartEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerStopEvent;
use Baldinof\RoadRunnerBundle\Http\IteratorRequestHandlerInterface;
use Baldinof\RoadRunnerBundle\Http\MiddlewareStack;
use Psr\Log\LoggerInterface;
use Spiral\RoadRunner\PSR7Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\RebootableInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class Worker implements WorkerInterface
{
    private $kernel;
    private $eventDispatcher;
    private $configuration;
    private $middlewareStack;
    private $psrClient;
    private $logger;

    public function __construct(
        KernelInterface $kernel,
        EventDispatcherInterface $eventDispatcher,
        Configuration $configuration,
        IteratorRequestHandlerInterface $middlewareStack,
        LoggerInterface $logger,
        PSR7Client $psrClient
    ) {
        $this->kernel = $kernel;
        $this->eventDispatcher = $eventDispatcher;
        $this->configuration = $configuration;
        $this->middlewareStack = $middlewareStack;
        $this->psrClient = $psrClient;
        $this->logger = $logger;

        $withReboot = $this->configuration->shouldRebootKernel();

        if ($withReboot && !$this->kernel instanceof RebootableInterface) {
            throw new \InvalidArgumentException("The worker is configured to reboot the kernel, but the passed kernel does not implement Symfony\Component\HttpKernel\RebootableInterface");
        }
    }

    public function start(): void
    {
        $withReboot = $this->configuration->shouldRebootKernel();

        if ($trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? $_ENV['TRUSTED_PROXIES'] ?? false) {
            Request::setTrustedProxies(explode(',', $trustedProxies), Request::HEADER_X_FORWARDED_ALL ^ Request::HEADER_X_FORWARDED_HOST);
        }

        if ($trustedHosts = $_SERVER['TRUSTED_HOSTS'] ?? $_ENV['TRUSTED_HOSTS'] ?? false) {
            Request::setTrustedHosts(explode(',', $trustedHosts));
        }

        $this->eventDispatcher->dispatch(new WorkerStartEvent());

        $middlewareStack = $this->middlewareStack;
        while ($psrRequest = $this->psrClient->acceptRequest()) {
            try {
                $gen = $middlewareStack->handle($psrRequest);

                $this->psrClient->respond($gen->current());

                consumes($gen);

                if ($withReboot) {
                    $this->kernel->reboot(null);
                    /** @var MiddlewareStack $middlewareStack */
                    $middlewareStack = $this->kernel->getContainer()->get(MiddlewareStack::class);
                }
            } catch (\Throwable $e) {
                $this->psrClient->getWorker()->error($this->kernel->isDebug() ? (string) $e : 'Internal server error');
                $this->logger->error('An error occured: '.$e->getMessage(), ['throwable' => $e]);

                $this->eventDispatcher->dispatch(new WorkerExceptionEvent($e));

                break;
            }
        }

        $this->eventDispatcher->dispatch(new WorkerStopEvent());
    }
}
