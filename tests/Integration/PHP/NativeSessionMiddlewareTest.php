<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\Integration\PHP;

use function Baldinof\RoadRunnerBundle\consumes;
use Baldinof\RoadRunnerBundle\Exception\HeadersAlreadySentException;
use Baldinof\RoadRunnerBundle\Integration\PHP\NativeSessionMiddleware;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class NativeSessionMiddlewareTest extends TestCase
{
    private NativeSessionMiddleware $middleware;

    public function setUp(): void
    {
        $this->middleware = new NativeSessionMiddleware();
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_sessions_works()
    {
        $response = $this->process($this->emptyRequest());

        $this->assertEquals('1', (string) $response->getContent());
        $sessionedRequest = $this->requestWithCookiesFrom($response);

        $sessionedResponse = $this->process($sessionedRequest);

        // The session has been re-used
        $this->assertEquals('2', (string) $sessionedResponse->getContent());

        // A new session has been created
        $noSessionResponse = $this->process($this->emptyRequest());

        $this->assertEquals('1', (string) $noSessionResponse->getContent());
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
        $cookie = $this->getCookie($response, session_name());

        $this->assertEquals('/hello', $cookie->getPath());
        $this->assertEquals('example.org', $cookie->getDomain());
        $this->assertTrue($cookie->isSecure());
        $this->assertTrue($cookie->isHttpOnly());
        $this->assertEquals(Cookie::SAMESITE_STRICT, $cookie->getSameSite());
        $this->assertEquals($now + $lifetime, $cookie->getExpiresTime());
    }

    /**
     * @runInSeparateProcess
     * @preserveGlobalState disabled
     */
    public function test_it_closes_session_if_the_handler_throws()
    {
        $expectedException = new \Exception('Error during handler');
        try {
            $this->process($this->emptyRequest(), function ($req) use ($expectedException) {
                session_start();

                throw $expectedException;
            });
        } catch (\Throwable $e) {
            if ($e !== $expectedException) {
                throw $e;
            }

            $this->assertEquals(PHP_SESSION_NONE, session_status());
        }
    }

    public function test_it_throws_if_headers_already_sent()
    {
        if (!headers_sent()) {
            $this->markAsRisky();
        }

        $this->expectException(HeadersAlreadySentException::class);

        $this->process($this->emptyRequest());
    }

    private function requestWithCookiesFrom(Response $response): Request
    {
        $request = $this->emptyRequest();

        if ($response->headers->has('Set-Cookie')) {
            $request->headers->set('Cookie', $response->headers->get('Set-Cookie'));

            foreach ($response->headers->getCookies() as $cookie) {
                $request->cookies->set($cookie->getName(), $cookie->getValue());
            }
        }

        return $request;
    }

    private function process(Request $request, ?\Closure $handler = null): Response
    {
        if (null === $handler) {
            $handler = function (Request $request): Response {
                session_start();

                $counter = ($_SESSION['counter'] ?? 0) + 1;

                $_SESSION['counter'] = $counter;

                return new Response((string) $counter, 200, []);
            };
        }

        $it = $this->middleware->process($request, new class($handler) implements HttpKernelInterface {
            private \Closure $handler;

            public function __construct(\Closure $handler)
            {
                $this->handler = $handler;
            }

            public function handle(Request $request, int $type = self::MASTER_REQUEST, bool $catch = true)
            {
                return ($this->handler)($request);
            }
        });

        $resp = $it->current();

        consumes($it);

        return $resp;
    }

    private function emptyRequest(): Request
    {
        return Request::create('https://example.org');
    }

    private function getCookie(Response $response, string $cookieName): Cookie
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $cookieName) {
                return $cookie;
            }
        }

        throw new \OutOfBoundsException("Cannot find cookie '$cookieName'");
    }
}
