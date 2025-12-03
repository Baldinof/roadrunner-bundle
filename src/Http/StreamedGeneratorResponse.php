<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Http;

use Symfony\Component\HttpFoundation\StreamedResponse;

final class StreamedGeneratorResponse extends StreamedResponse
{
    public function __construct(iterable|callable $callbackOrChunks, int $status = 200, array $headers = [])
    {
        if (is_iterable($callbackOrChunks)) {
            $callbackOrChunks = fn () => yield from $callbackOrChunks;
        }
        parent::__construct($callbackOrChunks, $status, $headers);
    }
}
