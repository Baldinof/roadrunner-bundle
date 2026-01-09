<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\Integration\Xdebug;

use Baldinof\RoadRunnerBundle\Integration\Xdebug\XdebugProxy;
use Baldinof\RoadRunnerBundle\Integration\Xdebug\XdebugTriggerMiddleware;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequest;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequestInvokerInterface;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Spiral\RoadRunner\GRPC\Context;
use Spiral\RoadRunner\GRPC\Method;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpFoundation\Response as HttpResponse;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Tests\Baldinof\RoadRunnerBundle\Grpc\FakeGrpcService;

use function Baldinof\RoadRunnerBundle\consumes;

class XdebugTriggerMiddlewareTest extends TestCase
{
    private MockObject&XdebugProxy $xdebug;
    private MockObject&HttpKernelInterface $nextMiddleware;
    private MockObject&GrpcRequestInvokerInterface $nextInterceptor;
    private XdebugTriggerMiddleware $middleware;

    #[\Override]
    protected function setUp(): void
    {
        $this->xdebug = $this->createMock(XdebugProxy::class);
        $this->middleware = new XdebugTriggerMiddleware($this->xdebug);

        $this->nextMiddleware = $this->createMock(HttpKernelInterface::class);
        $this->nextInterceptor = $this->createMock(GrpcRequestInvokerInterface::class);
    }

    public function testMiddlewareDoesNothingWhenXdebugExtensionIsNotLoaded(): void
    {
        $this->xdebug->method('isExtensionLoaded')->willReturn(false);
        $this->xdebug->expects($this->never())->method('notify');
        $this->xdebug->expects($this->never())->method('connectToClient');
        $this->expectsNextMiddlewareCalled();
        consumes($this->middleware->process(new HttpRequest(), $this->nextMiddleware));
    }

    public function testInterceptorDoesNothingWhenXdebugExtensionIsNotLoaded(): void
    {
        $this->xdebug->method('isExtensionLoaded')->willReturn(false);
        $this->xdebug->expects($this->never())->method('notify');
        $this->xdebug->expects($this->never())->method('connectToClient');
        $this->expectsNextIteratorCalled();
        consumes($this->middleware->intercept($this->grpcRequest(), $this->nextInterceptor));
    }

    public function testMiddlewareDoesNothingWhenXdebugStartWithRequestIsNo(): void
    {
        $this->xdebug->method('isExtensionLoaded')->willReturn(true);
        $this->xdebug->method('startWithRequest')->willReturn('no');
        $this->xdebug->expects($this->never())->method('notify');
        $this->xdebug->expects($this->never())->method('connectToClient');
        $this->expectsNextMiddlewareCalled();
        consumes($this->middleware->process(new HttpRequest(), $this->nextMiddleware));
    }

    public function testInterceptorDoesNothingWhenXdebugStartWithRequestIsNo(): void
    {
        $this->xdebug->method('isExtensionLoaded')->willReturn(true);
        $this->xdebug->method('startWithRequest')->willReturn('no');
        $this->xdebug->expects($this->never())->method('notify');
        $this->xdebug->expects($this->never())->method('connectToClient');
        $this->expectsNextIteratorCalled();
        consumes($this->middleware->intercept($this->grpcRequest(), $this->nextInterceptor));
    }

    public function testMiddlewareTriggersWhenXdebugStartWithRequestIsYes(): void
    {
        $this->xdebug->method('isExtensionLoaded')->willReturn(true);
        $this->xdebug->method('startWithRequest')->willReturn('yes');
        $this->xdebug->expects($this->exactly(2))->method('notify');
        $this->xdebug->expects($this->once())->method('connectToClient');
        $this->expectsNextMiddlewareCalled();
        consumes($this->middleware->process(new HttpRequest(), $this->nextMiddleware));
    }

    public function testInterceptorTriggersWhenXdebugStartWithRequestIsYes(): void
    {
        $this->xdebug->method('isExtensionLoaded')->willReturn(true);
        $this->xdebug->method('startWithRequest')->willReturn('yes');
        $this->xdebug->expects($this->exactly(2))->method('notify');
        $this->xdebug->expects($this->once())->method('connectToClient');
        $this->expectsNextIteratorCalled();
        consumes($this->middleware->intercept($this->grpcRequest(), $this->nextInterceptor));
    }

    public function testMiddlewareDoesNothingWhenXdebugStartWithRequestIsTriggerWithNoTrigger(): void
    {
        $this->xdebug->method('isExtensionLoaded')->willReturn(true);
        $this->xdebug->method('startWithRequest')->willReturn('trigger');
        $this->xdebug->expects($this->never())->method('notify');
        $this->xdebug->expects($this->never())->method('connectToClient');
        $this->expectsNextMiddlewareCalled();
        consumes($this->middleware->process(new HttpRequest(), $this->nextMiddleware));
    }

    public function testInterceptorDoesNothingWhenXdebugStartWithRequestIsTriggerWithNoTrigger(): void
    {
        $this->xdebug->method('isExtensionLoaded')->willReturn(true);
        $this->xdebug->method('startWithRequest')->willReturn('trigger');
        $this->xdebug->expects($this->never())->method('notify');
        $this->xdebug->expects($this->never())->method('connectToClient');
        $this->expectsNextIteratorCalled();
        consumes($this->middleware->intercept($this->grpcRequest(), $this->nextInterceptor));
    }

    /**
     * @dataProvider provideRequestsWithTrigger
     */
    public function testMiddlewareTriggersWhenXdebugStartWithRequestITriggerWithTrigger(HttpRequest $request, bool $hasTriggerValue, ?string $specificMode): void
    {
        $this->xdebug->method('isExtensionLoaded')->willReturn(true);
        $this->xdebug->method('startWithRequest')->willReturn('trigger');
        $this->xdebug->method('triggerValue')->willReturn($hasTriggerValue ? 'rr-debug' : null);
        if ($specificMode) {
            $this->xdebug->method('hasMode')->with($specificMode)->willReturn(true);
        } else {
            $this->xdebug->expects($this->never())->method('hasMode');
        }
        $this->xdebug->expects($this->exactly(2))->method('notify');
        $this->xdebug->expects($this->once())->method('connectToClient');
        $this->expectsNextMiddlewareCalled();
        consumes($this->middleware->process($request, $this->nextMiddleware));
    }

    /**
     * @dataProvider provideRequestsWithTrigger
     */
    public function testMiddlewareTriggersWhenXdebugStartWithRequestITriggerWithWrongTrigger(HttpRequest $request, bool $hasTriggerValue, ?string $specificMode): void
    {
        if (false === $hasTriggerValue && null === $specificMode) {
            // This case is not to be tested with this test.
            // Make one assertion, and return to skip it with no warning.
            // (It's better than duplicating the provider)
            $this->assertTrue(true);

            return;
        }
        $this->xdebug->method('isExtensionLoaded')->willReturn(true);
        $this->xdebug->method('startWithRequest')->willReturn('trigger');
        $this->xdebug->method('triggerValue')->willReturn($hasTriggerValue ? 'rr-is-awesome' : null);
        if ($specificMode) {
            $this->xdebug->method('hasMode')->with($specificMode)->willReturn(false);
        } else {
            $this->xdebug->expects($this->never())->method('hasMode');
        }
        $this->xdebug->expects($this->never())->method('notify');
        $this->xdebug->expects($this->never())->method('connectToClient');
        $this->expectsNextMiddlewareCalled();
        consumes($this->middleware->process($request, $this->nextMiddleware));
    }

    public static function provideRequestsWithTrigger(): \Iterator
    {
        yield 'XDEBUG_TRIGGER (any value)' => [self::httpRequest('XDEBUG_TRIGGER'), false, null];
        yield 'XDEBUG_TRIGGER (specific value)' => [self::httpRequest('XDEBUG_TRIGGER', 'rr-debug'), true, null];
        yield 'XDEBUG_SESSION (any value)' => [self::httpRequest('XDEBUG_SESSION'), false, 'debug'];
        yield 'XDEBUG_SESSION (specific value)' => [self::httpRequest('XDEBUG_SESSION', 'rr-debug'), true, 'debug'];
        yield 'XDEBUG_SESSION_START (any value)' => [self::httpRequest('XDEBUG_SESSION_START'), false, 'debug'];
        yield 'XDEBUG_SESSION_START (specific value)' => [self::httpRequest('XDEBUG_SESSION_START', 'rr-debug'), true, 'debug'];
        yield 'XDEBUG_PROFILE (any value)' => [self::httpRequest('XDEBUG_PROFILE'), false, 'profile'];
        yield 'XDEBUG_PROFILE (specific value)' => [self::httpRequest('XDEBUG_PROFILE', 'rr-debug'), true, 'profile'];
        yield 'XDEBUG_TRACE (any value)' => [self::httpRequest('XDEBUG_TRACE'), false, 'trace'];
        yield 'XDEBUG_TRACE (specific value)' => [self::httpRequest('XDEBUG_TRACE', 'rr-debug'), true, 'trace'];
    }

    public function testInterceptorTriggersWhenXdebugStartWithRequestIsTriggerWithTrigger(): void
    {
        $this->xdebug->method('isExtensionLoaded')->willReturn(true);
        $this->xdebug->method('startWithRequest')->willReturn('yes');
        $this->xdebug->expects($this->exactly(2))->method('notify');
        $this->xdebug->expects($this->once())->method('connectToClient');
        $this->expectsNextIteratorCalled();
        consumes($this->middleware->intercept($this->grpcRequest(), $this->nextInterceptor));
    }

    private function expectsNextMiddlewareCalled(?HttpResponse $response = null): void
    {
        $this->nextMiddleware->expects($this->once())->method('handle')->willReturn($response ?? new HttpResponse());
    }

    private function expectsNextIteratorCalled(?string $response = null): void
    {
        $this->nextInterceptor->expects($this->once())->method('invoke')->willReturn($response ?? '');
    }

    private static function httpRequest(string $trigger, ?string $value = null): HttpRequest
    {
        // Randomize query, request and cookies to test all cases
        return match (random_int(0, 2)) {
            default => new HttpRequest(query: [$trigger => $value ?? '1']),
            1 => new HttpRequest(request: [$trigger => $value ?? '1']),
            2 => new HttpRequest(cookies: [$trigger => $value ?? '1']),
        };
    }

    /**
     * @param array<string, mixed> $context
     */
    private static function grpcRequest(array $context = []): GrpcRequest
    {
        return new GrpcRequest(
            new FakeGrpcService(),
            Method::parse((new \ReflectionClass(FakeGrpcService::class))->getMethod('fake')),
            new Context($context),
            ''
        );
    }
}
