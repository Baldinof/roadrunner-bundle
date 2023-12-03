<?php

namespace Baldinof\RoadRunnerBundle\Helpers;

use Symfony\Component\HttpFoundation\StreamedJsonResponse;

// Basically copy of Symfony\Component\HttpFoundation\StreamedJsonResponse
// but adds `yield`ing, instead of `echo`s
class StreamedJsonResponseHelper
{
    public static function toGenerator(StreamedJsonResponse $response): \Generator
    {
        $ref = new \ReflectionClass($response);

        $encodingOptions = $ref->getProperty("encodingOptions")->getValue($response);
        $data = $ref->getProperty("data")->getValue($response);
        $placeholder = $ref->getConstant("PLACEHOLDER");

        return self::stream($data, $encodingOptions, $placeholder);
    }

    private static function stream(iterable $data, int $encodingOptions, string $placeholder): \Generator
    {
        $jsonEncodingOptions = \JSON_THROW_ON_ERROR | $encodingOptions;
        $keyEncodingOptions = $jsonEncodingOptions & ~\JSON_NUMERIC_CHECK;

        return self::streamData($data, $jsonEncodingOptions, $keyEncodingOptions, $placeholder);
    }

    private static function streamData(mixed $data, int $jsonEncodingOptions, int $keyEncodingOptions, string $placeholder): \Generator
    {
        if (\is_array($data)) {
            foreach (self::streamArray($data, $jsonEncodingOptions, $keyEncodingOptions, $placeholder) as $item) {
                yield $item;
            }

            return;
        }

        if (is_iterable($data) && !$data instanceof \JsonSerializable) {
            foreach (self::streamIterable($data, $jsonEncodingOptions, $keyEncodingOptions, $placeholder) as $item) {
                yield $item;
            }

            return;
        }

        yield json_encode($data, $jsonEncodingOptions);
    }

    private static function streamArray(array $data, int $jsonEncodingOptions, int $keyEncodingOptions, string $placeholder): \Generator
    {
        $generators = [];

        array_walk_recursive($data, function (&$item, $key) use (&$generators, $placeholder) {
            if ($placeholder === $key) {
                // if the placeholder is already in the structure it should be replaced with a new one that explode
                // works like expected for the structure
                $generators[] = $key;
            }

            // generators should be used but for better DX all kind of Traversable and objects are supported
            if (\is_object($item)) {
                $generators[] = $item;
                $item = $placeholder;
            } elseif ($placeholder === $item) {
                // if the placeholder is already in the structure it should be replaced with a new one that explode
                // works like expected for the structure
                $generators[] = $item;
            }
        });

        $jsonParts = explode('"' . $placeholder . '"', json_encode($data, $jsonEncodingOptions));

        foreach ($generators as $index => $generator) {
            // send first and between parts of the structure
            yield $jsonParts[$index];

            foreach (self::streamData($generator, $jsonEncodingOptions, $keyEncodingOptions, $placeholder) as $child) {
                yield $child;
            }
        }

        // send last part of the structure
        yield $jsonParts[array_key_last($jsonParts)];
    }

    private static function streamIterable(iterable $iterable, int $jsonEncodingOptions, int $keyEncodingOptions, string $placeholder): \Generator
    {
        $isFirstItem = true;
        $startTag = '[';

        foreach ($iterable as $key => $item) {
            if ($isFirstItem) {
                $isFirstItem = false;
                // depending on the first elements key the generator is detected as a list or map
                // we can not check for a whole list or map because that would hurt the performance
                // of the streamed response which is the main goal of this response class
                if (0 !== $key) {
                    $startTag = '{';
                }

                yield $startTag;
            } else {
                // if not first element of the generic, a separator is required between the elements
                yield ',';
            }

            if ('{' === $startTag) {
                yield json_encode((string)$key, $keyEncodingOptions) . ':';
            }

            foreach (self::streamData($item, $jsonEncodingOptions, $keyEncodingOptions, $placeholder) as $child) {
                yield $child;
            }
        }

        if ($isFirstItem) { // indicates that the generator was empty
            yield '[';
        }

        yield '[' === $startTag ? ']' : '}';
    }
}