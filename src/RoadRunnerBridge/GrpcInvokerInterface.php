<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\RoadRunnerBridge;

use Spiral\RoadRunner\GRPC\InvokerInterface;

interface GrpcInvokerInterface extends InvokerInterface
{
    public function invokeFinalize(?\Throwable $e): void;
    public function onServerStart(): void;
    public function onServerStop(): void;
}
