<?php

namespace Baldinof\RoadRunnerBundle\Http\Middleware;

use Baldinof\RoadRunnerBundle\Exception\HeadersAlreadySentException;
use Baldinof\RoadRunnerBundle\Http\IteratorMiddlewareInterface;
use Dflydev\FigCookies\FigRequestCookies;
use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\Modifier\SameSite;
use Dflydev\FigCookies\SetCookie;
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

        $response = $next->handle($request);

        $newId = session_id();

        if ($newId !== $oldId) {
            // A session has been started or the id has changed: send the cookie again
            $params = session_get_cookie_params();

            $setCookie = SetCookie::create(session_name())
                ->withValue($newId)
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

            $response = FigResponseCookies::set($response, $setCookie);
        }

        yield $response;

        if (PHP_SESSION_ACTIVE === session_status()) {
            session_commit();
        }
    }
}
