<?php

namespace Tests\Baldinof\RoadRunnerBundle\Http;

use function Baldinof\RoadRunnerBundle\consumes;
use Baldinof\RoadRunnerBundle\Http\KernelHandler;
use Nyholm\Psr7\Factory\Psr17Factory;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\TerminableInterface;

class KernelHandlerTest extends TestCase
{
    public function test_it_calls_the_kernel()
    {
        $kernel = $this->kernel(function (Request $request) {
            $this->assertSame('http://example.org/', $request->getUri());
            $this->assertSame('GET', $request->getMethod());

            return new Response('hello');
        });

        $handler = $this->createHandler($kernel);

        $gen = $handler->handle(new ServerRequest('GET', 'http://example.org/'));
        $response = $gen->current();

        $this->assertInstanceOf(ResponseInterface::class, $response);
        $this->assertSame('hello', (string) $response->getBody());
        $this->assertFalse($kernel->terminateCalled);

        consumes($gen);

        $this->assertTrue($kernel->terminateCalled);
    }

    /**
     * @dataProvider provideBasicAuthTest
     */
    public function test_it_handles_basic_auth_header(array $headers, $expectedUser, $expectedPassword)
    {
        /** @var Request|null $collectedRequest */
        $collectedRequest = null;
        $kernel = $this->kernel(function (Request $request) use (&$collectedRequest) {
            $collectedRequest = $request;

            return new Response('hello');
        });

        $handler = $this->createHandler($kernel);

        $request = new ServerRequest('GET', 'http://example.org/', $headers);

        consumes($handler->handle($request));

        $this->assertInstanceOf(Request::class, $collectedRequest);
        $this->assertEquals($expectedUser, $collectedRequest->getUser());
        $this->assertEquals($expectedPassword, $collectedRequest->getPassword());
    }

    public function provideBasicAuthTest()
    {
        yield 'no Authorization header' => [
            'headers' => [],
            'expectedUser' => null,
            'expectedPassword' => null,
        ];

        yield 'unsupported Authorization header' => [
            'headers' => ['Authorization' => 'Bearer token'],
            'expectedUser' => null,
            'expectedPassword' => null,
        ];

        yield 'wrongly encoded value' => [
            'headers' => ['Authorization' => 'Basic not-base64'],
            'expectedUser' => null,
            'expectedPassword' => null,
        ];

        yield 'only user' => [
            'headers' => ['Authorization' => sprintf('Basic %s', base64_encode('the-user'))],
            'expectedUser' => 'the-user',
            'expectedPassword' => null,
        ];

        yield 'user and password' => [
            'headers' => ['Authorization' => sprintf('Basic %s', base64_encode('the-user:the-password'))],
            'expectedUser' => 'the-user',
            'expectedPassword' => 'the-password',
        ];
    }

    private function createHandler(HttpKernelInterface $kernel): KernelHandler
    {
        $psrFactory = new Psr17Factory();

        return new KernelHandler($kernel, new PsrHttpFactory($psrFactory, $psrFactory, $psrFactory, $psrFactory), new HttpFoundationFactory());
    }

    private function kernel(callable $callback)
    {
        return new class($callback) implements HttpKernelInterface, TerminableInterface {
            private $callback;
            public $terminateCalled = false;

            public function __construct(callable $callback)
            {
                $this->callback = $callback;
            }

            public function handle(Request $request, int $type = self::MASTER_REQUEST, bool $catch = true): Response
            {
                return ($this->callback)($request);
            }

            public function terminate(Request $request, Response $response)
            {
                $this->terminateCalled = true;
            }
        };
    }
}
