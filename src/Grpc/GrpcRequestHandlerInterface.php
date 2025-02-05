<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Grpc;

use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequest;

/**
 * A request handler that allow to do some work after sending the response.
 */
interface GrpcRequestHandlerInterface
{
    /**
     * The iterator should be consumed in 2 times.
     *  1. Get the first value and send the response to the client
     *  2. Consumes all other values to terminate the iterator.
     *
     * This way a handler can do heavy jobs after sending the response to the client.
     *
     * An easy way to implement this method is via Generator:
     * ```php
     *   yield "foo";
     *   // code here will be executed after sending the response
     * ```
     *
     * @return \Iterator<string> Only the first item will be sent to the client
     */
    public function handle(GrpcRequest $request): \Iterator;
}
