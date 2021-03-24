<?php

namespace Tests\Baldinof\RoadRunnerBundle\Worker;

use Baldinof\RoadRunnerBundle\Bridge\HttpFoundationWorkerInterface;
use Baldinof\RoadRunnerBundle\Event\WorkerExceptionEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerKernelRebootedEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerStopEvent;
use Baldinof\RoadRunnerBundle\Http\MiddlewareStack;
use Baldinof\RoadRunnerBundle\Http\RequestHandlerInterface;
use Baldinof\RoadRunnerBundle\Reboot\KernelRebootStrategyInterface;
use Baldinof\RoadRunnerBundle\Worker\Dependencies;
use Baldinof\RoadRunnerBundle\Worker\Worker;
use Iterator;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Spiral\RoadRunner\Worker as RoadrunnerWorker;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\RebootableInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

class WorkerTest extends TestCase
{
    use ProphecyTrait;

    public static $rebootStrategyReturns = false;

    private Worker $worker;
    private \SplStack $requests;
    private \Closure $responder;

    private EventDispatcher $eventDispatcher;

    public function setUp(): void
    {
        $this->requests = $requests = new \SplStack();

        $this->roadrunnerWorker = $this->prophesize(RoadrunnerWorker::class);

        $this->psrClient = $this->prophesize(HttpFoundationWorkerInterface::class);
        $this->psrClient->waitRequest()->will(fn () => $requests->isEmpty() ? null : $requests->pop());

        $this->psrClient->getWorker()->willReturn($this->roadrunnerWorker->reveal());

        $this->eventDispatcher = new EventDispatcher();
        $this->kernel = $this->prophesize(KernelInterface::class)
            ->willImplement(TerminableInterface::class)
            ->willImplement(RebootableInterface::class);

        $this->kernel->isDebug()->willReturn(false);
        $this->kernel->boot()->willReturn(null);
        $this->kernel->getContainer()->willReturn($c = new Container());

        $handler = function ($request) {
            if (!isset($this->responder)) {
                $this->fail('Unexpected call on the request handler');
            }

            yield from ($this->responder)($request);
        };

        $this->handler = new class($handler) implements RequestHandlerInterface {
            private $handler;

            public function __construct(callable $handler)
            {
                $this->handler = $handler;
            }

            public function handle(Request $request): Iterator
            {
                yield from ($this->handler)($request);
            }
        };

        $kernelBootStrategyClass = new class() implements KernelRebootStrategyInterface {
            public function shouldReboot(): bool
            {
                return WorkerTest::$rebootStrategyReturns;
            }

            public function clear(): void
            {
                WorkerTest::$rebootStrategyReturns = false;
            }
        };

        $c->set(Dependencies::class, $deps = new Dependencies(new MiddlewareStack($this->handler), $kernelBootStrategyClass, $this->eventDispatcher));

        $this->worker = new Worker(
            $this->kernel->reveal(),
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

        $this->requests->push(Request::create('http://example.org/'));

        $terminated = false;
        $this->responder = function () use (&$terminated) {
            yield new Response('hello');

            $terminated = true;
        };

        $psrClientCalled = false;
        $this->psrClient->respond(Argument::any())
            ->shouldBeCalled()
            ->will(function (array $args) use (&$terminated, &$psrClientCalled) {
                $psrResponse = $args[0];

                Assert::assertInstanceOf(Response::class, $psrResponse);
                Assert::assertSame('hello', $psrResponse->getContent());
                Assert::assertFalse($terminated);
                $psrClientCalled = true;
            });

        $this->worker->start();

        $this->assertTrue($terminated);
        $this->assertTrue($psrClientCalled, 'PSR Client seems to not have been called.');
    }

    public function test_an_error_stops_the_worker()
    {
        $this->requests->push(Request::create('http://example.org/'));
        $this->requests->push(Request::create('http://example.org/'));

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
        $this->requests->push(Request::create('http://example.org/'));
        $this->requests->push(Request::create('http://example.org/'));

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

        $this->requests->push(Request::create('http://example.org/'));

        self::$rebootStrategyReturns = false;
        $this->worker->start();

        $this->kernel->reboot()->shouldNotHaveBeenCalled();

        $this->requests->push(Request::create('http://example.org/'));
        self::$rebootStrategyReturns = true;

        $rebootedEventFired = false;

        $this->eventDispatcher->addListener(WorkerKernelRebootedEvent::class, function () use (&$rebootedEventFired) {
            $rebootedEventFired = true;
        });

        $this->worker->start();

        $this->kernel->reboot(null)->shouldHaveBeenCalled();
        $this->assertTrue($rebootedEventFired);
    }
}
