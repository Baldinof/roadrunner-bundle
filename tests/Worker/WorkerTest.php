<?php

namespace Tests\Baldinof\RoadRunnerBundle\Worker;

use Baldinof\RoadRunnerBundle\Event\WorkerExceptionEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerStopEvent;
use Baldinof\RoadRunnerBundle\Http\IteratorRequestHandlerInterface;
use Baldinof\RoadRunnerBundle\Reboot\KernelRebootStrategyInterface;
use Baldinof\RoadRunnerBundle\Worker\Configuration;
use Baldinof\RoadRunnerBundle\Worker\Dependencies;
use Baldinof\RoadRunnerBundle\Worker\Worker;
use Iterator;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\NullLogger;
use Spiral\RoadRunner\PSR7Client;
use Spiral\RoadRunner\Worker as RoadrunnerWorker;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\RebootableInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

class WorkerTest extends TestCase
{
    use ProphecyTrait;

    public static $rebootStrategyReturns = false;

    private $worker;
    private $requests;
    private $responder;

    /**
     * @var EventDispatcher
     */
    private $eventDispatcher;

    public function setUp(): void
    {
        $this->requests = $requests = new \SplStack();

        $this->roadrunnerWorker = $this->prophesize(RoadrunnerWorker::class);

        $this->psrClient = $this->prophesize(PSR7Client::class);
        $this->psrClient->acceptRequest()->will(function () use ($requests) {
            return $requests->isEmpty() ? null : $requests->pop();
        });

        $this->psrClient->getWorker()->willReturn($this->roadrunnerWorker->reveal());

        $psrFactory = new Psr17Factory();

        $this->eventDispatcher = new EventDispatcher();
        $this->kernel = $this->prophesize(KernelInterface::class)
            ->willImplement(TerminableInterface::class)
            ->willImplement(RebootableInterface::class);

        $this->kernel->isDebug()->willReturn(false);
        $this->kernel->boot()->willReturn(null);
        $this->kernel->getContainer()->willReturn($c = new Container());

        $handler = function ($request) {
            if (!is_callable($this->responder)) {
                $this->fail('Unexpected call on the request handler');
            }

            yield from ($this->responder)($request);
        };

        $this->handler = new class($handler) implements IteratorRequestHandlerInterface {
            private $handler;

            public function __construct(callable $handler)
            {
                $this->handler = $handler;
            }

            public function handle(ServerRequestInterface $request): Iterator
            {
                yield from ($this->handler)($request);
            }
        };

        $c->set(Dependencies::class, new Dependencies($this->handler, new class() implements KernelRebootStrategyInterface {
            public function shouldReboot(): bool
            {
                return WorkerTest::$rebootStrategyReturns;
            }

            public function clear(): void
            {
                WorkerTest::$rebootStrategyReturns = false;
            }
        }));

        $this->worker = new Worker(
            $this->kernel->reveal(),
            $this->eventDispatcher,
            new Configuration(false),
            $this->handler,
            new NullLogger(),
            $this->psrClient->reveal()
        );
    }

    public function test_it_setup_trusted_proxies_and_hosts()
    {
        $_ENV['TRUSTED_PROXIES'] = '10.0.0.1,10.0.0.2';
        $_ENV['TRUSTED_HOSTS'] = 'example.org,example.com';

        $this->worker->start();

        $this->assertSame(['10.0.0.1', '10.0.0.2'], Request::getTrustedProxies());
        $this->assertSame(['{example.org}i', '{example.com}i'], Request::getTrustedHosts());
        $this->assertSame(Request::HEADER_X_FORWARDED_ALL ^ Request::HEADER_X_FORWARDED_HOST, Request::getTrustedHeaderSet());
    }

    /**
     * @testWith [ "Baldinof\\RoadRunnerBundle\\Event\\WorkerStartEvent" ]
     *           [ "Baldinof\\RoadRunnerBundle\\Event\\WorkerStopEvent" ]
     */
    public function test_it_dispatches_events($eventName)
    {
        $called = false;
        $this->eventDispatcher->addListener($eventName, function () use (&$called) {
            $called = true;
        });

        $this->worker->start();

        $this->assertTrue($called);
    }

    public function test_it_calls_the_handler()
    {
        // Force re-throw caught exception.
        $this->eventDispatcher->addListener(WorkerExceptionEvent::class, function (WorkerExceptionEvent $e) {
            throw $e->getException();
        });

        $this->requests->push(new ServerRequest('GET', 'http://example.org/'));

        $terminated = false;
        $this->responder = function () use (&$terminated) {
            yield new Response(200, [], 'hello');

            $terminated = true;
        };

        $psrClientCalled = false;
        $this->psrClient->respond(Argument::any())
            ->shouldBeCalled()
            ->will(function (array $args) use (&$terminated, &$psrClientCalled) {
                $psrResponse = $args[0];

                Assert::assertInstanceOf(ResponseInterface::class, $psrResponse);
                Assert::assertSame('hello', (string) $psrResponse->getBody());
                Assert::assertFalse($terminated);
                $psrClientCalled = true;
            });

        $this->worker->start();

        $this->assertTrue($terminated);
        $this->assertTrue($psrClientCalled, 'PSR Client seems to not have been called.');
    }

    public function test_an_error_stops_the_worker()
    {
        $this->requests->push(new ServerRequest('GET', 'http://example.org/'));
        $this->requests->push(new ServerRequest('GET', 'http://example.org/'));

        $this->responder = function () {
            throw new \RuntimeException('error');
        };

        $called = false;
        $this->eventDispatcher->addListener(WorkerStopEvent::class, function () use (&$called) {
            $called = true;
        });

        $this->roadrunnerWorker->error('Internal server error')->shouldBeCalled();
        $this->roadrunnerWorker->stop()->shouldBeCalled();

        $this->worker->start();

        $this->assertTrue($called, WorkerStopEvent::class.' has not been dispatched');
    }

    public function test_an_error_in_debug_mode_shows_the_trace_and_stops_the_worker()
    {
        $this->requests->push(new ServerRequest('GET', 'http://example.org/'));
        $this->requests->push(new ServerRequest('GET', 'http://example.org/'));

        $this->kernel->isDebug()->willReturn(true);

        $this->responder = function () {
            throw new \RuntimeException('error in debug');
        };

        $called = false;
        $this->eventDispatcher->addListener(WorkerStopEvent::class, function () use (&$called) {
            $called = true;
        });

        $this->roadrunnerWorker->stop()->shouldBeCalled();

        $this->roadrunnerWorker->error(Argument::type('string'))
            ->shouldBeCalled()
            ->will(function (array $args) {
                Assert::assertStringContainsString('error in debug', $args[0]);
            });

        $this->worker->start();

        $this->assertTrue($called, WorkerStopEvent::class.' has not been dispatched');
    }

    public function test_it_reboot_the_kernel_according_to_the_strategy()
    {
        $this->responder = function () use (&$terminated) {
            yield new Response(200, [], 'hello');

            $terminated = true;
        };

        $this->psrClient->respond(Argument::any()); // Allow resond() calls

        $this->requests->push(new ServerRequest('GET', 'http://example.org/'));

        self::$rebootStrategyReturns = false;
        $this->worker->start();

        $this->kernel->reboot()->shouldNotHaveBeenCalled();

        $this->requests->push(new ServerRequest('GET', 'http://example.org/'));
        self::$rebootStrategyReturns = true;
        $this->worker->start();

        $this->kernel->reboot(null)->shouldHaveBeenCalled();
    }
}
