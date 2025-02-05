<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\Grpc;

use Baldinof\RoadRunnerBundle\Grpc\GrpcRequestHandlerInterface;
use Baldinof\RoadRunnerBundle\Grpc\InterceptorInterface;
use Baldinof\RoadRunnerBundle\Grpc\InterceptorStack;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequest;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequestInvokerInterface;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Spiral\RoadRunner\GRPC\Context;
use Spiral\RoadRunner\GRPC\Method;

use function Baldinof\RoadRunnerBundle\consumes;

class InterceptorStackTest extends TestCase
{
    use ProphecyTrait;

    public static string $out = '';

    public function setUp(): void
    {
        self::$out = '';
    }

    public function test_it_calls_interceptors_in_expected_order()
    {
        $stack = new InterceptorStack(new class() implements GrpcRequestHandlerInterface {
            public function handle(GrpcRequest $request): \Iterator
            {
                InterceptorStackTest::$out .= "Main handler\n";

                yield '';

                InterceptorStackTest::$out .= "Terminated main handler\n";
            }
        });

        $stack->pipe($this->interceptor('1'));
        $stack->pipe($this->interceptor('2'));
        $stack->pipe($this->interceptor('3'));

        $gen = $stack->handle($this->request());

        $gen->current();

        $this->assertEquals(<<<TXT
        Before 1
        Before 2
        Before 3
        Main handler
        After 3
        After 2
        After 1
        TXT, $this->getOut());

        consumes($gen);

        $this->assertEquals(<<<TXT
        Terminated main handler
        Terminated 3
        Terminated 2
        Terminated 1
        TXT, $this->getOut());
    }

    public function test_it_works_with_psr_handler()
    {
        $handler = $this->handler($response = 'Hello');

        $stack = new InterceptorStack($handler);
        $gen = $stack->handle($this->request());

        $this->assertSame($response, $gen->current());
    }

    public function test_an_interceptor_can_modify_the_response()
    {
        $handler = $this->handler($response = 'Hello');

        $stack = new InterceptorStack($handler);
        $stack->pipe(new class() implements InterceptorInterface {
            public function intercept(GrpcRequest $invocation, GrpcRequestInvokerInterface $next): \Iterator
            {
                $response = $next->invoke($invocation);
                $response .= ' World!';

                yield $response;
            }
        });

        $response = $stack->handle($this->request())->current();
        $this->assertIsString($response);
        $this->assertEquals('Hello World!', $response);
    }

    private function request(): GrpcRequest
    {
        return new GrpcRequest(
            new FakeGrpcService(),
            Method::parse((new \ReflectionClass(FakeGrpcService::class))->getMethod('fake')),
            new Context([]),
            ''
        );
    }

    private function interceptor(string $name): InterceptorInterface
    {
        return new class($name) implements InterceptorInterface {
            public function __construct(private $name)
            {
            }

            public function intercept(GrpcRequest $invocation, GrpcRequestInvokerInterface $next): \Iterator
            {
                InterceptorStackTest::$out .= "Before {$this->name}\n";
                $res = $next->invoke($invocation);
                InterceptorStackTest::$out .= "After {$this->name}\n";

                yield $res;

                InterceptorStackTest::$out .= "Terminated {$this->name}\n";
            }
        };
    }

    private function handler(string $response): GrpcRequestHandlerInterface
    {
        return new class($response) implements GrpcRequestHandlerInterface {
            public function __construct(private string $response)
            {
            }

            public function handle(GrpcRequest $request): \Iterator
            {
                yield $this->response;
            }
        };
    }

    private function getOut(): string
    {
        $out = self::$out;
        self::$out = '';

        return trim($out);
    }
}
