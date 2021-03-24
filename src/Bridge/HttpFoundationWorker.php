<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Bridge;

use Spiral\RoadRunner\Http\HttpWorkerInterface;
use Spiral\RoadRunner\Http\Request as RoadRunnerRequest;
use Spiral\RoadRunner\WorkerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class HttpFoundationWorker implements HttpFoundationWorkerInterface
{
    private HttpWorkerInterface $httpWorker;
    private array $originalServer;

    public function __construct(HttpWorkerInterface $httpWorker)
    {
        $this->httpWorker = $httpWorker;
        $this->originalServer = $_SERVER;
    }

    public function waitRequest(): ?SymfonyRequest
    {
        $rrRequest = $this->httpWorker->waitRequest();

        if ($rrRequest === null) {
            return null;
        }

        return $this->toSymfonyRequest($rrRequest);
    }

    public function respond(SymfonyResponse $symfonyResponse): void
    {
        if ($symfonyResponse instanceof BinaryFileResponse && !$symfonyResponse->headers->has('Content-Range')) {
            $content = file_get_contents($symfonyResponse->getFile()->getPathname());
            if ($content === false) {
                throw new \RuntimeException(sprintf("Cannot read file '%s'", $symfonyResponse->getFile()->getPathname())); // TODO: custom error
            }
        } else {
            if ($symfonyResponse instanceof StreamedResponse || $symfonyResponse instanceof BinaryFileResponse) {
                $content = '';

                ob_start(function ($buffer) use (&$content) {
                    $content .= $buffer;

                    return '';
                });

                $symfonyResponse->sendContent();
                ob_end_clean();
            } else {
                $content = (string) $symfonyResponse->getContent();
            }
        }

        $headers = $symfonyResponse->headers->all();

        $cookies = $symfonyResponse->headers->getCookies();
        if (!empty($cookies)) {
            $headers['Set-Cookie'] = [];

            foreach ($cookies as $cookie) {
                $headers['Set-Cookie'][] = $cookie->__toString();
            }
        }

        $this->httpWorker->respond($symfonyResponse->getStatusCode(), $content, $headers);
    }

    public function getWorker(): WorkerInterface
    {
        return $this->httpWorker->getWorker();
    }

    private function toSymfonyRequest(RoadRunnerRequest $rrRequest): SymfonyRequest
    {
        $server = $this->configureServer($rrRequest);

        $files = $this->wrapUploads($rrRequest->uploads);

        return new SymfonyRequest(
            $rrRequest->query,
            $rrRequest->getParsedBody() ?? [],
            $rrRequest->attributes,
            $rrRequest->cookies,
            $files,
            $server,
            $rrRequest->body
        );
    }

    private function configureServer(RoadRunnerRequest $request): array
    {
        $server = $this->originalServer;

        $server['REQUEST_URI'] = $request->uri;
        $server['REQUEST_TIME'] = $this->timeInt();
        $server['REQUEST_TIME_FLOAT'] = $this->timeFloat();
        $server['REMOTE_ADDR'] = $request->getRemoteAddr();
        $server['REQUEST_METHOD'] = $request->method;

        $server['HTTP_USER_AGENT'] = '';
        foreach ($request->headers as $key => $value) {
            $key = \strtoupper(\str_replace('-', '_', $key));
            if (\in_array($key, ['CONTENT_TYPE', 'CONTENT_LENGTH'])) {
                $server[$key] = \implode(', ', $value);
            } else {
                $server['HTTP_'.$key] = \implode(', ', $value);
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

            $result[$index] = new UploadedFile($file['tmpName'] ?? '', $file['name'], $file['mime'], $file['error']);
        }

        return $result;
    }

    private function timeInt(): int
    {
        return \time();
    }

    private function timeFloat(): float
    {
        return \microtime(true);
    }
}
