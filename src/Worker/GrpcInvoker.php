<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Worker;

use Baldinof\RoadRunnerBundle\Event\GrpcTerminateEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerExceptionEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerKernelRebootedEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerStartEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerStopEvent;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequest;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcTerminableInterface;
use Psr\Log\LoggerInterface;
use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\InvokerInterface;
use Spiral\RoadRunner\GRPC\Method;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Spiral\RoadRunner\WorkerInterface as RoadRunnerWorkerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\RebootableInterface;
use Symfony\Contracts\Service\ResetInterface;

use function Baldinof\RoadRunnerBundle\consumes;

/**
 * @internal
 */
final class GrpcInvoker implements InvokerInterface, GrpcTerminableInterface
{
    private GrpcDependencies $dependencies;

    private ?\Iterator $gen;

    public function __construct(
        private KernelInterface $kernel,
        private LoggerInterface $logger,
        private RoadRunnerWorkerInterface $worker,
    ) {
        $container = $kernel->getContainer();

        /** @var GrpcDependencies */
        $dependencies = $container->get(GrpcDependencies::class);
        $this->dependencies = $dependencies;
    }

    public function onServerStart(): void
    {
        $this->dependencies->getEventDispatcher()->dispatch(new WorkerStartEvent());
    }

    public function invoke(ServiceInterface $service, Method $method, ContextInterface $ctx, ?string $input): string
    {
        $request = new GrpcRequest($service, $method, $ctx, $input);
        $gen = $this->dependencies->getRequestHandler()->handle($request);

        /** @var string $response */
        $response = $gen->current();
        // To be terminated :)
        $this->gen = $gen;

        return $response;
    }

    public function invokeThrowable(\Throwable $e): void
    {
        $this->logger->error('An error occured: '.$e->getMessage(), ['throwable' => $e]);

        $this->dependencies->getEventDispatcher()->dispatch(new WorkerExceptionEvent($e));

        $this->worker->stop();
    }

    public function invokeFinalize(): void
    {
        if (isset($this->gen)) {
            consumes($this->gen);
            $this->gen = null;
        }

        try {
            if ($this->kernel->getContainer()->has('services_resetter')) {
                /** @var ResetInterface $resetter */
                $resetter = $this->kernel->getContainer()->get('services_resetter');
                $resetter->reset();
            }
        } catch (\Throwable $e) {
            $this->logger->error(
                \sprintf(
                    'An error occurred when resetting services: %s',
                    $e->getMessage()
                ),
                ['exception' => $e]
            );
        }

        if ($this->kernel instanceof RebootableInterface && $this->dependencies->getKernelRebootStrategy()->shouldReboot()) {
            $this->kernel->reboot(null);
            /** @var GrpcDependencies */
            $deps = $this->kernel->getContainer()->get(GrpcDependencies::class);

            $this->dependencies = $deps;
            $this->dependencies->getEventDispatcher()->dispatch(new WorkerKernelRebootedEvent());
        }

        $this->dependencies->getKernelRebootStrategy()->clear();
    }

    public function terminate(GrpcRequest $request, string $response): void
    {
        $this->dependencies->getEventDispatcher()->dispatch(new GrpcTerminateEvent($request, $response));
    }

    public function onServerStop(): void
    {
        $this->dependencies->getEventDispatcher()->dispatch(new WorkerStopEvent());
    }
}
