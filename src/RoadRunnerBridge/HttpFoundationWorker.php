<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\RoadRunnerBridge;

use Baldinof\RoadRunnerBundle\RoadRunnerBridge\HttpFoundationWorker\HttpFoundationResponder;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\HttpFoundationWorker\MultipartFormDataParser;
use Spiral\RoadRunner\Http\HttpWorkerInterface;
use Spiral\RoadRunner\Http\Request as RoadRunnerRequest;
use Spiral\RoadRunner\WorkerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

final class HttpFoundationWorker implements HttpFoundationWorkerInterface
{
    private HttpWorkerInterface $httpWorker;
    private HttpFoundationResponder $responder;
    private array $originalServer;
    private MultipartFormDataParser $multipartParser;
    /** @var list<string> */
    private array $tmpFiles = [];

    public function __construct(HttpWorkerInterface $httpWorker, HttpFoundationResponder $responder)
    {
        $this->httpWorker = $httpWorker;
        $this->responder = $responder;
        $this->originalServer = $_SERVER;
        $this->multipartParser = new MultipartFormDataParser();
    }

    public function __destruct()
    {
        $this->removeTmpFiles();
    }

    public function waitRequest(): ?SymfonyRequest
    {
        $this->removeTmpFiles();

        $rrRequest = $this->httpWorker->waitRequest();

        if ($rrRequest === null) {
            return null;
        }

        return $this->toSymfonyRequest($rrRequest);
    }

    public function respond(SymfonyResponse $response): void
    {
        if (!$this->responder->supports($response)) {
            throw new \RuntimeException('Unsupported response.');
        }

        $this->responder->respond($this->httpWorker, $response);
    }

    public function getWorker(): WorkerInterface
    {
        return $this->httpWorker->getWorker();
    }

    private function toSymfonyRequest(RoadRunnerRequest $rrRequest): SymfonyRequest
    {
        $_SERVER = $this->configureServer($rrRequest);

        [$parsedBody, $uploads] = $this->parseBody($rrRequest);

        $files = $this->wrapUploads($uploads);

        $request = new SymfonyRequest(
            $rrRequest->query,
            $parsedBody,
            $rrRequest->attributes,
            $rrRequest->cookies,
            $files,
            $_SERVER,
            $rrRequest->body
        );

        $request->headers->add($rrRequest->headers);

        return $request;
    }

    /**
     * Must be called after configureServer(), as it relies on $_SERVER['CONTENT_TYPE'].
     *
     * @return array{0: array<mixed>, 1: array<mixed>} Parsed body and uploads (RoadRunner format)
     */
    private function parseBody(RoadRunnerRequest $rrRequest): array
    {
        if ($rrRequest->parsed) {
            return [$rrRequest->getParsedBody() ?? [], $rrRequest->uploads];
        }

        // With RoadRunner `http.raw_body: true`, the body is forwarded untouched:
        // parse forms with the same rules RoadRunner applies when it parses the body.
        $contentType = \is_string($_SERVER['CONTENT_TYPE'] ?? null) ? $_SERVER['CONTENT_TYPE'] : '';

        if ($rrRequest->body === '' || \in_array($rrRequest->method, ['HEAD', 'OPTIONS'], true)) {
            return [[], $rrRequest->uploads];
        }

        if (str_starts_with(strtolower($contentType), 'application/x-www-form-urlencoded')) {
            parse_str($rrRequest->body, $data);

            return [$data, []];
        }

        if (str_starts_with(strtolower($contentType), 'multipart/form-data') && MultipartFormDataParser::isSupported()) {
            [$data, $uploads, $tmpFiles] = $this->multipartParser->parse($contentType, $rrRequest->body);

            array_push($this->tmpFiles, ...$tmpFiles);

            return [$data, $uploads];
        }

        return [[], $rrRequest->uploads];
    }

    /**
     * Uploads parsed from raw bodies must survive until kernel.terminate listeners
     * are done, so they are removed when the next request comes in.
     */
    private function removeTmpFiles(): void
    {
        foreach ($this->tmpFiles as $tmpFile) {
            if (is_file($tmpFile)) {
                @unlink($tmpFile);
            }
        }

        $this->tmpFiles = [];
    }

    private function configureServer(RoadRunnerRequest $request): array
    {
        $server = $this->originalServer;

        $components = parse_url($request->uri);

        if ($components === false) {
            throw new \Exception('Failed to parse RoadRunner request URI: '.$request->uri);
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
            $server['REQUEST_URI'] .= '?'.$components['query'];
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
                $server['HTTP_'.$key] = implode(', ', $value);
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
}
