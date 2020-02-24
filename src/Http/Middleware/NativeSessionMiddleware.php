<?php

namespace Baldinof\RoadRunnerBundle\Http\Middleware;

use Baldinof\RoadRunnerBundle\Exception\HeadersAlreadySentException;
use Baldinof\RoadRunnerBundle\Http\IteratorMiddlewareInterface;
use Dflydev\FigCookies\FigRequestCookies;
use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\Modifier\SameSite;
use Dflydev\FigCookies\SetCookie;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class NativeSessionMiddleware implements IteratorMiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): \Iterator
    {
        if (headers_sent()) {
            throw new HeadersAlreadySentException('Headers has already been sent. Something have been echoed on stdout.');
        }

        unset($_SESSION);

        $oldId = FigRequestCookies::get($request, session_name())->getValue() ?: '';

        session_id($oldId); // Set to current session or reset to nothing

        try {
            $response = $next->handle($request);

            $newId = session_id();

            if ($newId !== $oldId) {
                // A session has been started or the id has changed: send the cookie again
                $response = $this->addSessionCookie($response, $newId);
            }

            yield $response;
        } finally {
            if (PHP_SESSION_ACTIVE === session_status()) {
                session_write_close();
            }
        }
    }

    private function addSessionCookie(ResponseInterface $response, string $sessionId): ResponseInterface
    {
        $params = session_get_cookie_params();

        $setCookie = SetCookie::create(session_name())
            ->withValue($sessionId)
            ->withPath($params['path'])
            ->withDomain($params['domain'])
            ->withSecure($params['secure'])
            ->withHttpOnly($params['httponly'])
        ;

        if ($params['lifetime'] > 0) {
            $setCookie = $setCookie->withExpires(time() + $params['lifetime']);
        }

        if ($params['samesite']) {
            $setCookie = $setCookie->withSameSite(SameSite::fromString($params['samesite']));
        }

        return FigResponseCookies::set($response, $setCookie);
    }
}
