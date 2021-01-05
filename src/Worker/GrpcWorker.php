<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Worker;

use Baldinof\RoadRunnerBundle\Grpc\GrpcServiceProvider;
use Psr\Log\LoggerInterface;
use Spiral\RoadRunner\Worker as RoadRunnerWorker;
use Spiral\GRPC\Server;

/**
 * @internal
 */
final class GrpcWorker implements GrpcWorkerInterface
{
    private $logger;

    /**
     * @var RoadRunnerWorker
     */
    private $rrWorker;
    /**
     * @var GrpcServiceProvider
     */
    private $grpcServiceProvider;

    public function __construct(
        LoggerInterface $logger,
        RoadRunnerWorker $rrWorker,
        GrpcServiceProvider $grpcServiceProvider
    ) {
        $this->logger = $logger;
        $this->rrWorker = $rrWorker;
        $this->grpcServiceProvider = $grpcServiceProvider;
    }

    public function start(): void
    {
        $server = new Server();
        foreach ($this->grpcServiceProvider->getRegisteredServices() as $interface => $service) {
            $this->logger->debug('Register GRPC service for ' . $interface . ', from ' . get_class($service));
            $server->registerService($interface, $service);
        }

        $server->serve($this->rrWorker);
    }
}
