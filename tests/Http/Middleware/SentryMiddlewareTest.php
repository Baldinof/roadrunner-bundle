<?php

namespace Tests\Baldinof\RoadRunnerBundle\Http\Middleware;

use function Baldinof\RoadRunnerBundle\consumes;
use Baldinof\RoadRunnerBundle\Helpers\SentryHelper;
use Baldinof\RoadRunnerBundle\Http\Middleware\SentryMiddleware;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
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
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\Transport\TransportFactoryInterface;
use Sentry\Transport\TransportInterface;

/**
 * Test are mostly a copy from the Sentry RequestIntegration test suite
 * adapted to the middleware case.
 */
final class SentryMiddlewareTest extends TestCase
{
    /**
     * @var \SplStack
     */
    public static $collectedEvents;

    public function setUp(): void
    {
        $this->handler = new class() implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                SentrySdk::getCurrentHub()->captureMessage('Oops, there was an error');

                return new Response();
            }
        };
    }

    public function getHub(array $options)
    {
        static::$collectedEvents = new \SplStack();

        $client = (new ClientBuilder(new Options(array_merge($options, ['default_integrations' => false]))))
            ->setTransportFactory($this->getTransportFactoryMock())
            ->getClient();

        $hub = new Hub($client);

        SentrySdk::setCurrentHub($hub);

        return $hub;
    }

    /**
     * @dataProvider applyToEventDataProvider
     */
    public function testApplyToEvent(array $options, ServerRequestInterface $request, array $expectedResult): void
    {
        $middleware = new SentryMiddleware($this->getHub($options));

        consumes($middleware->process($request, $this->handler));

        $this->assertCount(1, static::$collectedEvents);

        $event = static::$collectedEvents->pop();

        $this->assertEquals($expectedResult, $event->getRequest());
    }

    public function applyToEventDataProvider(): \Generator
    {
        yield [
            [
                'send_default_pii' => true,
            ],
            (new ServerRequest('GET', new Uri('http://www.example.com/foo')))
                ->withCookieParams(['foo' => 'bar']),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'GET',
                'cookies' => [
                    'foo' => 'bar',
                ],
                'headers' => [
                    'Host' => ['www.example.com'],
                ],
            ],
        ];

        yield [
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

        yield [
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

        yield [
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

        yield [
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

        yield [
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
                'headers' => [
                    'Host' => ['www.example.com'],
                ],
            ],
        ];

        yield [
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

        yield [
            [
                'max_request_body_size' => 'small',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withParsedBody([
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ])
                ->withBody($this->getStreamMock(10 ** 3)),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                ],
                'data' => [
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ],
            ],
        ];

        yield [
            [
                'max_request_body_size' => 'small',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withParsedBody([
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ])
                ->withBody($this->getStreamMock(10 ** 3 + 1)),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                ],
            ],
        ];

        yield [
            [
                'max_request_body_size' => 'medium',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withParsedBody([
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ])
                ->withBody($this->getStreamMock(10 ** 4)),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                ],
                'data' => [
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ],
            ],
        ];

        yield [
            [
                'max_request_body_size' => 'medium',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withParsedBody([
                    'foo' => 'foo value',
                    'bar' => 'bar value',
                ])
                ->withBody($this->getStreamMock(10 ** 4 + 1)),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                ],
            ],
        ];

        yield [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withUploadedFiles([
                    'foo' => new UploadedFile('foo content', 123, UPLOAD_ERR_OK, 'foo.ext', 'application/text'),
                ]),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
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

        yield [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withUploadedFiles([
                    'foo' => [
                        new UploadedFile('foo content', 123, UPLOAD_ERR_OK, 'foo.ext', 'application/text'),
                        new UploadedFile('bar content', 321, UPLOAD_ERR_OK, 'bar.ext', 'application/octet-stream'),
                    ],
                ]),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
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

        yield [
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
                ]),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
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

        yield [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->getStreamMock(13, '{"foo":"bar"}')),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Type' => ['application/json'],
                ],
                'data' => [
                    'foo' => 'bar',
                ],
            ],
        ];

        yield [
            [
                'max_request_body_size' => 'always',
            ],
            (new ServerRequest('POST', new Uri('http://www.example.com/foo')))
                ->withHeader('Content-Type', 'application/json')
                ->withBody($this->getStreamMock(1, '{')),
            [
                'url' => 'http://www.example.com/foo',
                'method' => 'POST',
                'headers' => [
                    'Host' => ['www.example.com'],
                    'Content-Type' => ['application/json'],
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
        $hub = $this->getHub($options);
        $middleware = new SentryMiddleware($hub);
        $hub->addBreadcrumb(new Breadcrumb('info', 'default', 'category', 'Contamination from previous requests'));

        consumes($middleware->process($request, $this->handler));

        $event = static::$collectedEvents->pop();
        $this->assertEmpty($event->getBreadCrumbs());
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

    private function getTransportFactoryMock()
    {
        return SentryHelper::isVersion3() ? $this->getTransportFactoryMockForSdkVersion3() : $this->getTransportFactoryMockForSdkVersion2();
    }

    private function getTransportFactoryMockForSdkVersion2()
    {
        return new class() implements TransportFactoryInterface {
            public function create(Options $options): TransportInterface
            {
                return new class() implements TransportInterface {
                    public function send(Event $event): ?string
                    {
                        SentryMiddlewareTest::$collectedEvents->push($event);

                        return $event->getId();
                    }
                };
            }
        };
    }

    private function getTransportFactoryMockForSdkVersion3()
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
