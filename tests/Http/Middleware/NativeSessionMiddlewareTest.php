<?php

namespace Tests\Baldinof\RoadRunnerBundle\Http\Middleware;

use function Baldinof\RoadRunnerBundle\consumes;

use Baldinof\RoadRunnerBundle\Exception\HeadersAlreadySentException;
use Baldinof\RoadRunnerBundle\Http\Middleware\NativeSessionMiddleware;
use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\Modifier\SameSite;
use Nyholm\Psr7\Response;
use Nyholm\Psr7\ServerRequest;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class NativeSessionMiddlewareTest extends TestCase
{
    private $middleware;

    public function setUp(): void
    {
        $this->middleware = new NativeSessionMiddleware();
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_sessions()
    {
        $response = $this->process($this->emptyRequest());

        $this->assertEquals('1', (string) $response->getBody());

        $sessionedRequest = $this->requestWithCookiesFrom($response);

        $sessionedResponse = $this->process($sessionedRequest);

        // The session has been re-used
        $this->assertEquals('2', (string) $sessionedResponse->getBody());

        // A new session has been created
        $noSessionResponse = $this->process($this->emptyRequest());

        $this->assertEquals('1', (string) $noSessionResponse->getBody());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_it_uses_php_params()
    {
        $lifetime = 600;
        $now = time();
        session_set_cookie_params([
            'lifetime' => $lifetime,
            'path' => '/hello',
            'domain' => 'example.org',
            'secure' => true,
            'httponly' => true,
            'samesite' => 'Strict',
        ]);

        $response = $this->process($this->emptyRequest());

        $cookie = FigResponseCookies::get($response, session_name());

        $this->assertEquals($cookie->getPath(), '/hello');
        $this->assertEquals($cookie->getDomain(), 'example.org');
        $this->assertTrue($cookie->getSecure());
        $this->assertTrue($cookie->getHttpOnly());
        $this->assertEquals(SameSite::strict(), $cookie->getSameSite());
        $this->assertEquals($now + $lifetime, $cookie->getExpires());
    }

    public function test_it_throws_if_headers_already_sent()
    {
        if (!headers_sent()) {
            $this->markAsRisky();
        }

        $this->expectException(HeadersAlreadySentException::class);

        $this->process($this->emptyRequest());
    }

    private function requestWithCookiesFrom(ResponseInterface $response): ServerRequestInterface
    {
        $request = $this->emptyRequest();

        if ($response->hasHeader('Set-Cookie')) {
            $request = $request->withHeader('Cookie', $response->getHeaderLine('Set-Cookie'));
        }

        return $request;
    }

    private function process(ServerRequestInterface $request): ResponseInterface
    {
        $it = $this->middleware->process($request, new class() implements RequestHandlerInterface {
            public function handle(ServerRequestInterface $request): ResponseInterface
            {
                session_start();

                $counter = ($_SESSION['counter'] ?? 0) + 1;

                $_SESSION['counter'] = $counter;

                return new Response(200, [], (string) $counter);
            }
        });

        $resp = $it->current();

        consumes($it);

        return $resp;
    }

    private function emptyRequest(): ServerRequestInterface
    {
        return new ServerRequest('GET', 'https://example.org');
    }
}
