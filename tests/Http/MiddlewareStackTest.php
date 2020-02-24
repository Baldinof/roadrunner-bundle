<?php

namespace Tests\Baldinof\RoadRunnerBundle\Http;

use function Baldinof\RoadRunnerBundle\consumes;
use Baldinof\RoadRunnerBundle\Http\IteratorMiddlewareInterface;
use Baldinof\RoadRunnerBundle\Http\IteratorRequestHandlerInterface;
use Baldinof\RoadRunnerBundle\Http\MiddlewareStack;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Prophecy\Argument;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MiddlewareStackTest extends TestCase
{
    public static $out = '';

    public function setUp(): void
    {
        self::$out = '';
    }

    public function test_it_calls_middlewares_in_expected_order()
    {
        $stack = new MiddlewareStack(new class() implements IteratorRequestHandlerInterface {
            public function handle(ServerRequestInterface $request): \Iterator
            {
                MiddlewareStackTest::$out .= "Main handler\n";

                yield new Response();

                MiddlewareStackTest::$out .= "Terminated main handler\n";
            }
        });

        $stack->pipe($this->middleware('1'));
        $stack->pipe($this->middleware('2'));
        $stack->pipe($this->middleware('3'));

        $gen = $stack->handle(new ServerRequest('POST', 'http://example.org'));

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
        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::any())->willReturn($response = new Response());

        $stack = new MiddlewareStack($handler->reveal());
        $gen = $stack->handle(new ServerRequest('GET', 'https://example.org'));

        $this->assertSame($response, $gen->current());
    }

    public function test_a_middleware_can_modify_the_response()
    {
        $handler = $this->prophesize(RequestHandlerInterface::class);
        $handler->handle(Argument::any())->willReturn($response = new Response());

        $stack = new MiddlewareStack($handler->reveal());
        $stack->pipe(new class() implements MiddlewareInterface {
            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
            {
                return $handler->handle($request)->withHeader('X-Test', 'foo');
            }
        });

        $response = $stack->handle(new ServerRequest('GET', 'https://example.org'))->current();
        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertEquals('foo', $response->getHeaderLine('X-Test'));
    }

    private function middleware(string $name): IteratorMiddlewareInterface
    {
        $m = new class() implements IteratorMiddlewareInterface {
            public $name;

            public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): \Iterator
            {
                MiddlewareStackTest::$out .= "Before {$this->name}\n";
                $res = $handler->handle($request);
                MiddlewareStackTest::$out .= "After {$this->name}\n";

                yield $res;

                MiddlewareStackTest::$out .= "Terminated {$this->name}\n";
            }
        };

        $m->name = $name;

        return $m;
    }

    private function getOut(): string
    {
        $out = self::$out;
        self::$out = '';

        return trim($out);
    }
}
