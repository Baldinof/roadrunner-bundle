<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\TerminableInterface;

/**
 * @internal
 */
final class KernelHandler implements RequestHandlerInterface
{
    private HttpKernelInterface $kernel;
    private \Closure $startTimeReset;

    public function __construct(HttpKernelInterface $kernel)
    {
        $this->kernel = $kernel;

        if ($kernel instanceof Kernel && $kernel->isDebug()) {
            $this->startTimeReset = (function () use ($kernel) {
                $kernel->startTime = microtime(true);
            })->bindTo(null, Kernel::class);
        } else {
            $this->startTimeReset = function () {};
        }
    }

    public function handle(Request $request): \Iterator
    {
        ($this->startTimeReset)();

        $symfonyRequest = $request;

        $this->handleBasicAuth($symfonyRequest);

        $symfonyResponse = $this->kernel->handle($symfonyRequest);

        yield $symfonyResponse;

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
