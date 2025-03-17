<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Worker;

use Baldinof\RoadRunnerBundle\Grpc\GrpcServiceProvider;
use Psr\Log\LoggerInterface;
use Spiral\RoadRunner\GRPC\Exception\InvokeException;
use Spiral\RoadRunner\GRPC\Server;
use Spiral\RoadRunner\WorkerInterface as RoadrunnerWorker;

/**
 * @internal
 */
final class GrpcWorker implements WorkerInterface
{
    private Server $server;

    public function __construct(
        private LoggerInterface $logger,
        private RoadrunnerWorker $roadRunnerWorker,
        private GrpcServiceProvider $grpcServiceProvider,
        private GrpcInvoker $invoker,
    ) {
        $this->server = new Server($this->invoker);
    }

    public function start(): void
    {
        foreach ($this->grpcServiceProvider->getRegisteredServices() as $interface => $service) {
            $this->logger->debug(
                \sprintf(
                    'Registering GRPC service for \'%s\' from \'%s\'',
                    $interface,
                    \get_class($service),
                ),
            );

            $this->server->registerService($interface, $service);
        }

        $this->invoker->onServerStart();
        $this->server->serve($this->roadRunnerWorker, [$this, 'finalizeInvocation']);
        $this->invoker->onServerStop();
    }

    public function finalizeInvocation(?\Throwable $e = null): void
    {
        try {
            if (null !== $e && !$e instanceof InvokeException) {
                $this->invoker->invokeThrowable($e);
            }
        } finally {
            $this->invoker->invokeFinalize();
        }
    }
}
