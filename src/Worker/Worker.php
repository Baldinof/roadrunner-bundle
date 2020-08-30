<?php

namespace Baldinof\RoadRunnerBundle\Worker;

use function Baldinof\RoadRunnerBundle\consumes;
use Baldinof\RoadRunnerBundle\Event\WorkerExceptionEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerStartEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerStopEvent;
use Baldinof\RoadRunnerBundle\Http\IteratorRequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Spiral\RoadRunner\PSR7Client;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\RebootableInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

/**
 * @internal
 */
final class Worker implements WorkerInterface
{
    private $kernel;
    private $eventDispatcher;
    private $psrClient;
    private $logger;
    /**
     * @var Dependencies
     */
    private $dependencies;

    public function __construct(
        KernelInterface $kernel,
        EventDispatcherInterface $eventDispatcher,
        Configuration $configuration,
        IteratorRequestHandlerInterface $middlewareStack,
        LoggerInterface $logger,
        PSR7Client $psrClient,
        ?Dependencies $dependencies = null
    ) {
        $this->kernel = $kernel;
        $this->eventDispatcher = $eventDispatcher;
        $this->psrClient = $psrClient;
        $this->logger = $logger;

        /** @var Dependencies */
        $dependencies = $dependencies ?? $kernel->getContainer()->get(Dependencies::class);
        $this->dependencies = $dependencies;
    }

    public function start(): void
    {
        if ($trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? $_ENV['TRUSTED_PROXIES'] ?? false) {
            Request::setTrustedProxies(explode(',', $trustedProxies), Request::HEADER_X_FORWARDED_ALL ^ Request::HEADER_X_FORWARDED_HOST);
        }

        if ($trustedHosts = $_SERVER['TRUSTED_HOSTS'] ?? $_ENV['TRUSTED_HOSTS'] ?? false) {
            Request::setTrustedHosts(explode(',', $trustedHosts));
        }

        $this->eventDispatcher->dispatch(new WorkerStartEvent());

        while ($psrRequest = $this->psrClient->acceptRequest()) {
            $sent = false;
            try {
                $gen = $this->dependencies->getRequestHandler()->handle($psrRequest);

                $this->psrClient->respond($gen->current());

                $sent = true;

                consumes($gen);
            } catch (\Throwable $e) {
                if (!$sent) {
                    $this->psrClient->getWorker()->error($this->kernel->isDebug() ? (string) $e : 'Internal server error');
                }

                $this->logger->error('An error occured: '.$e->getMessage(), ['throwable' => $e]);

                $this->eventDispatcher->dispatch(new WorkerExceptionEvent($e));

                $this->psrClient->getWorker()->stop();
            } finally {
                if ($this->kernel instanceof RebootableInterface && $this->dependencies->getKernelRebootStrategy()->shouldReboot()) {
                    $this->kernel->reboot(null);
                    /** @var Dependencies */
                    $deps = $this->kernel->getContainer()->get(Dependencies::class);

                    $this->dependencies = $deps;
                }

                $this->dependencies->getKernelRebootStrategy()->clear();
            }
        }

        $this->eventDispatcher->dispatch(new WorkerStopEvent());
    }
}
