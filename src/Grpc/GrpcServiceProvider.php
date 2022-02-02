<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Grpc;

use Spiral\RoadRunner\GRPC\ServiceInterface;

/**
 * @internal
 */
final class GrpcServiceProvider
{
    /**
     * @var array<class-string<ServiceInterface>, ServiceInterface>
     */
    private array $services = [];

    /**
     * @template T of ServiceInterface
     *
     * @param class-string<T> $interface
     * @param T               $service
     */
    public function registerService(string $interface, object $service): void
    {
        $this->services[$interface] = $service;
    }

    /**
     * @return array<class-string<ServiceInterface>, ServiceInterface>
     */
    public function getRegisteredServices(): array
    {
        return $this->services;
    }
}
