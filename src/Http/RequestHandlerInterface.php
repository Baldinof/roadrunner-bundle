<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Http;

use Iterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A request handler that allow to do some work after sending the response.
 */
interface RequestHandlerInterface
{
    /**
     * The iterator should be consumed in 2 times.
     *  1. Get the first value (should implement {@link Response}), and send the response to the client
     *  2. Consumes all other values to terminate the iterator.
     *
     * This way a handler can do heavy jobs after sending the response to the client.
     *
     * An easy way to implement this method is via Generator:
     * ```php
     *   yield new Response("foo");
     *   // code here will be executed after sending the response
     * ```
     *
     * @return \Iterator<Response> Only the first item will be sent to the client
     */
    public function handle(Request $request): \Iterator;
}
