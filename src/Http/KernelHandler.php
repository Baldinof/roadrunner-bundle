<?php

namespace Baldinof\RoadRunnerBundle\Http;

use Closure;
use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\TerminableInterface;

final class KernelHandler implements IteratorRequestHandlerInterface
{
    private HttpKernelInterface $kernel;
    private HttpMessageFactoryInterface $httpMessageFactory;
    private HttpFoundationFactoryInterface $httpFoundationFactory;
    private Closure $startTimeReset;

    public function __construct(
        HttpKernelInterface $kernel,
        HttpMessageFactoryInterface $httpMessageFactory,
        HttpFoundationFactoryInterface $httpFoundationFactory
    ) {
        $this->kernel = $kernel;
        $this->httpMessageFactory = $httpMessageFactory;
        $this->httpFoundationFactory = $httpFoundationFactory;

        if ($kernel instanceof Kernel && $kernel->isDebug()) {
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

        $this->handleBasicAuth($symfonyRequest);

        $symfonyResponse = $this->kernel->handle($symfonyRequest);

        yield $this->httpMessageFactory->createResponse($symfonyResponse);

        if ($this->kernel instanceof TerminableInterface) {
            $this->kernel->terminate($symfonyRequest, $symfonyResponse);
        }
    }

    private function handleBasicAuth(Request $request): void
    {
        $authorizationHeader = $request->headers->get('Authorization');

        if (!$authorizationHeader) {
            return;
        }

        if (preg_match("/Basic\s+(.*)$/i", $authorizationHeader, $matches)) {
            $decoded = base64_decode($matches[1], true);

            if (!$decoded) {
                return;
            }

            $userPass = explode(':', $decoded, 2);

            $userInfo = [
                'PHP_AUTH_USER' => $userPass[0],
                'PHP_AUTH_PW' => $userPass[1] ?? '',
            ];

            $request->headers->add($userInfo);
            $request->server->add($userInfo);
        }
    }
}
