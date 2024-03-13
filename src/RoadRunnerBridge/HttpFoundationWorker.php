<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\RoadRunnerBridge;

use Baldinof\RoadRunnerBundle\Helpers\StreamedJsonResponseHelper;
use Baldinof\RoadRunnerBundle\Http\Response\StreamedResponse;
use Spiral\RoadRunner\Http\Exception\StreamStoppedException;
use Spiral\RoadRunner\Http\HttpWorkerInterface;
use Spiral\RoadRunner\Http\Request as RoadRunnerRequest;
use Spiral\RoadRunner\WorkerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;

final class HttpFoundationWorker implements HttpFoundationWorkerInterface
{
    private HttpWorkerInterface $httpWorker;
    private array $originalServer;
    private \Closure $binaryFileResponseParameterExtractor;

    public function __construct(HttpWorkerInterface $httpWorker)
    {
        $this->httpWorker = $httpWorker;
        $this->originalServer = $_SERVER;

        $this->binaryFileResponseParameterExtractor = \Closure::bind(static fn(BinaryFileResponse $binaryFileResponse) => [
            $binaryFileResponse->maxlen,
            $binaryFileResponse->offset,
            $binaryFileResponse->chunkSize,
            $binaryFileResponse->deleteFileAfterSend
        ], null, BinaryFileResponse::class);
    }

    public function waitRequest(): ?SymfonyRequest
    {
        $rrRequest = $this->httpWorker->waitRequest();

        if ($rrRequest === null) {
            return null;
        }

        return $this->toSymfonyRequest($rrRequest);
    }

    public function respond(SymfonyResponse $response): void
    {
        $content = match (true) {
            $response instanceof StreamedJsonResponse => StreamedJsonResponseHelper::toGenerator($response),
            $response instanceof StreamedResponse => $response->getCallback(),
            $response instanceof BinaryFileResponse => $this->createFileStreamGenerator($response),
            default => $this->createDefaultContentGetter($response),
        };

        $headers = $this->stringifyHeaders($response->headers->all());

        try {
            $this->httpWorker->respond($response->getStatusCode(), $content, $headers);
        } catch (StreamStoppedException){

        }
    }

    public function getWorker(): WorkerInterface
    {
        return $this->httpWorker->getWorker();
    }

    private function toSymfonyRequest(RoadRunnerRequest $rrRequest): SymfonyRequest
    {
        $_SERVER = $this->configureServer($rrRequest);

        $files = $this->wrapUploads($rrRequest->uploads);

        $request = new SymfonyRequest(
            $rrRequest->query,
            $rrRequest->getParsedBody() ?? [],
            $rrRequest->attributes,
            $rrRequest->cookies,
            $files,
            $_SERVER,
            $rrRequest->body
        );

        $request->headers->add($rrRequest->headers);

        return $request;
    }

    private function configureServer(RoadRunnerRequest $request): array
    {
        $server = $this->originalServer;

        $components = parse_url($request->uri);

        if ($components === false) {
            throw new \Exception('Failed to parse RoadRunner request URI');
        }

        if (isset($components['host'])) {
            $server['SERVER_NAME'] = $components['host'];
        }

        if (isset($components['port'])) {
            $server['SERVER_PORT'] = $components['port'];
        } elseif (isset($components['scheme'])) {
            $server['SERVER_PORT'] = $components['scheme'] === 'https' ? 443 : 80;
        }

        $server['REQUEST_URI'] = $components['path'] ?? '';
        if (isset($components['query']) && $components['query'] !== '') {
            $server['QUERY_STRING'] = $components['query'];
            $server['REQUEST_URI'] .= '?' . $components['query'];
        }

        if (isset($components['scheme']) && $components['scheme'] === 'https') {
            $server['HTTPS'] = 'on';
        }

        $server['REQUEST_TIME'] = $this->timeInt();
        $server['REQUEST_TIME_FLOAT'] = $this->timeFloat();
        $server['REMOTE_ADDR'] = $request->getRemoteAddr();
        $server['REQUEST_METHOD'] = $request->method;
        $server['SERVER_PROTOCOL'] = $request->protocol;

        $server['HTTP_USER_AGENT'] = '';
        foreach ($request->headers as $key => $value) {
            $key = strtoupper(str_replace('-', '_', $key));
            if (\in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $server[$key] = implode(', ', $value);
            } else {
                $server['HTTP_' . $key] = implode(', ', $value);
            }
        }

        $authorizationHeader = $request->headers['Authorization'][0] ?? null;

        if ($authorizationHeader !== null && preg_match("/Basic\s+(.*)$/i", $authorizationHeader, $matches)) {
            $decoded = base64_decode($matches[1], true);

            if ($decoded) {
                $userPass = explode(':', $decoded, 2);

                $server['PHP_AUTH_USER'] = $userPass[0];
                $server['PHP_AUTH_PW'] = $userPass[1] ?? '';
            }
        }

        return $server;
    }

    /**
     * Wraps all uploaded files with UploadedFile.
     */
    private function wrapUploads(array $files): array
    {
        $result = [];

        foreach ($files as $index => $file) {
            if (!isset($file['name'])) {
                $result[$index] = $this->wrapUploads($file);
                continue;
            }

            $result[$index] = new UploadedFile($file['tmpName'] ?? '', $file['name'], $file['mime'], $file['error'], true);
        }

        return $result;
    }

    private function timeInt(): int
    {
        return time();
    }

    private function timeFloat(): float
    {
        return microtime(true);
    }

    /**
     * @param array<string, array<int, string|null>>|array<int, string|null> $headers
     *
     * @return array<int|string, string[]>
     */
    private function stringifyHeaders(array $headers): array
    {
        return array_map(static function ($headerValues) {
            return array_map(static fn($val) => (string)$val, (array)$headerValues);
        }, $headers);
    }

    /**
     * Basically a copy of BinaryFileResponse->sendContent()
     * @param BinaryFileResponse $response
     * @return \Generator
     */
    private function createFileStreamGenerator(BinaryFileResponse $response): \Generator
    {
        $extractor = $this->binaryFileResponseParameterExtractor;

        [$maxlen, $offset, $chunkSize, $deleteFileAfterSend] = $extractor($response);

        try {
            if (!$response->isSuccessful()) {
                return;
            }

            $file = fopen($response->getFile()->getPathname(), "r");

            if ($maxlen === 0) {
                return;
            }

            if ($offset !== 0) {
                fseek($file, $offset);
            }

            $length = $maxlen;
            while ($length && !feof($file)) {
                $read = $length > $chunkSize || 0 > $length ? $chunkSize : $length;

                if (false === $data = fread($file, $read)) {
                    break;
                }

                while ('' !== $data) {
                    try {
                        yield $data;
                    } catch (StreamStoppedException) {
                        break 2;
                    }

                    if (0 < $length) {
                        $length -= $read;
                    }
                    $data = substr($data, $read);
                }
            }

            fclose($file);
        } finally {
            if ($deleteFileAfterSend && is_file($response->getFile()->getPathname())) {
                unlink($response->getFile()->getPathname());
            }
        }
    }

    /**
     * @param SymfonyResponse $response
     * @return \Generator
     */
    private function createDefaultContentGetter(SymfonyResponse $response): \Generator
    {
        ob_start();
        $response->sendContent();
        yield ob_get_clean();
    }
}
