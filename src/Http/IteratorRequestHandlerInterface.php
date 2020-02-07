<?php

namespace Baldinof\RoadRunnerBundle\Http;

use Iterator;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;

/**
 * A request handler that allow to do some work after sending the response.
 */
interface IteratorRequestHandlerInterface
{
    /**
     * The iterator should be consumed in 2 times.
     *  1. Get the first value that should implement {@link ResponseInterface}, and send the response to the client
     *  2. Consumes all other values to terminate the iterator.
     *
     * This way a handler can do heavy jobs after sending the response to the client.
     *
     * An easy way to implement this method is via Generator:
     * ```php
     *   yield new \Nyholm\Psr7\Response(204);
     *   // code here will be executed after sending the response
     * ```
     *
     * @return Iterator<ResponseInterface> Only the first item will be sent to the client
     */
    public function handle(ServerRequestInterface $request): Iterator;
}
