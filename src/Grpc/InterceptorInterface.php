<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Grpc;

use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequest;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequestInvokerInterface;

/**
 * An interceptor that allow to do some work after sending the response.
 */
interface InterceptorInterface
{
    /**
     * The traversable should be consumed in 2 times.
     *  1. Get the first value and send the response to the client
     *  2. Consumes all other values to terminate the iterator.
     *
     * This way an interceptor can handle heavy jobs after sending the response to the client.
     *
     * An easy way to implement this method is via Generator:
     * ```php
     *   // code here can alter the invocation
     *   yield $next->invoke($invocation);
     *   // code here will be executed after sending the response
     * ```
     *
     * @return \Iterator<string> Only the first item will be sent to the client
     */
    public function intercept(GrpcRequest $invocation, GrpcRequestInvokerInterface $next): \Iterator;
}
