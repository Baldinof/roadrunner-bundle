<?php

namespace Baldinof\RoadRunnerBundle\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class BlackfireMiddleware implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): ResponseInterface
    {
        if (!$request->hasHeader('x-blackfire-query')) {
            return $next->handle($request);
        }

        $probe = new \BlackfireProbe($request->getHeader('x-blackfire-query')[0]);

        $probe->enable();

        $response = $next->handle($request);

        if ($probe->isEnabled()) {
            $probe->close();

            $probeHeader = explode(':', $probe->getResponseLine(), 2);

            $response = $response->withHeader('x-'.$probeHeader[0], trim($probeHeader[1]));
        }

        return $response;
    }
}
