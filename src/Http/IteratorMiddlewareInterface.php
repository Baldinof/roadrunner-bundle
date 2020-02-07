<?php

namespace Baldinof\RoadRunnerBundle\Http;

use Iterator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * A middleware that allow to do some work after sending the response.
 */
interface IteratorMiddlewareInterface
{
    /**
     * The traversable should be consumed in 2 times.
     *  1. Get the first value that should implement {@link ResponseInterface}, and send the response to the client
     *  2. Consumes all other values to terminate the iterator.
     *
     * This way a middleware can handle heavy jobs after sending the response to the client.
     *
     * An easy way to implement this method is via Generator:
     * ```php
     *   yield $next->handle($request)->withHeader('X-Middleware', 'MyMiddleware');
     *   // code here will be executed after sending the response
     * ```
     *
     * @return Iterator<ResponseInterface> Only the first item will be sent to the client
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $next): Iterator;
}
