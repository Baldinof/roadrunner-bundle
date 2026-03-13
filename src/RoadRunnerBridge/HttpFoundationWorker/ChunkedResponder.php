<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\RoadRunnerBridge\HttpFoundationWorker;

use Spiral\RoadRunner\Http\HttpWorkerInterface;
use Symfony\Component\HttpFoundation\Response;

final class ChunkedResponder implements HttpFoundationResponder
{
    /**
     * @param list<class-string<Response>> $responseClasses
     * @param positive-int                 $chunkSize
     */
    public function __construct(
        private readonly array $responseClasses,
        private readonly int $chunkSize,
    ) {
    }

    public function supports(Response $response): bool
    {
        return \in_array($response::class, $this->responseClasses, true);
    }

    public function respond(HttpWorkerInterface $httpWorker, Response $response): void
    {
        ob_start(function (string $buffer, int $phase) use ($response, $httpWorker) {
            static $content = '';
            $content .= $buffer;

            $isFirst = ($phase & PHP_OUTPUT_HANDLER_START) === PHP_OUTPUT_HANDLER_START;
            $isLast = ($phase & PHP_OUTPUT_HANDLER_END) === PHP_OUTPUT_HANDLER_END;

            // Skip, if content size less, then chunk size
            if (\strlen($content) < $this->chunkSize && !$isLast) {
                return '';
            }

            $httpWorker->respond(
                status: $response->getStatusCode(),
                body: $content,
                headers: $isFirst ? HttpFoundationResponderHelper::stringifyHeaders($response->headers->all()) : [],
                endOfStream: $isLast,
            );
            $content = '';

            return '';
        }, $this->chunkSize);
        $response->sendContent();
        @ob_end_clean();
    }
}
