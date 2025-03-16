<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\Integration\Sentry;

use Baldinof\RoadRunnerBundle\Integration\Sentry\SentryMiddleware;
use GuzzleHttp\Promise\Promise;
use GuzzleHttp\Promise\PromiseInterface;
use PHPUnit\Framework\TestCase;
use Sentry\Breadcrumb;
use Sentry\Client;
use Sentry\ClientBuilder;
use Sentry\Event;
use Sentry\Options;
use Sentry\SentrySdk;
use Sentry\State\Hub;
use Sentry\Transport\Result;
use Sentry\Transport\ResultStatus;
use Sentry\Transport\TransportFactoryInterface;
use Sentry\Transport\TransportInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tests\Baldinof\RoadRunnerBundle\Utils\CallableHttpKernel;

use function Baldinof\RoadRunnerBundle\consumes;

final class SentryMiddlewareTest extends TestCase
{
    public static \SplStack $collectedEvents;

    /** @var \Closure(): Response */
    public \Closure $onRequest;

    private HttpKernelInterface $handler;

    public function setUp(): void
    {
        $this->onRequest = function () {
            SentrySdk::getCurrentHub()->captureMessage('Oops, there was an error');

            return new Response();
        };

        $this->handler = new CallableHttpKernel(fn ($req) => ($this->onRequest)($req));
    }

    public function initHub(array $options): void
    {
        self::$collectedEvents = new \SplStack();

        $opts = new Options(array_merge($options, ['default_integrations' => true]));

        $builder = new ClientBuilder($opts);
        if (version_compare(Client::SDK_VERSION, '4.0.0', '<')) {
            $builder->setTransportFactory($this->getTransportFactoryMock());
        } else {
            $builder->setTransport($this->getV4TransportMock());
        }
        $client = $builder->getClient();

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
        return new class implements TransportFactoryInterface {
            public function create(Options $options): TransportInterface
            {
                return new class implements TransportInterface {
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

    private function getV4TransportMock(): TransportInterface
    {
        return new class implements TransportInterface {
            public function send(Event $event): Result
            {
                SentryMiddlewareTest::$collectedEvents->push($event);

                return new Result(ResultStatus::success(), $event);
            }

            public function close(?int $timeout = null): Result
            {
                return new Result(ResultStatus::success());
            }
        };
    }
}
