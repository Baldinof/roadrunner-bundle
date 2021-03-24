<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\Http;

use function Baldinof\RoadRunnerBundle\consumes;
use Baldinof\RoadRunnerBundle\Http\MiddlewareInterface;
use Baldinof\RoadRunnerBundle\Http\MiddlewareStack;
use Baldinof\RoadRunnerBundle\Http\RequestHandlerInterface;
use Iterator;
use PHPUnit\Framework\TestCase;
use Prophecy\PhpUnit\ProphecyTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class MiddlewareStackTest extends TestCase
{
    use ProphecyTrait;

    public static string $out = '';

    public function setUp(): void
    {
        self::$out = '';
    }

    public function test_it_calls_middlewares_in_expected_order()
    {
        $stack = new MiddlewareStack(new class() implements RequestHandlerInterface {
            public function handle(Request $request): Iterator
            {
                MiddlewareStackTest::$out .= "Main handler\n";

                yield new Response();

                MiddlewareStackTest::$out .= "Terminated main handler\n";
            }
        });

        $stack->pipe($this->middleware('1'));
        $stack->pipe($this->middleware('2'));
        $stack->pipe($this->middleware('3'));

        $gen = $stack->handle(Request::create('http://example.org', 'POST'));

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
        $handler = $this->handler($response = new Response());

        $stack = new MiddlewareStack($handler);
        $gen = $stack->handle(Request::create('https://example.org'));

        $this->assertSame($response, $gen->current());
    }

    public function test_a_middleware_can_modify_the_response()
    {
        $handler = $this->handler($response = new Response());

        $stack = new MiddlewareStack($handler);
        $stack->pipe(new class() implements MiddlewareInterface {
            public function process(Request $request, HttpKernelInterface $handler): Iterator
            {
                $response = $handler->handle($request);
                $response->headers->set('X-Test', 'foo');

                yield $response;
            }
        });

        $response = $stack->handle(Request::create('https://example.org'))->current();
        $this->assertInstanceOf(Response::class, $response);
        $this->assertEquals('foo', $response->headers->get('X-Test'));
    }

    private function middleware(string $name): MiddlewareInterface
    {
        $m = new class() implements MiddlewareInterface {
            public $name;

            public function process(Request $request, HttpKernelInterface $next): Iterator
            {
                MiddlewareStackTest::$out .= "Before {$this->name}\n";
                $res = $next->handle($request);
                MiddlewareStackTest::$out .= "After {$this->name}\n";

                yield $res;

                MiddlewareStackTest::$out .= "Terminated {$this->name}\n";
            }
        };

        $m->name = $name;

        return $m;
    }

    private function handler(Response $response): RequestHandlerInterface
    {
        return new class($response) implements RequestHandlerInterface {
            private Response $response;

            public function __construct(Response $response)
            {
                $this->response = $response;
            }

            public function handle(Request $request): Iterator
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
