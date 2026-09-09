<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\Worker;

use Baldinof\RoadRunnerBundle\Event\WorkerExceptionEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerKernelRebootedEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerStopEvent;
use Baldinof\RoadRunnerBundle\Grpc\GrpcExceptionPolicy;
use Baldinof\RoadRunnerBundle\Grpc\GrpcExceptionPolicyInterface;
use Baldinof\RoadRunnerBundle\Grpc\GrpcRequestHandlerInterface;
use Baldinof\RoadRunnerBundle\Grpc\GrpcServiceProvider;
use Baldinof\RoadRunnerBundle\Grpc\InterceptorStack;
use Baldinof\RoadRunnerBundle\Reboot\KernelRebootStrategyInterface;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequest;
use Baldinof\RoadRunnerBundle\Worker\GrpcDependencies;
use Baldinof\RoadRunnerBundle\Worker\GrpcInvoker;
use Baldinof\RoadRunnerBundle\Worker\GrpcWorker;
use Google\Rpc\Status;
use PHPUnit\Framework\Assert;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Spiral\RoadRunner\GRPC\Exception\GRPCException;
use Spiral\RoadRunner\GRPC\Exception\InvokeException;
use Spiral\RoadRunner\GRPC\Internal\Json;
use Spiral\RoadRunner\GRPC\StatusCode;
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
        self::$rebootStrategyReturns = false;
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

        $kernelBootStrategyClass = new class implements KernelRebootStrategyInterface {
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
        $this->kernel->getContainer()->willReturn($c = new Container());

        $this->container = $c;

        $c->set(GrpcDependencies::class, new GrpcDependencies(new InterceptorStack($this->handler), $kernelBootStrategyClass, $this->eventDispatcher, new GrpcExceptionPolicy()));

        $this->logger = $this->prophesize(LoggerInterface::class);
        $this->logger->error(Argument::any(), Argument::any());
        $this->createWorker(new GrpcExceptionPolicy());
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

    public function test_a_non_fatal_exception_preserves_the_response_and_worker_reuse(): void
    {
        $this->createWorker(new GrpcExceptionPolicy([GRPCException::class]));
        $this->logger->error(Argument::any(), Argument::any())->shouldNotBeCalled();
        $this->roadrunnerWorker->stop()->shouldNotBeCalled();
        $this->roadrunnerWorker->error(Argument::any())->shouldNotBeCalled();
        $this->eventDispatcher->addListener(WorkerExceptionEvent::class, function (): void {
            $this->fail('Non-fatal exceptions must not dispatch WorkerExceptionEvent.');
        });

        $resetter = $this->prophesize(\Symfony\Contracts\Service\ResetInterface::class);
        $resetter->reset()->shouldBeCalledTimes(2);
        $this->container->set('services_resetter', $resetter->reveal());
        $this->enqueueRequest();
        $this->enqueueRequest();

        $calls = 0;
        $terminated = false;
        $this->responder = function () use (&$calls, &$terminated) {
            if (++$calls === 1) {
                throw new GRPCException('Authentication required', StatusCode::UNAUTHENTICATED);
            }

            yield 'success';
            $terminated = true;
        };

        $responses = [];
        $this->roadrunnerWorker->respond(Argument::type(Payload::class))
            ->shouldBeCalledTimes(2)
            ->will(function (array $args) use (&$responses): void {
                $responses[] = $args[0];
            });

        $this->worker->start();

        $status = new Status();
        $status->mergeFromString(base64_decode(Json::decode($responses[0]->header)['error']));
        $this->assertSame(StatusCode::UNAUTHENTICATED, $status->getCode());
        $this->assertSame('Authentication required', $status->getMessage());
        $this->assertSame('', $responses[0]->body);
        $this->assertSame('success', $responses[1]->body);
        $this->assertTrue($terminated);
        $this->assertSame(2, $calls);
    }

    public function test_the_default_policy_escalates_grpc_exceptions(): void
    {
        $exception = new GRPCException('Authentication required', StatusCode::UNAUTHENTICATED);
        $caught = null;
        $this->eventDispatcher->addListener(WorkerExceptionEvent::class, static function (WorkerExceptionEvent $event) use (&$caught): void {
            $caught = $event->getException();
        });
        $this->logger->error(Argument::type('string'), ['throwable' => $exception])->shouldBeCalledOnce();
        $this->roadrunnerWorker->stop()->shouldBeCalledOnce();
        $resetter = $this->prophesize(\Symfony\Contracts\Service\ResetInterface::class);
        $resetter->reset()->shouldBeCalledOnce();
        $this->container->set('services_resetter', $resetter->reveal());

        $this->worker->finalizeInvocation($exception);

        $this->assertSame($exception, $caught);
    }

    public function test_invoke_exceptions_bypass_the_policy_and_still_finalize(): void
    {
        $policy = $this->prophesize(GrpcExceptionPolicyInterface::class);
        $policy->shouldEscalate(Argument::any())->shouldNotBeCalled();
        $this->createWorker($policy->reveal());
        $this->logger->error(Argument::any(), Argument::any())->shouldNotBeCalled();
        $this->roadrunnerWorker->stop()->shouldNotBeCalled();
        $resetter = $this->prophesize(\Symfony\Contracts\Service\ResetInterface::class);
        $resetter->reset()->shouldBeCalledOnce();
        $this->container->set('services_resetter', $resetter->reveal());

        $this->worker->finalizeInvocation(new InvokeException());
    }

    public function test_it_reloads_the_policy_after_a_non_fatal_exception_reboots_the_kernel(): void
    {
        $exception = new GRPCException('Authentication required', StatusCode::UNAUTHENTICATED);
        $initialPolicy = $this->prophesize(GrpcExceptionPolicyInterface::class);
        $initialPolicy->shouldEscalate($exception)->willReturn(false)->shouldBeCalledOnce();
        $this->createWorker($initialPolicy->reveal());

        $nextPolicy = $this->prophesize(GrpcExceptionPolicyInterface::class);
        $nextPolicy->shouldEscalate($exception)->willReturn(true)->shouldBeCalledOnce();
        $nextStrategy = $this->prophesize(KernelRebootStrategyInterface::class);
        $nextStrategy->shouldReboot()->willReturn(false)->shouldBeCalledOnce();
        $nextStrategy->clear()->shouldBeCalledTimes(2);
        $nextDispatcher = new EventDispatcher();
        $rebooted = false;
        $nextDispatcher->addListener(WorkerKernelRebootedEvent::class, static function () use (&$rebooted): void {
            $rebooted = true;
        });
        $nextContainer = new Container();
        $nextContainer->set(GrpcDependencies::class, new GrpcDependencies(
            new InterceptorStack($this->handler),
            $nextStrategy->reveal(),
            $nextDispatcher,
            $nextPolicy->reveal(),
        ));
        $kernel = $this->kernel;
        $kernel->reboot(null)->shouldBeCalledOnce()->will(static function () use ($kernel, $nextContainer): void {
            $kernel->getContainer()->willReturn($nextContainer);
        });
        $resetter = $this->prophesize(\Symfony\Contracts\Service\ResetInterface::class);
        $resetter->reset()->shouldBeCalledOnce();
        $this->container->set('services_resetter', $resetter->reveal());
        $nextResetter = $this->prophesize(\Symfony\Contracts\Service\ResetInterface::class);
        $nextResetter->reset()->shouldBeCalledOnce();
        $nextContainer->set('services_resetter', $nextResetter->reveal());
        $this->roadrunnerWorker->stop()->shouldBeCalledOnce();
        $this->logger->error(Argument::type('string'), ['throwable' => $exception])->shouldBeCalledOnce();
        self::$rebootStrategyReturns = true;

        $this->worker->finalizeInvocation($exception);
        $this->assertTrue($rebooted);
        $this->roadrunnerWorker->stop()->shouldNotHaveBeenCalled();
        $this->worker->finalizeInvocation($exception);
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

    public function test_it_resets_services_before_reboot(): void
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

        $resetter = $this->prophesize(\Symfony\Contracts\Service\ResetInterface::class);
        $resetter->reset()->shouldBeCalledOnce();
        $this->container->set('services_resetter', $resetter->reveal());

        self::$rebootStrategyReturns = true;

        $rebootedEventFired = false;
        $this->eventDispatcher->addListener(WorkerKernelRebootedEvent::class, function () use (&$rebootedEventFired) {
            $rebootedEventFired = true;
        });

        $this->worker->start();

        $this->kernel->reboot(null)->shouldHaveBeenCalled();
        $this->assertTrue($rebootedEventFired);
    }

    private function createWorker(GrpcExceptionPolicyInterface $policy): void
    {
        $dependencies = $this->container->get(GrpcDependencies::class);
        $this->container->set(GrpcDependencies::class, new GrpcDependencies(
            $dependencies->getRequestHandler(),
            $dependencies->getKernelRebootStrategy(),
            $this->eventDispatcher,
            $policy,
        ));

        $this->invoker = new GrpcInvoker(
            $this->kernel->reveal(),
            $this->logger->reveal(),
            $this->roadrunnerWorker->reveal(),
        );

        $this->worker = new GrpcWorker(
            new NullLogger(),
            $this->roadrunnerWorker->reveal(),
            $this->grpcServiceProvider,
            $this->invoker,
        );
    }

    private function enqueueRequest(): void
    {
        $this->requests->push(new Payload('', Json::encode([
            'service' => FakeGrpcService::NAME,
            'method' => 'fake',
            'context' => [],
        ])));
    }
}
