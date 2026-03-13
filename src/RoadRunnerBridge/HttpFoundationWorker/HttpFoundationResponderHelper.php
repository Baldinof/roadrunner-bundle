<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\RoadRunnerBridge\HttpFoundationWorker;

final class HttpFoundationResponderHelper
{
    private function __construct()
    {
    }

    /**
     * @param array<string, array<int, string|null>>|array<int, string|null> $headers
     *
     * @return array<int|string, string[]>
     */
    public static function stringifyHeaders(array $headers): array
    {
        return array_map(static function ($headerValues) {
            return array_map(static fn ($val) => (string) $val, (array) $headerValues);
        }, $headers);
    }
}
