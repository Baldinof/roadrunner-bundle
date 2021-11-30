<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Worker;

use function Baldinof\RoadRunnerBundle\consumes;
use Baldinof\RoadRunnerBundle\Event\WorkerExceptionEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerKernelRebootedEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerStartEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerStopEvent;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\HttpFoundationWorkerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\RebootableInterface;

/**
 * @internal
 */
final class Worker implements WorkerInterface
{
    private KernelInterface $kernel;
    private LoggerInterface $logger;
    private Dependencies $dependencies;
    private HttpFoundationWorkerInterface $httpFoundationWorker;

    public function __construct(
        KernelInterface $kernel,
        LoggerInterface $logger,
        HttpFoundationWorkerInterface $httpFoundationWorker
    ) {
        $this->kernel = $kernel;
        $this->logger = $logger;
        $this->httpFoundationWorker = $httpFoundationWorker;

        /** @var Dependencies */
        $dependencies = $kernel->getContainer()->get(Dependencies::class);
        $this->dependencies = $dependencies;
    }

    public function start(): void
    {
        if ($trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? $_ENV['TRUSTED_PROXIES'] ?? false) {
            Request::setTrustedProxies(
                explode(',', $trustedProxies),
                Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PORT | Request::HEADER_X_FORWARDED_PROTO
            );
        }

        if ($trustedHosts = $_SERVER['TRUSTED_HOSTS'] ?? $_ENV['TRUSTED_HOSTS'] ?? false) {
            Request::setTrustedHosts(explode(',', $trustedHosts));
        }

        $this->dependencies->getEventDispatcher()->dispatch(new WorkerStartEvent());

        while ($request = $this->httpFoundationWorker->waitRequest()) {
            $sent = false;
            try {
                $gen = $this->dependencies->getRequestHandler()->handle($request);

                /** @var Response $response */
                $response = $gen->current();

                $this->httpFoundationWorker->respond($response);

                $sent = true;

                consumes($gen);
            } catch (\Throwable $e) {
                if (!$sent) {
                    $this->httpFoundationWorker->getWorker()->error($this->kernel->isDebug() ? (string) $e : 'Internal server error');
                }

                $this->logger->error('An error occured: '.$e->getMessage(), ['throwable' => $e]);

                $this->dependencies->getEventDispatcher()->dispatch(new WorkerExceptionEvent($e));

                $this->httpFoundationWorker->getWorker()->stop();
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
