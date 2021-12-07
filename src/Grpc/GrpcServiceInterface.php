<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Grpc;

interface GrpcServiceInterface
{
    public static function getImplementedInterface(): string;
}
