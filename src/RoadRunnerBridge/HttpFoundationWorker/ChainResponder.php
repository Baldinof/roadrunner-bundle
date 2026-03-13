<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\RoadRunnerBridge\HttpFoundationWorker;

use Spiral\RoadRunner\Http\HttpWorkerInterface;
use Symfony\Component\HttpFoundation\Response;

final class ChainResponder implements HttpFoundationResponder
{
    /**
     * @param iterable<HttpFoundationResponder> $responders
     */
    public function __construct(
        private readonly iterable $responders,
    ) {
    }

    public function supports(Response $response): bool
    {
        return (bool) $this->resolveResponder($response);
    }

    public function respond(HttpWorkerInterface $httpWorker, Response $response): void
    {
        $responder = $this->resolveResponder($response) ?? throw new \RuntimeException('Unsupported response.');
        $responder->respond($httpWorker, $response);
    }

    private function resolveResponder(Response $response): ?HttpFoundationResponder
    {
        foreach ($this->responders as $responder) {
            if ($responder->supports($response)) {
                return $responder;
            }
        }

        return null;
    }
}
