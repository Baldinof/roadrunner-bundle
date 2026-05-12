<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\RoadRunnerBridge\HttpFoundationWorker;

use Spiral\RoadRunner\Http\HttpWorkerInterface;
use Symfony\Component\HttpFoundation\Response;

final class BufferedResponder implements HttpFoundationResponder
{
    public function supports(Response $response): bool
    {
        return true;
    }

    public function respond(HttpWorkerInterface $httpWorker, Response $response): void
    {
        $content = '';
        ob_start(function ($buffer) use (&$content) {
            $content .= $buffer;

            return '';
        });

        $response->sendContent();
        ob_end_clean();

        $httpWorker->respond(
            status: $response->getStatusCode(),
            body: $content,
            headers: HttpFoundationResponderHelper::stringifyHeaders($response->headers->all()),
            endOfStream: true,
        );
    }
}
