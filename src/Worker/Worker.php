<?php

namespace Baldinof\RoadRunnerBundle\Worker;

use function Baldinof\RoadRunnerBundle\consumes;
use Baldinof\RoadRunnerBundle\Event\WorkerExceptionEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerFirstRequestEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerKernelRebootedEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerStartEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerStopEvent;
use Psr\Log\LoggerInterface;
use Spiral\RoadRunner\Http\PSR7WorkerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\RebootableInterface;

/**
 * @internal
 */
final class Worker implements WorkerInterface
{
    private KernelInterface $kernel;
    private PSR7WorkerInterface $psrWorker;
    private LoggerInterface $logger;
    private Dependencies $dependencies;

    public function __construct(
        KernelInterface $kernel,
        LoggerInterface $logger,
        PSR7WorkerInterface $psrWorker
    ) {
        $this->kernel = $kernel;
        $this->psrWorker = $psrWorker;
        $this->logger = $logger;

        /** @var Dependencies */
        $dependencies = $kernel->getContainer()->get(Dependencies::class);
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

        $this->dependencies->getEventDispatcher()->dispatch(new WorkerStartEvent());

        $firstRequest = true;

        while ($psrRequest = $this->psrWorker->waitRequest()) {
            $sent = false;
            try {
                if ($firstRequest) {
                    $firstRequest = false;
                    $this->dependencies->getEventDispatcher()->dispatch(new WorkerFirstRequestEvent());
                }
                $gen = $this->dependencies->getRequestHandler()->handle($psrRequest);

                $this->psrWorker->respond($gen->current());

                $sent = true;

                consumes($gen);
            } catch (\Throwable $e) {
                if (!$sent) {
                    $this->psrWorker->getWorker()->error($this->kernel->isDebug() ? (string) $e : 'Internal server error');
                }

                $this->logger->error('An error occured: '.$e->getMessage(), ['throwable' => $e]);

                $this->dependencies->getEventDispatcher()->dispatch(new WorkerExceptionEvent($e));

                $this->psrWorker->getWorker()->stop();
            } finally {
                if ($this->kernel instanceof RebootableInterface && $this->dependencies->getKernelRebootStrategy()->shouldReboot()) {
                    $this->kernel->reboot(null);
                    /** @var Dependencies */
                    $deps = $this->kernel->getContainer()->get(Dependencies::class);

                    $this->dependencies = $deps;
                    $this->dependencies->getEventDispatcher()->dispatch(new WorkerKernelRebootedEvent());
                }

                $this->dependencies->getKernelRebootStrategy()->clear();
            }
        }

        $this->dependencies->getEventDispatcher()->dispatch(new WorkerStopEvent());
    }
}
