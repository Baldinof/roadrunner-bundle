<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Baldinof\RoadRunnerBundle\DependencyInjection\BaldinofRoadRunnerExtension;
use Baldinof\RoadRunnerBundle\Grpc\GrpcServiceProvider;
use Baldinof\RoadRunnerBundle\Helpers\RoadRunnerConfig;
use Baldinof\RoadRunnerBundle\Helpers\RPCFactory;
use Baldinof\RoadRunnerBundle\Http\KernelHandler;
use Baldinof\RoadRunnerBundle\Http\MiddlewareStack;
use Baldinof\RoadRunnerBundle\Http\RequestHandlerInterface;
use Baldinof\RoadRunnerBundle\Reboot\KernelRebootStrategyInterface;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\HttpFoundationWorker;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\HttpFoundationWorkerInterface;
use Baldinof\RoadRunnerBundle\Worker\GrpcWorker as InternalGrpcWorker;
use Baldinof\RoadRunnerBundle\Worker\HttpDependencies;
use Baldinof\RoadRunnerBundle\Worker\HttpWorker as InternalHttpWorker;
use Baldinof\RoadRunnerBundle\Worker\WorkerRegistry;
use Baldinof\RoadRunnerBundle\Worker\WorkerRegistryInterface;
use Psr\Log\LoggerInterface;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunner\GRPC\Invoker as GrpcInvoker;
use Spiral\RoadRunner\GRPC\Server as GrpcServer;
use Spiral\RoadRunner\GRPC\ServiceInterface as GrpcServiceInterface;
use Spiral\RoadRunner\Http\HttpWorker;
use Spiral\RoadRunner\Http\HttpWorkerInterface;
use Spiral\RoadRunner\Metrics\Metrics;
use Spiral\RoadRunner\Metrics\MetricsInterface;
use Spiral\RoadRunner\Worker as RoadRunnerWorker;
use Spiral\RoadRunner\WorkerInterface as RoadRunnerWorkerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

return static function (ContainerConfigurator $container) {
    $container->parameters()
        ->set('baldinof_road_runner.intercept_side_effect', true);

    $services = $container->services();

    // RoadRuner services
    $services->set(EnvironmentInterface::class)
        ->factory([Environment::class, 'fromGlobals']);

    $services->set(RoadRunnerWorkerInterface::class, RoadRunnerWorker::class)
        ->factory([RoadRunnerWorker::class, 'createFromEnvironment'])
        ->args([service(EnvironmentInterface::class), '%baldinof_road_runner.intercept_side_effect%']);

    $services->set(HttpWorkerInterface::class, HttpWorker::class)
        ->args([service(RoadRunnerWorkerInterface::class)]);

    $services->set(RPCInterface::class)
        ->factory([RPCFactory::class, 'fromEnvironment'])
        ->args([service(EnvironmentInterface::class)]);

    $services->set(MetricsInterface::class, Metrics::class)
        ->args([service(RPCInterface::class)]);

    // Bundle services
    $services->set(RoadRunnerConfig::class)
        ->args([param('kernel.project_dir')]);

    $services->set(HttpFoundationWorkerInterface::class, HttpFoundationWorker::class)
        ->args([
            service(HttpWorkerInterface::class),
            service(RoadRunnerConfig::class),
        ]);

    $services->set(WorkerRegistryInterface::class, WorkerRegistry::class)
        ->public();

    $services->set(InternalHttpWorker::class)
        ->public() // Manually retrieved on the DIC in the Worker if the kernel has been rebooted
        ->tag('monolog.logger', ['channel' => BaldinofRoadRunnerExtension::MONOLOG_CHANNEL])
        ->args([
            service('kernel'),
            service(LoggerInterface::class),
            service(HttpFoundationWorkerInterface::class),
        ]);

    $services
        ->get(WorkerRegistryInterface::class)
        ->call('registerWorker', [
            Environment\Mode::MODE_HTTP,
            service(InternalHttpWorker::class),
        ]);

    $services->set(HttpDependencies::class)
        ->public() // Manually retrieved on the DIC in the Worker if the kernel has been rebooted
        ->args([
            service(MiddlewareStack::class),
            service(KernelRebootStrategyInterface::class),
            service(EventDispatcherInterface::class),
        ]);

    $services->set(KernelHandler::class)
        ->args([
            service('kernel'),
        ]);

    $services->set(MiddlewareStack::class)
        ->args([service(KernelHandler::class)]);

    $services->alias(RequestHandlerInterface::class, MiddlewareStack::class);

    if (interface_exists(GrpcServiceInterface::class)) {
        $services->set(GrpcServiceProvider::class);
        $services->set(GrpcInvoker::class);

        $services->set(GrpcServer::class)
            ->args([
                service(GrpcInvoker::class),
            ]);

        $services->set(InternalGrpcWorker::class)
            ->public() // Manually retrieved on the DIC in the Worker if the kernel has been rebooted
            ->tag('monolog.logger', ['channel' => BaldinofRoadRunnerExtension::MONOLOG_CHANNEL])
            ->args([
                service(LoggerInterface::class),
                service(RoadRunnerWorkerInterface::class),
                service(GrpcServiceProvider::class),
                service(GrpcServer::class),
            ]);

        $services
            ->get(WorkerRegistryInterface::class)
            ->call('registerWorker', [
                Environment\Mode::MODE_GRPC,
                service(InternalGrpcWorker::class),
            ]);
    }
};
