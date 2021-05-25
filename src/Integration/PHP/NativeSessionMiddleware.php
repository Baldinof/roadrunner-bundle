<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Integration\PHP;

use Baldinof\RoadRunnerBundle\Exception\HeadersAlreadySentException;
use Baldinof\RoadRunnerBundle\Http\MiddlewareInterface;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class NativeSessionMiddleware implements MiddlewareInterface
{
    public function process(Request $request, HttpKernelInterface $next): \Iterator
    {
        if (headers_sent()) {
            throw new HeadersAlreadySentException('Headers has already been sent. Something have been echoed on stdout.');
        }

        unset($_SESSION);

        $sessionName = session_name();
        if ($sessionName) {
            $oldId = (string) $request->cookies->get($sessionName);
        } else {
            $oldId = '';
        }

        session_id($oldId); // Set to current session or reset to nothing

        try {
            $response = $next->handle($request);

            $newId = session_id();

            if ($newId && $newId !== $oldId) {
                // A session has been started or the id has changed: send the cookie again
                $this->addSessionCookie($response, $newId);
            }

            yield $response;
        } finally {
            if (PHP_SESSION_ACTIVE === session_status()) {
                session_write_close();
            }
        }
    }

    private function addSessionCookie(Response $response, string $sessionId): void
    {
        $params = session_get_cookie_params();
        $sessionName = session_name();

        if (!$sessionName) {
            return;
        }

        $expire = 0;

        if ($params['lifetime'] > 0) {
            $expire = time() + $params['lifetime'];
        }

        $sameSite = null;

        if ($params['samesite']) {
            $sameSite = $params['samesite'];
        }

        $cookie = Cookie::create($sessionName, $sessionId, $expire, $params['path'], $params['domain'], $params['secure'], $params['httponly'], false, $sameSite);

        $response->headers->setCookie($cookie);
    }
}
