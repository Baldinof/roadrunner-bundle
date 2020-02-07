<?php

namespace Baldinof\RoadRunnerBundle\Http\Middleware;

use Baldinof\RoadRunnerBundle\Http\IteratorMiddlewareInterface;
use Dflydev\FigCookies\FigResponseCookies;
use Dflydev\FigCookies\Modifier\SameSite;
use Dflydev\FigCookies\SetCookie;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class NativeSessionMiddleware implements IteratorMiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): \Iterator
    {
        session_unset();

        $cookies = $request->getCookieParams();

        $oldId = $cookies[session_name()] ?? '';

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
                ->withSameSite(SameSite::fromString($params['samesite']))
            ;

            $lifetime = $params['lifetime'];

            if ($lifetime > 0) {
                $setCookie = $setCookie->withExpires(time() + $lifetime);
            }

            $response = FigResponseCookies::set($response, $setCookie);
        }

        yield $response;
    }
}
