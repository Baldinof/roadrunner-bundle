<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\RoadRunnerBridge;

use Spiral\RoadRunner\GRPC\Exception\InvokeException;

interface GrpcRequestInvokerInterface
{
    /**
     * Call a service with the given method and input and return response message converted to string.
     *
     * @throws InvokeException
     */
    public function invoke(GrpcRequest $request): string;
}
