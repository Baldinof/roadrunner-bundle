<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\Grpc;

use Baldinof\RoadRunnerBundle\Grpc\InvocationHandler;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequest;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcTerminableInterface;
use PHPUnit\Framework\TestCase;
use Spiral\RoadRunner\GRPC\Context;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\InvokerInterface;
use Spiral\RoadRunner\GRPC\Method;
use Spiral\RoadRunner\GRPC\ServiceInterface;

use function Baldinof\RoadRunnerBundle\consumes;

class InvocationHandlerTest extends TestCase
{
    public function test_it_calls_the_kernel()
    {
        $invoker = $this->invoker(function (ServiceInterface $service, Method $method, ContextInterface $ctx, ?string $input): string {
            return 'hello';
        });
        $handler = $this->createHandler($invoker);

        $gen = $handler->handle(new GrpcRequest(
            new FakeGrpcService(),
            Method::parse((new \ReflectionClass(FakeGrpcService::class))->getMethod('fake')),
            new Context([]),
            ''
        ));
        $response = $gen->current();

        $this->assertIsString($response);
        $this->assertSame('hello', $response);
        $this->assertFalse($invoker->terminateCalled);

        consumes($gen);

        $this->assertTrue($invoker->terminateCalled);
    }

    private function createHandler(InvokerInterface $invoker): InvocationHandler
    {
        return new InvocationHandler($invoker);
    }

    private function invoker(\Closure $callback): InvokerInterface
    {
        return new class($callback) implements InvokerInterface, GrpcTerminableInterface {
            public $terminateCalled = false;

            public function __construct(
                private \Closure $callback,
            ) {
            }

            public function invoke(ServiceInterface $service, Method $method, ContextInterface $ctx, ?string $input): string
            {
                return ($this->callback)($service, $method, $ctx, $input);
            }

            public function terminate(GrpcRequest $request, string $response): void
            {
                $this->terminateCalled = true;
            }
        };
    }
}
