<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Worker;

use Baldinof\RoadRunnerBundle\Grpc\GrpcServiceProvider;
use Psr\Log\LoggerInterface;
use Spiral\RoadRunner\GRPC\Server;
use Spiral\RoadRunner\Worker as RoadRunnerWorker;
use function sprintf;

/**
 * @internal
 */
final class GrpcWorker implements GrpcWorkerInterface
{
    private LoggerInterface $logger;
    private RoadRunnerWorker $roadRunnerWorker;
    private GrpcServiceProvider $grpcServiceProvider;
    private Server $server;

    public function __construct(
        LoggerInterface $logger,
        RoadRunnerWorker $roadRunnerWorker,
        GrpcServiceProvider $grpcServiceProvider,
        Server $server
    ) {
        $this->logger = $logger;
        $this->roadRunnerWorker = $roadRunnerWorker;
        $this->grpcServiceProvider = $grpcServiceProvider;
        $this->server = $server;
    }

    public function start(): void
    {
        foreach ($this->grpcServiceProvider->getRegisteredServices() as $interface => $service) {
            $this->logger->debug(
                sprintf(
                    'Registering GRPC service for \'%s\' from \'%s\'',
                    $interface,
                    \get_class($service),
                ),
            );

            $this->server->registerService($interface, $service);
        }

        $this->server->serve($this->roadRunnerWorker);
    }
}
