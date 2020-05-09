<?php

namespace Baldinof\RoadRunnerBundle\Http;

use Psr\Http\Message\ServerRequestInterface;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
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

        $tempBuffer = new \SplTempFileObject();
        ob_start(function ($buffer) use (&$tempBuffer) {
            $tempBuffer->fwrite($buffer);
        });

        $symfonyResponse = $this->kernel->handle($symfonyRequest);
        $psrResponse = $this->httpMessageFactory->createResponse($symfonyResponse);
        ob_end_clean();

        if ($symfonyResponse instanceof StreamedResponse) {
            $dataSize = $tempBuffer->ftell();
            $tempBuffer->rewind();
            $psrResponse->getBody()->write($tempBuffer->fread($dataSize));
        }

        unset($tempBuffer);

        yield $psrResponse;

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
