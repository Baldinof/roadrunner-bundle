<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\Worker;

use Baldinof\RoadRunnerBundle\Event\WorkerExceptionEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerKernelRebootedEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerStopEvent;
use Baldinof\RoadRunnerBundle\Grpc\GrpcRequestHandlerInterface;
use Baldinof\RoadRunnerBundle\Grpc\GrpcServiceProvider;
use Baldinof\RoadRunnerBundle\Grpc\InterceptorStack;
use Baldinof\RoadRunnerBundle\Reboot\KernelRebootStrategyInterface;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequest;
use Baldinof\RoadRunnerBundle\Worker\GrpcDependencies;
use Baldinof\RoadRunnerBundle\Worker\GrpcInvoker;
use Baldinof\RoadRunnerBundle\Worker\GrpcWorker;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\NullLogger;
use Spiral\RoadRunner\GRPC\Internal\Json;
use Spiral\RoadRunner\Payload;
use Spiral\RoadRunner\WorkerInterface as RoadrunnerWorker;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\RebootableInterface;
use Tests\Baldinof\RoadRunnerBundle\Grpc\FakeGrpcService;

#[\AllowDynamicProperties]
class GrpcWorkerTest extends TestCase
{
    use ProphecyTrait;

    public static $rebootStrategyReturns = false;

    private GrpcWorker $worker;
    private EventDispatcher $eventDispatcher;
    private Container $container;
    private bool $isDebug = false;

    public function setUp(): void
    {
        $this->requests = $requests = new \SplStack();

        $this->roadrunnerWorker = $this->prophesize(RoadrunnerWorker::class);
        $this->roadrunnerWorker->waitPayload()->will(fn () => $requests->isEmpty() ? null : $requests->pop());
        $this->roadrunnerWorker->respond(Argument::any());

        $this->grpcServiceProvider = new GrpcServiceProvider();
        $this->service = new FakeGrpcService();
        $this->grpcServiceProvider->registerService(FakeGrpcService::class, $this->service);

        $handler = function ($request) {
            if (!isset($this->responder)) {
                $this->fail('Unexpected call on the request handler');
            }

            yield from ($this->responder)($request);
        };

        $this->handler = new class($handler) implements GrpcRequestHandlerInterface {
            private $handler;

            public function __construct(callable $handler)
            {
                $this->handler = $handler;
            }

            public function handle(GrpcRequest $request): \Iterator
            {
                yield from ($this->handler)($request);
            }
        };

        $kernelBootStrategyClass = new class() implements KernelRebootStrategyInterface {
            public function shouldReboot(): bool
            {
                return GrpcWorkerTest::$rebootStrategyReturns;
            }

            public function clear(): void
            {
                GrpcWorkerTest::$rebootStrategyReturns = false;
            }
        };

        $this->eventDispatcher = new EventDispatcher();

        $this->kernel = $this->prophesize(KernelInterface::class)
            ->willImplement(RebootableInterface::class);
        $this->kernel->isDebug()->willReturn($this->isDebug);
        $this->kernel->boot()->willReturn(null);
        $this->kernel->getContainer()->willReturn($c = new Container());

        $this->container = $c;

        $c->set(GrpcDependencies::class, new GrpcDependencies(new InterceptorStack($this->handler), $kernelBootStrategyClass, $this->eventDispatcher));

        $this->invoker = new GrpcInvoker(
            $this->kernel->reveal(),
            new NullLogger(),
            $this->roadrunnerWorker->reveal(),
        );

        $this->worker = new GrpcWorker(
            new NullLogger(),
            $this->roadrunnerWorker->reveal(),
            $this->grpcServiceProvider,
            $this->invoker,
        );
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

        $this->requests->push(new Payload('', Json::encode([
            'service' => FakeGrpcService::NAME,
            'method' => 'fake',
            'context' => [],
        ])));

        $terminated = false;
        $this->responder = function () use (&$terminated) {
            yield 'hello';

            $terminated = true;
        };

        $grpcInvokerCalled = false;
        $this->roadrunnerWorker->respond(Argument::any())
            ->shouldBeCalled()
            ->will(function (array $args) use (&$terminated, &$grpcInvokerCalled) {
                $response = $args[0];

                Assert::assertInstanceOf(Payload::class, $response);
                Assert::assertSame('hello', $response->body);
                Assert::assertFalse($terminated);
                $grpcInvokerCalled = true;
            });

        $this->worker->start();

        $this->assertTrue($grpcInvokerCalled, 'gRPC Client seems to not have been called.');
        $this->assertTrue($terminated, 'responder chain has not been consumed entirely.');
    }

    public function test_an_error_stops_the_worker()
    {
        $this->requests->push(new Payload('', Json::encode([
            'service' => FakeGrpcService::NAME,
            'method' => 'fake',
            'context' => [],
        ])));

        $this->requests->push(new Payload('', Json::encode([
            'service' => FakeGrpcService::NAME,
            'method' => 'fake',
            'context' => [],
        ])));

        $this->responder = function () {
            throw new \RuntimeException('should not be displayed');
        };

        $called = false;
        $this->eventDispatcher->addListener(WorkerStopEvent::class, function () use (&$called) {
            $called = true;
        });

        $this->roadrunnerWorker->error(Argument::type('string'))
            ->shouldBeCalled();
        $this->roadrunnerWorker->stop()->shouldBeCalled();

        $this->worker->start();

        $this->assertTrue($called, WorkerStopEvent::class.' has not been dispatched');
    }

    public function test_it_reboot_the_kernel_according_to_the_strategy()
    {
        $this->responder = function () use (&$terminated) {
            yield 'hello';

            $terminated = true;
        };

        $this->roadrunnerWorker->respond(Argument::any()); // Allow respond() calls

        $this->requests->push(new Payload('', Json::encode([
            'service' => FakeGrpcService::NAME,
            'method' => 'fake',
            'context' => [],
        ])));

        self::$rebootStrategyReturns = false;
        $this->worker->start();

        $this->kernel->reboot()->shouldNotHaveBeenCalled();

        $this->requests->push(new Payload('', Json::encode([
            'service' => FakeGrpcService::NAME,
            'method' => 'fake',
            'context' => [],
        ])));
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
