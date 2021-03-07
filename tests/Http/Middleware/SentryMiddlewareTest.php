<?php

namespace Tests\Baldinof\RoadRunnerBundle\Http\Middleware;

use function Baldinof\RoadRunnerBundle\consumes;
use Baldinof\RoadRunnerBundle\Helpers\SentryRequestFetcher;
use Baldinof\RoadRunnerBundle\Http\Middleware\SentryMiddleware;
use Composer\InstalledVersions;
use Composer\Semver\VersionParser;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use Nyholm\Psr7\UploadedFile;
use Nyholm\Psr7\Uri;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Sentry\Breadcrumb;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Integration\RequestIntegration;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\Transport\TransportFactoryInterface;
use Sentry\Transport\TransportInterface;
use SplStack;

/**
 * Test cases are mostly a copy from the Sentry RequestIntegration test suite.
 */
final class SentryMiddlewareTest extends TestCase
{
    public static SplStack $collectedEvents;

    public \Closure $onRequest;

    private SentryRequestFetcher $requestFetcher;
    private RequestHandlerInterface $handler;

    public function setUp(): void
    {
        $this->requestFetcher = new SentryRequestFetcher();

        $this->onRequest = function () {
            SentrySdk::getCurrentHub()->captureMessage('Oops, there was an error');

            return new Response();
        };

        $this->handler = new class($this) implements RequestHandlerInterface {
            private SentryMiddlewareTest $test;

            public function __construct(SentryMiddlewareTest $test)
            {
                $this->test = $test;
            }

            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                return ($this->test->onRequest)($request);
            }
        };
    }

    public function initHub(array $options): void
    {
        self::$collectedEvents = new \SplStack();

        $opts = new Options(array_merge($options, ['default_integrations' => true]));
        $opts->setIntegrations([
            new RequestIntegration($this->requestFetcher),
        ]);

        $client = (new ClientBuilder($opts))
            ->setTransportFactory($this->getTransportFactoryMock())
            ->getClient();

        $hub = new Hub($client);

        SentrySdk::setCurrentHub($hub);
    }

    /**
     * @dataProvider applyToEventDataProvider
     */
    public function testApplyToEvent(array $options, ServerRequestInterface $request, array $expectedResult): void
    {
        $this->initHub($options);

        $middleware = new SentryMiddleware($this->requestFetcher, SentrySdk::getCurrentHub());

        consumes($middleware->process($request, $this->handler));

        $this->assertCount(1, static::$collectedEvents);

        $event = static::$collectedEvents->pop();

        $this->assertEquals($expectedResult, $event->getRequest());
    }

    public function applyToEventDataProvider(): \Generator
    {
//        yield 'send_default_pii => true' => [
//            [
//                'send_default_pii' => true,
//            ],
//            (new ServerRequest('GET', new Uri('http://www.example.com/foo')))
//                ->withCookieParams(['foo' => 'bar']),
//            [
//                'url' => 'http://www.example.com/foo',
//                'method' => 'GET',
//                'cookies' => [
//                    'foo' => 'bar',
//                ],
//                'headers' => [
//                    'Host' => ['www.example.com'],
//                ],
//            ],
//        ];

        yield 'send_default_pii => false' => [
            [
                'send_default_pii' => false,
            ],
            (new ServerRequest('GET', new Uri('http://www.example.com/foo')))
                ->withCookieParams(['foo' => 'bar']),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'GET',
                'headers' => [
                    'Host' => ['www.example.com'],
                ],
            ],
        ];

        yield 'non 80 port, send_default_pii => true' => [
            [
                'send_default_pii' => true,
            ],
            (new ServerRequest('GET', new Uri('http://www.example.com:1234/foo'))),
            [
                'url' => 'http://www.example.com:1234/foo',
                'method' => 'GET',
                'cookies' => [],
                'headers' => [
                    'Host' => ['www.example.com:1234'],
                ],
            ],
        ];

        yield 'non 80 port, send_default_pii => false' => [
            [
                'send_default_pii' => false,
            ],
            (new ServerRequest('GET', new Uri('http://www.example.com:1234/foo'))),
            [
                'url' => 'http://www.example.com:1234/foo',
                'method' => 'GET',
                'headers' => [
                    'Host' => ['www.example.com:1234'],
                ],
            ],
        ];

        yield 'with headers & cookies, send_default_pii => true' => [
            [
                'send_default_pii' => true,
            ],
            (new ServerRequest('GET', new Uri('http://www.example.com/foo?foo=bar&bar=baz'), [], null, '1.1', ['REMOTE_ADDR' => '127.0.0.1']))
                ->withHeader('Host', 'www.example.com')
                ->withHeader('Authorization', 'foo')
                ->withHeader('Cookie', 'bar')
                ->withHeader('Set-Cookie', 'baz'),
            [
                'url' => 'http://www.example.com/foo?foo=bar&bar=baz',
                'method' => 'GET',
                'query_string' => 'foo=bar&bar=baz',
                'cookies' => [],
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Authorization' => ['foo'],
                    'Cookie' => ['bar'],
                    'Set-Cookie' => ['baz'],
                ],
                'env' => [
                    'REMOTE_ADDR' => '127.0.0.1',
                ],
            ],
        ];

        if (InstalledVersions::satisfies(new VersionParser(), 'sentry/sentry', '>=3.2.0')) {
            // sentry > 3.2 exports a sanitized version of risky headers
            $headers = [
                'Host' => ['www.example.com'],
                'Cookie' => ['[Filtered]'],
                'REMOTE_ADDR' => ['127.0.0.1'],
                'Authorization' => ['[Filtered]'],
                'Set-Cookie' => ['[Filtered]'],
            ];
        } else {
            $headers = ['Host' => ['www.example.com']];
        }

        yield 'with headers & cookies, send_default_pii => false' => [
            [
                'send_default_pii' => false,
            ],
            (new ServerRequest('GET', new Uri('http://www.example.com/foo?foo=bar&bar=baz'), ['REMOTE_ADDR' => '127.0.0.1']))
                ->withHeader('Host', 'www.example.com')
                ->withHeader('Authorization', 'foo')
                ->withHeader('Cookie', 'bar')
                ->withHeader('Set-Cookie', 'baz'),
            [
                'url' => 'http://www.example.com/foo?foo=bar&bar=baz',
                'method' => 'GET',
                'query_string' => 'foo=bar&bar=baz',
                'headers' => $headers,
            ],
        ];

        yield 'removed body' => [
            [
                'max_request_body_size' => 'none',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withParsedBody([
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ])
                ->withBody($this->getStreamMock(1)),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                ],
            ],
        ];

        yield 'small body that fits in the limit' => [
            [
                'max_request_body_size' => 'small',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withParsedBody([
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ])
                ->withAddedHeader('content-length', 10 ** 3)
                ->withBody($this->getStreamMock(10 ** 3)),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                    'content-length' => [10 ** 3],
                ],
                'data' => [
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ],
            ],
        ];

        yield 'small body that does not fit in the limit' => [
            [
                'max_request_body_size' => 'small',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withParsedBody([
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ])
                ->withAddedHeader('Content-Length', 10 ** 3 + 1)
                ->withBody($this->getStreamMock(10 ** 3 + 1)),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Length' => [10 ** 3 + 1],
                ],
            ],
        ];

        yield 'medium body that fits in the limit' => [
            [
                'max_request_body_size' => 'medium',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withParsedBody([
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ])
                ->withAddedHeader('Content-Length', 10 ** 4)
                ->withBody($this->getStreamMock(10 ** 4)),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Length' => [10 ** 4],
                ],
                'data' => [
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ],
            ],
        ];

        yield 'medium body that does not fit in the limit' => [
            [
                'max_request_body_size' => 'medium',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withParsedBody([
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ])
                ->withAddedHeader('Content-Length', 10 ** 4 + 1)
                ->withBody($this->getStreamMock(10 ** 4 + 1)),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Length' => [10 ** 4 + 1],
                ],
            ],
        ];

        yield 'uploaded file' => [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withUploadedFiles([
                    'foo' => new UploadedFile('foo content', 123, UPLOAD_ERR_OK, 'foo.ext', 'application/text'),
                ])
                ->withAddedHeader('Content-Length', 1),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Length' => [1],
                ],
                'data' => [
                    'foo' => [
                        'client_filename' => 'foo.ext',
                        'client_media_type' => 'application/text',
                        'size' => 123,
                    ],
                ],
            ],
        ];

        yield 'uploaded files' => [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withUploadedFiles([
                    'foo' => [
                        new UploadedFile('foo content', 123, UPLOAD_ERR_OK, 'foo.ext', 'application/text'),
                        new UploadedFile('bar content', 321, UPLOAD_ERR_OK, 'bar.ext', 'application/octet-stream'),
                    ],
                ])
                ->withAddedHeader('Content-Length', 1),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Length' => [1],
                ],
                'data' => [
                    'foo' => [
                        [
                            'client_filename' => 'foo.ext',
                            'client_media_type' => 'application/text',
                            'size' => 123,
                        ],
                        [
                            'client_filename' => 'bar.ext',
                            'client_media_type' => 'application/octet-stream',
                            'size' => 321,
                        ],
                    ],
                ],
            ],
        ];

        yield 'nested uploaded files' => [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withUploadedFiles([
                    'foo' => [
                        'bar' => [
                            new UploadedFile('foo content', 123, UPLOAD_ERR_OK, 'foo.ext', 'application/text'),
                            new UploadedFile('bar content', 321, UPLOAD_ERR_OK, 'bar.ext', 'application/octet-stream'),
                        ],
                    ],
                ])
                ->withAddedHeader('Content-Length', 1),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Length' => [1],
                ],
                'data' => [
                    'foo' => [
                        'bar' => [
                            [
                                'client_filename' => 'foo.ext',
                                'client_media_type' => 'application/text',
                                'size' => 123,
                            ],
                            [
                                'client_filename' => 'bar.ext',
                                'client_media_type' => 'application/octet-stream',
                                'size' => 321,
                            ],
                        ],
                    ],
                ],
            ],
        ];

        yield 'json body' => [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withHeader('Content-Type', 'application/json')
                ->withAddedHeader('Content-Length', 13)
                ->withBody($this->streamFor('{"foo":"bar"}')),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Type' => ['application/json'],
                    'Content-Length' => [13],
                ],
                'data' => [
                    'foo' => 'bar',
                ],
            ],
        ];

        yield 'invalid json' => [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withHeader('Content-Type', 'application/json')
                ->withHeader('Content-Length', 1)
                ->withBody($this->streamFor('{')),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Type' => ['application/json'],
                    'Content-Length' => [1],
                ],
                'data' => '{',
            ],
        ];
    }

    public function testClearsScopeFromPreviousRequestContamination(): void
    {
        $request = (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
            ->withHeader('Content-Type', 'application/json')
            ->withBody($this->getStreamMock(0, ''));
        $options = [
            'max_request_body_size' => 'always',
        ];

        $this->initHub($options);

        $middleware = new SentryMiddleware($this->requestFetcher, SentrySdk::getCurrentHub());
        $this->onRequest = function () {
            $hub = SentrySdk::getCurrentHub();
            $hub->addBreadcrumb(new Breadcrumb('info', 'default', 'category', 'Contamination from previous requests'));
            $hub->captureMessage('Oops, there was an error');

            return new Response();
        };

        consumes($middleware->process($request, $this->handler)); // First request added a breadcrumb

        $this->onRequest = function () {
            $hub = SentrySdk::getCurrentHub();
            $hub->captureMessage('Oops, there was an error');

            return new Response();
        };

        consumes($middleware->process($request, $this->handler)); // // No breadcrumb added

        $event = static::$collectedEvents->pop();
        $this->assertEquals([], $event->getBreadCrumbs());
    }

    private function getStreamMock(int $size, string $content = ''): StreamInterface
    {
        /** @var MockObject|StreamInterface $stream */
        $stream = $this->createMock(StreamInterface::class);
        $stream->expects($this->any())
            ->method('getSize')
            ->willReturn($size);

        $stream->expects($this->any())
            ->method('getContents')
            ->willReturn($content);

        return $stream;
    }

    private function streamFor(string $content): StreamInterface
    {
        $stream = (new Psr17Factory())->createStream($content);
        $stream->rewind();

        return $stream;
    }

    private function getTransportFactoryMock()
    {
        return new class() implements TransportFactoryInterface {
            public function create(Options $options): TransportInterface
            {
                return new class() implements TransportInterface {
                    public function send(Event $event): PromiseInterface
                    {
                        SentryMiddlewareTest::$collectedEvents->push($event);

                        return new Promise(function () use ($event) {
                            return $event->getId();
                        }, null);
                    }

                    public function close(?int $timeout = null): PromiseInterface
                    {
                        return new Promise(null, null);
                    }
                };
            }
        };
    }
}
