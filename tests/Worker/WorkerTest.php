<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\Worker;

use AllowDynamicProperties;
use Baldinof\RoadRunnerBundle\Event\WorkerExceptionEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerKernelRebootedEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerStopEvent;
use Baldinof\RoadRunnerBundle\Http\MiddlewareStack;
use Baldinof\RoadRunnerBundle\Http\RequestHandlerInterface;
use Baldinof\RoadRunnerBundle\Reboot\KernelRebootStrategyInterface;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\HttpFoundationWorkerInterface;
use Baldinof\RoadRunnerBundle\Worker\HttpDependencies;
use Baldinof\RoadRunnerBundle\Worker\HttpWorker;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Spiral\RoadRunner\WorkerInterface as RoadrunnerWorker;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\RebootableInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

#[\AllowDynamicProperties]
class WorkerTest extends TestCase
{
    use ProphecyTrait;

    public static $rebootStrategyReturns = false;

    private HttpWorker $worker;
    private \SplStack $requests;
    private \Closure $responder;

    private EventDispatcher $eventDispatcher;
    private Container $container;

    private bool $isDebug = false;

    public function setUp(): void
    {
        $this->requests = $requests = new \SplStack();

        $this->roadrunnerWorker = $this->prophesize(RoadrunnerWorker::class);

        $this->httpFoundationWorker = $this->prophesize(HttpFoundationWorkerInterface::class);
        $this->httpFoundationWorker->waitRequest()->will(fn () => $requests->isEmpty() ? null : $requests->pop());
        $this->httpFoundationWorker->respond(Argument::any());

        $this->httpFoundationWorker->getWorker()->willReturn($this->roadrunnerWorker->reveal());

        $this->eventDispatcher = new EventDispatcher();
        $this->kernel = $this->prophesize(KernelInterface::class)
            ->willImplement(TerminableInterface::class)
            ->willImplement(RebootableInterface::class);

        $this->kernel->isDebug()->willReturn($this->isDebug);
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

            public function handle(Request $request): \Iterator
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

        $this->container = $c;

        $c->set(HttpDependencies::class, new HttpDependencies(new MiddlewareStack($this->handler), $kernelBootStrategyClass, $this->eventDispatcher));

        $this->worker = new HttpWorker(
            $this->kernel->reveal(),
            new NullLogger(),
            $this->httpFoundationWorker->reveal()
        );
    }

    public function test_it_setup_trusted_proxies_and_hosts()
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.2';

        $this->container->setParameter('kernel.trusted_proxies', '10.0.0.1,REMOTE_ADDR');
        $this->container->setParameter('kernel.trusted_headers', Request::HEADER_FORWARDED);

        $worker = new HttpWorker(
            $this->kernel->reveal(),
            new NullLogger(),
            $this->httpFoundationWorker->reveal()
        );

        $this->requests->push(Request::create('http://example.org/'));

        $worker->start();

        $this->assertSame(['10.0.0.1', '10.0.0.2'], Request::getTrustedProxies());
        $this->assertSame(Request::HEADER_FORWARDED, Request::getTrustedHeaderSet());
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

        $httpFoundationWorkerCalled = false;
        $this->httpFoundationWorker->respond(Argument::any())
            ->shouldBeCalled()
            ->will(function (array $args) use (&$terminated, &$httpFoundationWorkerCalled) {
                $psrResponse = $args[0];

                Assert::assertInstanceOf(Response::class, $psrResponse);
                Assert::assertSame('hello', $psrResponse->getContent());
                Assert::assertFalse($terminated);
                $httpFoundationWorkerCalled = true;
            });

        $this->worker->start();

        $this->assertTrue($terminated);
        $this->assertTrue($httpFoundationWorkerCalled, 'PSR Client seems to not have been called.');
    }

    public function test_an_error_stops_the_worker()
    {
        $this->requests->push(Request::create('http://example.org/'));
        $this->requests->push(Request::create('http://example.org/'));

        $this->responder = function () {
            throw new \RuntimeException('should not be displayed');
        };

        $called = false;
        $this->eventDispatcher->addListener(WorkerStopEvent::class, function () use (&$called) {
            $called = true;
        });

        $this->httpFoundationWorker->respond(Argument::type(Response::class))
            ->shouldBeCalled()
            ->will(function (array $args) {
                Assert::assertStringNotContainsString('should not be displayed', $args[0]->getContent());
            });
        $this->roadrunnerWorker->stop()->shouldBeCalled();

        $this->worker->start();

        $this->assertTrue($called, WorkerStopEvent::class.' has not been dispatched');
    }

    public function test_an_error_in_debug_mode_shows_the_trace_and_stops_the_worker()
    {
        $this->isDebug = true;

        $this->setup(); // Debug kernel param is read only at work instantiation. `setup()` resets it.

        $this->requests->push(Request::create('http://example.org/'));
        $this->requests->push(Request::create('http://example.org/'));

        $this->responder = function () {
            throw new \RuntimeException('Should be displayed');
        };

        $called = false;
        $this->eventDispatcher->addListener(WorkerStopEvent::class, function () use (&$called) {
            $called = true;
        });

        $this->roadrunnerWorker->stop()->shouldBeCalled();
        $this->httpFoundationWorker->respond(Argument::type(Response::class))
            ->shouldBeCalled()
            ->will(function (array $args) {
                Assert::assertStringContainsString('Should be displayed', $args[0]->getContent());
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

        $this->httpFoundationWorker->respond(Argument::any()); // Allow resond() calls

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
