<?php

namespace Baldinof\RoadRunnerBundle\Worker;

use Baldinof\RoadRunnerBundle\Event\WorkerStartEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerStopEvent;
use Spiral\RoadRunner\PSR7Client;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\RebootableInterface;
use Symfony\Component\HttpKernel\TerminableInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class Worker implements WorkerInterface
{
    private $kernel;
    private $httpMessageFactory;
    private $httpFoundationFactory;
    private $eventDispatcher;
    private $configuration;
    private $psrClient;

    /**
     * @var \Closure
     */
    private $startTimeReset;

    public function __construct(
        KernelInterface $kernel,
        HttpMessageFactoryInterface $httpMessageFactory,
        HttpFoundationFactoryInterface $httpFoundationFactory,
        EventDispatcherInterface $eventDispatcher,
        Configuration $configuration,
        PSR7Client $psrClient
    ) {
        $this->kernel = $kernel;
        $this->httpMessageFactory = $httpMessageFactory;
        $this->httpFoundationFactory = $httpFoundationFactory;
        $this->eventDispatcher = $eventDispatcher;
        $this->configuration = $configuration;
        $this->psrClient = $psrClient;

        if ($kernel->isDebug() && $kernel instanceof Kernel) {
            $this->startTimeReset = (function () use ($kernel) {
                $kernel->startTime = microtime(true);
            })->bindTo(null, Kernel::class);
        } else {
            $this->startTimeReset = function () {
            };
        }

        $withReboot = $this->configuration->shouldRebootKernel();

        if ($withReboot && !$this->kernel instanceof RebootableInterface) {
            throw new \InvalidArgumentException("The worker is configured to reboot the kernel, but the passed kernel does not implement Symfony\Component\HttpKernel\RebootableInterface");
        }
    }

    public function start(): void
    {
        $debug = $this->kernel->isDebug();
        $withReboot = $this->configuration->shouldRebootKernel();

        if ($trustedProxies = $_SERVER['TRUSTED_PROXIES'] ?? $_ENV['TRUSTED_PROXIES'] ?? false) {
            Request::setTrustedProxies(explode(',', $trustedProxies), Request::HEADER_X_FORWARDED_ALL ^ Request::HEADER_X_FORWARDED_HOST);
        }

        if ($trustedHosts = $_SERVER['TRUSTED_HOSTS'] ?? $_ENV['TRUSTED_HOSTS'] ?? false) {
            Request::setTrustedHosts(explode(',', $trustedHosts));
        }

        $isTerminable = $this->kernel instanceof TerminableInterface;

        $this->eventDispatcher->dispatch(new WorkerStartEvent());

        while ($psrRequest = $this->psrClient->acceptRequest()) {
            try {
                ($this->startTimeReset)();

                $request = $this->httpFoundationFactory->createRequest($psrRequest);

                $response = $this->kernel->handle($request);

                $this->psrClient->respond($this->httpMessageFactory->createResponse($response));

                if ($isTerminable) {
                    $this->kernel->terminate($request, $response);
                }

                if ($withReboot) {
                    $this->kernel->reboot(null);
                }
            } catch (\Throwable $e) {
                $this->psrClient->getWorker()->error($debug ? (string) $e : 'Internal server error');
                $this->psrClient->getWorker()->stop();
            }
        }

        $this->eventDispatcher->dispatch(new WorkerStopEvent());
    }
}
