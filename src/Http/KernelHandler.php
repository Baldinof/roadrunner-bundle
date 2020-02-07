<?php

namespace Baldinof\RoadRunnerBundle\Http;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

final class KernelHandler implements IteratorRequestHandlerInterface
{
    private $kernel;
    private $httpMessageFactory;
    private $httpFoundationFactory;

    /**
     * @var \Closure
     */
    private $startTimeReset;

    public function __construct(
        KernelInterface $kernel,
        HttpMessageFactoryInterface $httpMessageFactory,
        HttpFoundationFactoryInterface $httpFoundationFactory
    ) {
        $this->kernel = $kernel;
        $this->httpMessageFactory = $httpMessageFactory;
        $this->httpFoundationFactory = $httpFoundationFactory;

        if ($kernel->isDebug() && $kernel instanceof Kernel) {
            $this->startTimeReset = (function () use ($kernel) {
                $kernel->startTime = microtime(true);
            })->bindTo(null, Kernel::class);
        } else {
            $this->startTimeReset = function () {};
        }
    }

    public function handle(ServerRequestInterface $request): \Iterator
    {
        ($this->startTimeReset)();

        $symfonyRequest = $this->httpFoundationFactory->createRequest($request);

        $symfonyResponse = $this->kernel->handle($symfonyRequest);

        yield $this->httpMessageFactory->createResponse($symfonyResponse);

        if ($this->kernel instanceof TerminableInterface) {
            $this->kernel->terminate($symfonyRequest, $symfonyResponse);
        }
    }
}
