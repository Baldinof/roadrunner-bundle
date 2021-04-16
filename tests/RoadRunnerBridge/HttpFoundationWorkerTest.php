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
use Symfony\Component\HttpFoundation\HeaderUtils;
use Symfony\Component\HttpFoundation\Request as SymfonyRequest;
use Symfony\Component\HttpFoundation\Response;
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
        $rrRequest = new RoadRunnerRequest();
        $rrRequestConfigurator($rrRequest);

        $innerWorker = new MockWorker();
        $innerWorker->nextRequest = $rrRequest;

        $worker = new HttpFoundationWorker($innerWorker);
        $symfonyRequest = $worker->waitRequest();

        $expectations($symfonyRequest);
    }

    /** @noinspection PhpImmutablePropertyIsWrittenInspection */
    public function provideRequests()
    {
        yield 'full request' => [
            function (RoadRunnerRequest $rrRequest) {
                $rrRequest->method = 'GET';
                $rrRequest->uri = 'https://les-tilleuls.coop/about/kevin?page=1';
                $rrRequest->protocol = 'HTTP/1.1';
                $rrRequest->headers = [
                    'X-Dunglas-API-Platform' => ['1.0'],
                    'X-data' => ['a', 'b'],
                ];
                $rrRequest->query = ['page' => '1'];
                $rrRequest->body = json_encode(['country' => 'France']);
                $rrRequest->parsed = true;
                $rrRequest->cookies = ['city' => 'Lille'];
                $rrRequest->uploads = [
                    'doc1' => $this->createUploadedFile('Doc 1', \UPLOAD_ERR_OK, 'doc1.txt', 'text/plain'),
                    'nested' => [
                        'docs' => [
                            $this->createUploadedFile('Doc 2', \UPLOAD_ERR_OK, 'doc2.txt', 'text/plain'),
                            $this->createUploadedFile('Doc 3', \UPLOAD_ERR_OK, 'doc3.txt', 'text/plain'),
                        ],
                    ],
                ];
                $stdClass = new \stdClass();
                $rrRequest->attributes = ['custom' => $stdClass];
            },
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
            fn (RoadRunnerRequest $r) => $r->uri = 'https://les-tilleuls.coop:8443/about/kevin',
            fn (SymfonyRequest $r) => expect($r->getPort())->toBe(8443),
        ];

        yield 'https detection' => [
            fn (RoadRunnerRequest $r) => $r->uri = 'https://les-tilleuls.coop:8443/about/kevin',
            fn (SymfonyRequest $r) => expect($r->isSecure())->toBeTrue(),
        ];

        yield 'POST body not parsed' => [
            fn (RoadRunnerRequest $r) => $r->body = 'the body',
            fn (SymfonyRequest $r) => expect($r->getContent())->toBe('the body')
                    ->and($r->request->all())->toBeEmpty(),
        ];

        yield 'content-type & length added to $_SERVER' => [
            fn (RoadRunnerRequest $r) => $r->headers = [
                'content-type' => ['application/json'],
                'content-length' => ['42'],
            ],
            fn (SymfonyRequest $r) => expect($r->server->get('CONTENT_TYPE'))->toBe('application/json')
                    ->and($r->server->get('CONTENT_LENGTH'))->toBe('42'),
        ];

        yield 'basic authorization' => [
            fn (RoadRunnerRequest $request) => $request->headers = [
                'Authorization' => ['Basic '.base64_encode('user:pass')],
            ],
            fn (SymfonyRequest $r) => expect($r->getUser())->toBe('user')
                ->and($r->getPassword())->toBe('pass'),
        ];

        yield 'upload error' => [
            fn (RoadRunnerRequest $request) => $request->uploads = [
                'error' => $this->createUploadedFile('', \UPLOAD_ERR_CANT_WRITE, 'error.txt', 'plain/text'),
            ],
            fn (SymfonyRequest $request) => expect($request->files->get('error')->getError())
                ->toBe(\UPLOAD_ERR_CANT_WRITE),
        ];

        yield 'valid upload' => [
            fn (RoadRunnerRequest $request) => $request->uploads = [
                'doc1' => $this->createUploadedFile('Doc 1', \UPLOAD_ERR_OK, 'doc1.txt', 'text/plain'),
            ],
            fn (SymfonyRequest $request) => expect($request->files->get('doc1')->isValid())
                ->toBeTrue(),
        ];
    }

    /**
     * @dataProvider provideResponses
     *
     * @param Response|(\Closure(): Response)    $sfResponse
     * @param \Closure<RoadRunnerResponse>: void $expectations
     */
    public function test_it_convert_symfony_response_to_roadrunner($sfResponse, \Closure $expectations)
    {
        $sfResponse = $sfResponse instanceof Response ? $sfResponse : $sfResponse();

        $innerWorker = new MockWorker();

        $worker = new HttpFoundationWorker($innerWorker);

        $worker->respond($sfResponse);

        expect($innerWorker->responded)->not->toBeNull();
        $expectations($innerWorker->responded);
    }

    public function provideResponses()
    {
        yield 'simple response' => [
            new Response(),
            fn (RoadRunnerResponse $roadRunnerResponse) => expect($roadRunnerResponse)->toMatchObject([
                    'status' => 200,
                    'content' => '',
                ]),
        ];

        yield 'response with content' => [
            new Response('Hello world'),
            fn (RoadRunnerResponse $response) => expect($response->content)->toBe('Hello world'),
        ];

        yield 'non 200 status code' => [
            new Response('', 404),
            fn (RoadRunnerResponse $response) => expect($response->status)->toBe(404),
        ];

        yield 'binary file response' => [
            fn () => new BinaryFileResponse($this->createFile('binary-response.txt', 'hello')),
            fn (RoadRunnerResponse $response) => expect($response)
                ->toMatchObject([
                    'status' => 200,
                    'content' => 'hello',
                ]),
        ];

        yield 'binary file response with content disposition' => [
            fn () => (new BinaryFileResponse($this->createFile('binary-response.txt', 'hello')))
                ->setContentDisposition(HeaderUtils::DISPOSITION_ATTACHMENT, 'file.txt'),
            fn (RoadRunnerResponse $response) => expect($response)
                ->toMatchObject([
                    'status' => 200,
                    'content' => 'hello',
                ])
                ->and($response->headers)->toMatchArray([
                    'content-disposition' => ['attachment; filename=file.txt'],
                ]),
        ];

        yield 'streamed response' => [
            new StreamedResponse(function () {
                echo 'hello';
                echo ' ';
                echo 'world';
            }),
            fn (RoadRunnerResponse $response) => expect($response)
                ->toMatchObject([
                    'status' => 200,
                    'content' => 'hello world',
                ]),
        ];

        yield 'cookies' => [
            function () {
                $response = new Response();

                $response->headers->setCookie(Cookie::create('hello', 'world'));

                return $response;
            },
            fn (RoadRunnerResponse $response) => expect($response->headers)
                ->toMatchArray([
                    'set-cookie' => ['hello=world; path=/; httponly; samesite=lax'],
                ]),
        ];

        yield 'non string headers' => [
            new Response('', 200, [
                'Foo' => 1234,
                'Bar' => new class() {
                    public function __toString(): string
                    {
                        return 'bar';
                    }
                },
            ]),
            fn (RoadRunnerResponse $r) => expect($r->headers)
                ->toHaveKeys(['foo', 'bar'])
                ->and($r->headers['foo'])->toBe(['1234'])
                ->and($r->headers['bar'])->toBe(['bar']),
        ];
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

    public function __construct(int $status, string $content, array $headers)
    {
        $this->status = $status;
        $this->content = $content;
        $this->headers = $headers;
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

    public function respond(int $status, string $body, array $headers = []): void
    {
        $this->responded = new RoadRunnerResponse($status, $body, $headers);
    }

    public function getWorker(): WorkerInterface
    {
        throw new \Exception('Not implemented');
    }
}
