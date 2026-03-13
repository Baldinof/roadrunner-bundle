<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\RoadRunnerBridge;

use Baldinof\RoadRunnerBundle\RoadRunnerBridge\HttpFoundationWorker;
use org\bovigo\vfs\vfsStream;
use org\bovigo\vfs\vfsStreamDirectory;
use PHPUnit\Framework\TestCase;
use Spiral\RoadRunner\Http\HttpWorkerInterface;
use Spiral\RoadRunner\Http\Request as RoadRunnerRequest;
use Spiral\RoadRunner\WorkerInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\EventStreamResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedJsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class HttpFoundationWorkerTest extends TestCase
{
    private static vfsStreamDirectory $vfs;

    public static function setUpBeforeClass(): void
    {
        self::$vfs = vfsStream::setup('uploads');
    }

    /**
     * @param \Closure(RoadRunnerRequest): void $rrRequestConfigurator
     * @param \Closure(SymfonyRequest): void    $expectations
     *
     * @dataProvider provideRequests
     */
    public function test_it_convert_roadrunner_request_to_symfony(\Closure $rrRequestConfigurator, \Closure $expectations)
    {
        $rrRequest = $rrRequestConfigurator();

        $innerWorker = new MockWorker();
        $innerWorker->nextRequest = $rrRequest;

        $worker = new HttpFoundationWorker($innerWorker, new HttpFoundationWorker\BufferedResponder());
        $symfonyRequest = $worker->waitRequest();

        $expectations($symfonyRequest);
    }

    /** @noinspection PhpImmutablePropertyIsWrittenInspection */
    public function provideRequests()
    {
        yield 'full request' => [
            fn () => new RoadRunnerRequest(
                method: 'GET',
                uri: 'https://les-tilleuls.coop/about/kevin?page=1',
                protocol: 'HTTP/1.1',
                headers: [
                    'X-Dunglas-API-Platform' => ['1.0'],
                    'X-data' => ['a', 'b'],
                ],
                query: ['page' => '1'],
                body: json_encode(['country' => 'France']),
                parsed: true,
                cookies: ['city' => 'Lille'],
                uploads: [
                    'doc1' => $this->createUploadedFile('Doc 1', \UPLOAD_ERR_OK, 'doc1.txt', 'text/plain'),
                    'nested' => [
                        'docs' => [
                            $this->createUploadedFile('Doc 2', \UPLOAD_ERR_OK, 'doc2.txt', 'text/plain'),
                            $this->createUploadedFile('Doc 3', \UPLOAD_ERR_OK, 'doc3.txt', 'text/plain'),
                        ],
                    ],
                ],
                attributes: ['custom' => new \stdClass()],
            ),
            function (SymfonyRequest $symfonyRequest) {
                $files = $symfonyRequest->files->all();

                $this->assertEquals('https://les-tilleuls.coop/about/kevin?page=1', $symfonyRequest->getUri());
                $this->assertEquals(443, $symfonyRequest->getPort());
                $this->assertEquals('1', $symfonyRequest->query->get('page'));
                $this->assertEquals('doc1.txt', $files['doc1']->getClientOriginalName());
                $this->assertEquals('doc2.txt', $files['nested']['docs'][0]->getClientOriginalName());
                $this->assertEquals('doc3.txt', $files['nested']['docs'][1]->getClientOriginalName());
                $this->assertEquals('France', $symfonyRequest->request->get('country'));
                $this->assertEquals(new \stdClass(), $symfonyRequest->attributes->get('custom'));
                $this->assertEquals('Lille', $symfonyRequest->cookies->get('city'));
                $this->assertEquals('{"country":"France"}', $symfonyRequest->getContent());
                $this->assertEquals('1.0', $symfonyRequest->headers->get('X-Dunglas-API-Platform'));
                $this->assertEquals(['a', 'b'], $symfonyRequest->headers->all('X-data'));
                $this->assertEquals('HTTP/1.1', $symfonyRequest->getProtocolVersion());
            },
        ];

        yield 'non default port' => [
            fn () => new RoadRunnerRequest(uri: 'https://les-tilleuls.coop:8443/about/kevin'),
            fn (SymfonyRequest $r) => $this->assertSame(8443, $r->getPort()),
        ];

        yield 'https detection' => [
            fn () => new RoadRunnerRequest(uri: 'https://les-tilleuls.coop:8443/about/kevin'),
            fn (SymfonyRequest $r) => $this->assertTrue($r->isSecure()),
        ];

        yield 'POST body not parsed' => [
            fn () => new RoadRunnerRequest(body: 'the body'),
            function (SymfonyRequest $r) {
                $this->assertSame('the body', $r->getContent());
                $this->assertEmpty($r->request->all());
            },
        ];

        yield 'content-type & length added to $_SERVER' => [
            fn () => new RoadRunnerRequest(headers: [
                'content-type' => ['application/json'],
                'content-length' => ['42'],
            ]),
            function (SymfonyRequest $r) {
                $this->assertSame('application/json', $r->server->get('CONTENT_TYPE'));
                $this->assertSame('42', $r->server->get('CONTENT_LENGTH'));
            },
        ];

        yield 'basic authorization' => [
            fn () => new RoadRunnerRequest(headers: [
                'Authorization' => ['Basic '.base64_encode('user:pass')],
            ]),
            function (SymfonyRequest $r) {
                $this->assertSame('user', $r->getUser());
                $this->assertSame('pass', $r->getPassword());
            },
        ];

        yield 'upload error' => [
            fn () => new RoadRunnerRequest(uploads: [
                'error' => $this->createUploadedFile('', \UPLOAD_ERR_CANT_WRITE, 'error.txt', 'plain/text'),
            ]),
            fn (SymfonyRequest $request) => $this->assertSame(\UPLOAD_ERR_CANT_WRITE, $request->files->get('error')->getError()),
        ];

        yield 'valid upload' => [
            fn () => new RoadRunnerRequest(uploads: [
                'doc1' => $this->createUploadedFile('Doc 1', \UPLOAD_ERR_OK, 'doc1.txt', 'text/plain'),
            ]),
            fn (SymfonyRequest $request) => $this->assertTrue($request->files->get('doc1')->isValid()),
        ];
    }

    /**
     * @dataProvider provideResponses
     *
     * @param Response|(\Closure(): Response)    $sfResponse
     * @param \Closure(RoadRunnerResponse): void $expectations
     */
    public function test_it_convert_symfony_response_to_roadrunner($sfResponse, \Closure $expectations)
    {
        $sfResponse = $sfResponse instanceof Response ? $sfResponse : $sfResponse();

        $innerWorker = new MockWorker();

        $responder = new HttpFoundationWorker\ChainResponder([
            new HttpFoundationWorker\ChunkedResponder([StreamedResponse::class, StreamedJsonResponse::class], 1024 * 16),
            new HttpFoundationWorker\ChunkedResponder([BinaryFileResponse::class], 1),
            new HttpFoundationWorker\ChunkedResponder([EventStreamResponse::class], 1),
            new HttpFoundationWorker\BufferedResponder(),
        ]);
        $worker = new HttpFoundationWorker($innerWorker, $responder);

        $worker->respond($sfResponse);

        $this->assertNotNull($innerWorker->responded);
        $expectations($innerWorker->responded);
        $this->assertTrue($innerWorker->responded->endOfStream);
    }

    public function provideResponses()
    {
        yield 'simple response' => [
            new Response(),
            function (RoadRunnerResponse $roadRunnerResponse) {
                $this->assertSame(200, $roadRunnerResponse->status);
                $this->assertSame('', $roadRunnerResponse->content);
                $this->assertCount(1, $roadRunnerResponse->chunks);
            },
        ];

        yield 'response with content' => [
            new Response('Hello world'),
            function (RoadRunnerResponse $response) {
                $this->assertSame('Hello world', $response->content);
                $this->assertCount(1, $response->chunks);
            },
        ];

        yield 'non 200 status code' => [
            new Response('', 404),
            fn (RoadRunnerResponse $response) => $this->assertSame(404, $response->status),
        ];

        yield 'binary file response' => [
            fn () => (new BinaryFileResponse($this->createFile('binary-response.txt', 'hello')))
                ->setChunkSize(2),
            function (RoadRunnerResponse $roadRunnerResponse) {
                $this->assertSame(200, $roadRunnerResponse->status);
                $this->assertSame('hello', $roadRunnerResponse->content);
                $this->assertCount(4, $roadRunnerResponse->chunks);
                $this->assertSame(['he', 'll', 'o', ''], $roadRunnerResponse->chunks);
            },
        ];

        yield 'binary file response with content disposition' => [
            fn () => (new BinaryFileResponse($this->createFile('binary-response.txt', 'hello')))
                ->setContentDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'file.txt'),
            function (RoadRunnerResponse $response) {
                $this->assertSame(200, $response->status);
                $this->assertSame('hello', $response->content);
                $this->assertArrayHasKey('content-disposition', $response->headers);
                $this->assertEquals(['attachment; filename=file.txt'], $response->headers['content-disposition']);
            },
        ];

        yield 'streamed response' => [
            new StreamedResponse(function () {
                echo 'hello';
                echo ' ';
                echo 'world';
            }),
            function (RoadRunnerResponse $response) {
                $this->assertSame(200, $response->status);
                $this->assertSame('hello world', $response->content);
                $this->assertCount(1, $response->chunks);
                $this->assertSame(['hello world'], $response->chunks);
            },
        ];

        yield 'cookies' => [
            function () {
                $response = new Response();

                $response->headers->setCookie(Cookie::create('hello', 'world'));

                return $response;
            },
            function (RoadRunnerResponse $response) {
                $this->assertSame(200, $response->status);
                $this->assertArrayHasKey('set-cookie', $response->headers);
                $this->assertEquals(['hello=world; path=/; httponly; samesite=lax'], $response->headers['set-cookie']);
            },
        ];

        yield 'non string headers' => [
            new Response('', 200, [
                'Foo' => 1234,
                'Bar' => new class {
                    public function __toString(): string
                    {
                        return 'bar';
                    }
                },
            ]),
            function (RoadRunnerResponse $response) {
                $this->assertSame(200, $response->status);
                $this->assertArrayHasKey('foo', $response->headers);
                $this->assertArrayHasKey('bar', $response->headers);
                $this->assertEquals(['bar'], $response->headers['bar']);
                $this->assertEquals(['1234'], $response->headers['foo']);
            },
        ];
    }

    /**
     * @dataProvider provideStreamedResponses
     *
     * @param StreamedResponse|(\Closure(): StreamedResponse) $sfResponse
     * @param non-negative-int                                $chunkSize
     * @param \Closure(RoadRunnerResponse): void              $expectations
     */
    public function test_it_convert_symfony_streamed_response_to_roadrunner(
        StreamedResponse|\Closure $sfResponse,
        int $chunkSize,
        \Closure $expectations,
    ): void {
        $sfResponse = $sfResponse instanceof StreamedResponse ? $sfResponse : $sfResponse();

        $innerWorker = new MockWorker();
        $responder = new HttpFoundationWorker\ChunkedResponder([StreamedResponse::class, StreamedJsonResponse::class], $chunkSize);
        $worker = new HttpFoundationWorker($innerWorker, $responder);
        $worker->respond($sfResponse);

        $this->assertNotNull($innerWorker->responded);
        $expectations($innerWorker->responded);
        $this->assertTrue($innerWorker->responded->endOfStream);
    }

    public function provideStreamedResponses(): iterable
    {
        yield 'streamed response from callback' => [
            new StreamedResponse(function () {
                echo 'foo';
                echo ' ';
                echo 'bar baz';
                echo ' ';
                echo 'qux';
            }),
            2,
            function (RoadRunnerResponse $response): void {
                $this->assertSame(200, $response->status);
                $this->assertSame('foo bar baz qux', $response->content);
                $this->assertCount(4, $response->chunks);
                $this->assertSame(['foo', ' bar baz', ' qux', ''], $response->chunks);
            },
        ];

        yield 'streamed response from callback 2' => [
            new StreamedResponse(function () {
                echo 'foo';
                echo ' ';
                echo 'bar baz';
                echo ' ';
                echo 'qux';
            }),
            4,
            function (RoadRunnerResponse $response): void {
                $this->assertSame(200, $response->status);
                $this->assertSame('foo bar baz qux', $response->content);
                $this->assertCount(4, $response->chunks);
                $this->assertSame(['foo ', 'bar baz', ' qux', ''], $response->chunks);
            },
        ];

        yield 'streamed response from callback 3' => [
            new StreamedResponse(function () {
                echo 'foo';
                echo ' ';
                echo 'bar baz';
                echo ' ';
                echo 'qux';
            }),
            1024 * 16,
            function (RoadRunnerResponse $response): void {
                $this->assertSame(200, $response->status);
                $this->assertSame('foo bar baz qux', $response->content);
                $this->assertCount(1, $response->chunks);
                $this->assertSame(['foo bar baz qux'], $response->chunks);
            },
        ];

        yield 'streamed response from chunks' => [
            new StreamedResponse([
                'foo',
                ' ',
                'bar baz',
                ' ',
                'qux',
            ]),
            2,
            function (RoadRunnerResponse $response): void {
                $this->assertSame(200, $response->status);
                $this->assertSame('foo bar baz qux', $response->content);
                $this->assertCount(4, $response->chunks);
                $this->assertSame(['foo', ' bar baz', ' qux', ''], $response->chunks);
            },
        ];

        yield 'streamed response from chunks 2' => [
            new StreamedResponse([
                'foo',
                ' ',
                'bar baz',
                ' ',
                'qux',
            ]),
            4,
            function (RoadRunnerResponse $response): void {
                $this->assertSame(200, $response->status);
                $this->assertSame('foo bar baz qux', $response->content);
                $this->assertCount(4, $response->chunks);
                $this->assertSame(['foo ', 'bar baz', ' qux', ''], $response->chunks);
            },
        ];

        yield 'streamed response from chunks 3' => [
            new StreamedResponse([
                'foo',
                ' ',
                'bar baz',
                ' ',
                'qux',
            ]),
            1024 * 16,
            function (RoadRunnerResponse $response): void {
                $this->assertSame(200, $response->status);
                $this->assertSame('foo bar baz qux', $response->content);
                $this->assertCount(1, $response->chunks);
                $this->assertSame(['foo bar baz qux'], $response->chunks);
            },
        ];

        yield 'streamed json response array' => [
            function () {
                $data = [];

                for ($i = 0; $i < 3; ++$i) {
                    $data[] = ['id' => $i, 'data' => 'Some data value'];
                }

                return new StreamedJsonResponse($data);
            },
            10,
            function (RoadRunnerResponse $response): void {
                $this->assertSame(200, $response->status);
                $this->assertSame(
                    '[{"id":0,"data":"Some data value"},{"id":1,"data":"Some data value"},{"id":2,"data":"Some data value"}]',
                    $response->content,
                );
                $this->assertCount(2, $response->chunks);
                $this->assertSame(
                    [
                        '[{"id":0,"data":"Some data value"},{"id":1,"data":"Some data value"},{"id":2,"data":"Some data value"}]',
                        '',
                    ],
                    $response->chunks,
                );
            },
        ];

        yield 'streamed json response iterable' => [
            function () {
                $data = [];

                for ($i = 0; $i < 3; ++$i) {
                    $data[] = ['id' => $i, 'data' => 'Some data value'];
                }

                return new StreamedJsonResponse(new \ArrayObject($data));
            },
            10,
            function (RoadRunnerResponse $response): void {
                $this->assertSame(200, $response->status);
                $this->assertSame(
                    '[{"id":0,"data":"Some data value"},{"id":1,"data":"Some data value"},{"id":2,"data":"Some data value"}]',
                    $response->content,
                );
                $this->assertCount(4, $response->chunks);
                $this->assertSame(
                    [
                        '[{"id":0,"data":"Some data value"}',
                        ',{"id":1,"data":"Some data value"}',
                        ',{"id":2,"data":"Some data value"}',
                        ']',
                    ],
                    $response->chunks,
                );
            },
        ];
    }

    public function test_it_overrides_SERVER_global()
    {
        $rrRequest = new RoadRunnerRequest(
            remoteAddr: '10.0.0.2',
            uri: 'https://localhost/foo/bar?hello=world',
            headers: [
                'Hello-World-Header' => ['World'],
            ]
        );

        $innerWorker = new MockWorker();
        $innerWorker->nextRequest = $rrRequest;

        $worker = new HttpFoundationWorker($innerWorker, new HttpFoundationWorker\BufferedResponder());
        $symfonyRequest = $worker->waitRequest();

        $this->assertSame('10.0.0.2', $symfonyRequest->server->get('REMOTE_ADDR'));
        $this->assertSame('10.0.0.2', $_SERVER['REMOTE_ADDR']);

        $this->assertSame('/foo/bar?hello=world', $symfonyRequest->server->get('REQUEST_URI'));
        $this->assertSame('/foo/bar?hello=world', $_SERVER['REQUEST_URI']);

        $this->assertSame('World', $_SERVER['HTTP_HELLO_WORLD_HEADER']);

        $rrRequest = new RoadRunnerRequest();
        $innerWorker->nextRequest = $rrRequest;
        $newSymfonyRequest = $worker->waitRequest();

        $this->assertArrayNotHasKey('HTTP_HELLO_WORLD_HEADER', $_SERVER);
    }

    private function createUploadedFile(string $content, int $error, string $clientFileName, string $clientMediaType)
    {
        $tmpPath = $this->createFile($clientFileName, $content);

        return [
            'name' => $clientFileName,
            'error' => $error,
            'tmpName' => $tmpPath,
            'size' => filesize($tmpPath),
            'mime' => $clientFileName,
        ];
    }

    private function createFile(string $path, string $content): string
    {
        $tmpPath = self::$vfs->url().'/'.$path;

        file_put_contents($tmpPath, $content);

        return $tmpPath;
    }
}

/**
 * @internal
 */
final class RoadRunnerResponse
{
    public int $status;
    public string $content;
    public array $headers;
    public bool $endOfStream;
    /** @var list<string> */
    public array $chunks;

    public function __construct(int $status, string $content, array $headers, bool $endOfStream)
    {
        $this->status = $status;
        $this->content = $content;
        $this->headers = $headers;
        $this->endOfStream = $endOfStream;
        $this->chunks[] = $content;
    }

    public function withChunk(string $chunk, bool $endOfStream): self
    {
        $response = new self(
            status: $this->status,
            content: $this->content.$chunk,
            headers: $this->headers,
            endOfStream: $endOfStream,
        );
        $response->chunks = array_merge($this->chunks, [$chunk]);

        return $response;
    }
}

class MockWorker implements HttpWorkerInterface
{
    public ?RoadRunnerResponse $responded = null;
    public ?RoadRunnerRequest $nextRequest = null;

    public function waitRequest(): ?RoadRunnerRequest
    {
        $req = $this->nextRequest;
        $this->nextRequest = null;

        return $req;
    }

    public function respond(int $status, string|\Generator $body, array $headers = [], bool $endOfStream = true): void
    {
        if ($body instanceof \Generator) {
            throw new \LogicException('Generator body not supported.');
        }

        if (null === $this->responded || true === $this->responded->endOfStream) {
            $this->responded = new RoadRunnerResponse($status, $body, $headers, $endOfStream);
        } else {
            $this->responded = $this->responded->withChunk($body, $endOfStream);
        }
    }

    public function getWorker(): WorkerInterface
    {
        throw new \LogicException('Not implemented');
    }
}
