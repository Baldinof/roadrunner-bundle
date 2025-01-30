<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Worker;

use Baldinof\RoadRunnerBundle\Grpc\GrpcServiceProvider;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcInvokerInterface;
use Psr\Log\LoggerInterface;
use Spiral\RoadRunner\GRPC\Server;
use Spiral\RoadRunner\Worker as RoadRunnerWorker;

use function sprintf;

/**
 * @internal
 */
final class GrpcWorker implements WorkerInterface
{
    private Server $server;

    public function __construct(
        private LoggerInterface $logger,
        private RoadRunnerWorker $roadRunnerWorker,
        private GrpcServiceProvider $grpcServiceProvider,
        private GrpcInvokerInterface $invoker,
    ) {
        $this->server = new Server($this->invoker);
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

        $this->invoker->onServerStart();
        $this->server->serve($this->roadRunnerWorker, [$this->invoker, 'invokeFinalize']);
        $this->invoker->onServerStop();
    }
}
