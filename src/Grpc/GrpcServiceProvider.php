<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Grpc;

class GrpcServiceProvider
{
    private array $services = [];

    public function registerService(string $interface, object $service): void
    {
        $this->services[$interface] = $service;
    }

    public function getRegisteredServices(): array
    {
        return $this->services;
    }
}
