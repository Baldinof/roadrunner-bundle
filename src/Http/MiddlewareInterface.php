<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Http;

use Iterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * A middleware that allow to do some work after sending the response.
 */
interface MiddlewareInterface
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
     * @return Iterator<Response> Only the first item will be sent to the client
     */
    public function process(Request $request, HttpKernelInterface $next): Iterator;
}
