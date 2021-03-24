<?php

namespace Tests\Baldinof\RoadRunnerBundle\Http\Middleware;

use function Baldinof\RoadRunnerBundle\consumes;
use Baldinof\RoadRunnerBundle\Http\Middleware\SentryMiddleware;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\Transport\TransportFactoryInterface;
use Sentry\Transport\TransportInterface;
use SplStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * Test cases are mostly a copy from the Sentry RequestIntegration test suite.
 */
final class SentryMiddlewareTest extends TestCase
{
    public static SplStack $collectedEvents;

    public \Closure $onRequest;

    private HttpKernelInterface $handler;

    public function setUp(): void
    {
        $this->onRequest = function () {
            SentrySdk::getCurrentHub()->captureMessage('Oops, there was an error');

            return new Response();
        };

        $this->handler = new class($this) implements HttpKernelInterface {
            private SentryMiddlewareTest $test;

            public function __construct(SentryMiddlewareTest $test)
            {
                $this->test = $test;
            }

            public function handle(Request $request, int $type = self::MASTER_REQUEST, bool $catch = true)
            {
                return ($this->test->onRequest)($request);
            }
        };
    }

    public function initHub(array $options): void
    {
        self::$collectedEvents = new \SplStack();

        $opts = new Options(array_merge($options, ['default_integrations' => true]));

        $client = (new ClientBuilder($opts))
            ->setTransportFactory($this->getTransportFactoryMock())
            ->getClient();

        $hub = new Hub($client);

        SentrySdk::setCurrentHub($hub);
    }

    public function testClearsScopeFromPreviousRequestContamination(): void
    {
        $request = Request::create('http://www.example.com/foo', 'POST', [], [], [], [], '{}');
        $request->headers->set('Content-Type', 'application/json');
        $options = [
            'max_request_body_size' => 'always',
        ];

        $this->initHub($options);

        $middleware = new SentryMiddleware(SentrySdk::getCurrentHub());
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

    private function getTransportFactoryMock(): TransportFactoryInterface
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
