<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Worker;

use Baldinof\RoadRunnerBundle\Grpc\GrpcServiceProvider;
use Psr\Log\LoggerInterface;
use Spiral\RoadRunner\GRPC\InvokerInterface;
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
    private InvokerInterface $invoker;

    public function __construct(
        LoggerInterface $logger,
        RoadRunnerWorker $roadRunnerWorker,
        GrpcServiceProvider $grpcServiceProvider,
        InvokerInterface $invoker
    ) {
        $this->logger = $logger;
        $this->roadRunnerWorker = $roadRunnerWorker;
        $this->grpcServiceProvider = $grpcServiceProvider;
        $this->invoker = $invoker;
    }

    public function start(): void
    {
        $server = new Server($this->invoker);

        foreach ($this->grpcServiceProvider->getRegisteredServices() as $interface => $service) {
            $this->logger->debug(
                sprintf(
                    'Registering GRPC service for \'%s\' from \'%s\'',
                    $interface,
                    \get_class($service),
                ),
            );

            $server->registerService($interface, $service);
        }

        $server->serve($this->roadRunnerWorker);
    }
}
