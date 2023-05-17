<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Worker;

use Baldinof\RoadRunnerBundle\Event\WorkerExceptionEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerKernelRebootedEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerStartEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerStopEvent;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\HttpFoundationWorkerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\RebootableInterface;

use function Baldinof\RoadRunnerBundle\consumes;

/**
 * @internal
 */
final class HttpWorker implements WorkerInterface
{
    private KernelInterface $kernel;
    private LoggerInterface $logger;
    private HttpDependencies $dependencies;
    private HttpFoundationWorkerInterface $httpFoundationWorker;

    private array $trustedProxies = [];
    private int $trustedHeaders = 0;
    private bool $shouldRedeclareTrustedProxies = false;

    /**
     * @var \Closure(\Throwable): Response
     */
    private \Closure $renderError;

    public function __construct(
        KernelInterface $kernel,
        LoggerInterface $logger,
        HttpFoundationWorkerInterface $httpFoundationWorker
    ) {
        $this->kernel = $kernel;
        $this->logger = $logger;
        $this->httpFoundationWorker = $httpFoundationWorker;

        $container = $kernel->getContainer();

        /** @var HttpDependencies */
        $dependencies = $container->get(HttpDependencies::class);
        $this->dependencies = $dependencies;

        if ($container->hasParameter('kernel.trusted_proxies') && $container->hasParameter('kernel.trusted_headers')) {
            $trustedProxies = $container->getParameter('kernel.trusted_proxies');
            $trustedHeaders = $container->getParameter('kernel.trusted_headers');

            if (!\is_int($trustedHeaders)) {
                throw new \InvalidArgumentException('Parameter "kernel.trusted_headers" must be an integer');
            }

            if (!\is_string($trustedProxies) && !\is_array($trustedProxies)) {
                throw new \InvalidArgumentException('Parameter "kernel.trusted_proxies" must be a string or an array');
            }

            $this->trustedProxies = \is_array($trustedProxies) ? $trustedProxies : array_map('trim', explode(',', $trustedProxies));
            $this->trustedHeaders = $trustedHeaders;
        }

        $this->shouldRedeclareTrustedProxies = \in_array('REMOTE_ADDR', $this->trustedProxies, true);

        if (class_exists(HtmlErrorRenderer::class)) {
            $htmlRenderer = new HtmlErrorRenderer($kernel->isDebug());
            $this->renderError = static function (\Throwable $e) use ($htmlRenderer): Response {
                $flatten = $htmlRenderer->render($e);

                return new Response($flatten->getAsString(), $flatten->getStatusCode(), $flatten->getHeaders());
            };
        } else {
            $this->renderError = static function (\Throwable $e) use ($kernel) {
                $message = $kernel->isDebug() ? (string) $e : 'Internal error';

                return new Response($message, 500, ['Content-Type' => 'text/plain']);
            };
        }
    }

    public function start(): void
    {
        $this->dependencies->getEventDispatcher()->dispatch(new WorkerStartEvent());

        while ($request = $this->httpFoundationWorker->waitRequest()) {
            if ($this->shouldRedeclareTrustedProxies) {
                Request::setTrustedProxies($this->trustedProxies, $this->trustedHeaders);
            }

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
                    $response = ($this->renderError)($e);
                    $this->httpFoundationWorker->respond($response);
                }

                $this->logger->error('An error occured: '.$e->getMessage(), ['throwable' => $e]);

                $this->dependencies->getEventDispatcher()->dispatch(new WorkerExceptionEvent($e));

                $this->httpFoundationWorker->getWorker()->stop();
                break;
            } finally {
                if ($this->kernel instanceof RebootableInterface && $this->dependencies->getKernelRebootStrategy()->shouldReboot()) {
                    $this->kernel->reboot(null);
                    /** @var HttpDependencies */
                    $deps = $this->kernel->getContainer()->get(HttpDependencies::class);

                    $this->dependencies = $deps;
                    $this->dependencies->getEventDispatcher()->dispatch(new WorkerKernelRebootedEvent());
                }

                $this->dependencies->getKernelRebootStrategy()->clear();
            }
        }

        $this->dependencies->getEventDispatcher()->dispatch(new WorkerStopEvent());
    }
}
