<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Baldinof\RoadRunnerBundle\Command\GrpcWorkerCommand;
use Baldinof\RoadRunnerBundle\Command\WorkerCommand;
use Baldinof\RoadRunnerBundle\DependencyInjection\BaldinofRoadRunnerExtension;
use Baldinof\RoadRunnerBundle\EventListener\StreamedResponseListener;
use Baldinof\RoadRunnerBundle\Grpc\GrpcServiceProvider;
use Baldinof\RoadRunnerBundle\Http\IteratorRequestHandlerInterface;
use Baldinof\RoadRunnerBundle\Http\KernelHandler;
use Baldinof\RoadRunnerBundle\Http\Middleware\NativeSessionMiddleware;
use Baldinof\RoadRunnerBundle\Http\MiddlewareStack;
use Baldinof\RoadRunnerBundle\Metric\MetricFactory;
use Baldinof\RoadRunnerBundle\Reboot\KernelRebootStrategyInterface;
use Baldinof\RoadRunnerBundle\Worker\Configuration;
use Baldinof\RoadRunnerBundle\Worker\Dependencies;
use Baldinof\RoadRunnerBundle\Worker\GrpcWorker;
use Baldinof\RoadRunnerBundle\Worker\GrpcWorkerInterface;
use Baldinof\RoadRunnerBundle\Worker\Worker;
use Baldinof\RoadRunnerBundle\Worker\WorkerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Log\LoggerInterface;
use Spiral\Goridge\RelayInterface;
use Spiral\Goridge\SocketRelay;
use Spiral\RoadRunner\MetricsInterface;
use Spiral\RoadRunner\PSR7Client;
use Spiral\RoadRunner\Worker as RoadRunnerWorker;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;

// Polyfill of the `service()` function introduced in Symfony 5.1 when using older version
if (!\function_exists('Symfony\Component\DependencyInjection\Loader\Configurator\service')) {
    function service(string $id): ReferenceConfigurator
    {
        return ref($id);
    }
}

return static function (ContainerConfigurator $container) {
    $services = $container->services();
    $services->set(RoadRunnerWorker::class)
        ->args([service(RelayInterface::class)]);

    $services->set('baldinof.roadrunner.grpc_worker', RoadRunnerWorker::class)
        ->args([service('baldinof.roadrunner.grpc_relay')]);

    $services->set(PSR7Client::class)
        ->args([
            service(RoadRunnerWorker::class),
            service(ServerRequestFactoryInterface::class),
            service(StreamFactoryInterface::class),
            service(UploadedFileFactoryInterface::class),
        ]);

    $services->set(RelayInterface::class, SocketRelay::class)
        ->args([
            '%kernel.project_dir%/var/roadrunner.sock',
            null,
            SocketRelay::SOCK_UNIX,
        ]);

    $services->set('baldinof.roadrunner.grpc_relay', SocketRelay::class)
        ->args([
            '%kernel.project_dir%/var/roadrunner_grpc.sock',
            null,
            SocketRelay::SOCK_UNIX,
        ]);

    $services->set(GrpcServiceProvider::class);

    $services->set(Configuration::class);

    $services->set(WorkerInterface::class, Worker::class)
        ->tag('monolog.logger', ['channel' => BaldinofRoadRunnerExtension::MONOLOG_CHANNEL])
        ->args([
            service('kernel'),
            service('event_dispatcher'),
            service(Configuration::class),
            service(MiddlewareStack::class),
            service(LoggerInterface::class),
            service(PSR7Client::class),
            service(Dependencies::class),
        ]);

    $services->set(GrpcWorkerInterface::class, GrpcWorker::class)
        ->tag('monolog.logger', ['channel' => BaldinofRoadRunnerExtension::MONOLOG_CHANNEL])
        ->args([
            service(LoggerInterface::class),
            service('baldinof.roadrunner.grpc_worker'),
            service(GrpcServiceProvider::class),
        ]);

    $services->set(Dependencies::class)
        ->public() // Manually retrieved on the DIC in the Worker if the kernel has been rebooted
        ->args([
            service(MiddlewareStack::class),
            service(KernelRebootStrategyInterface::class),
        ]);

    $services->set(WorkerCommand::class)
        ->args([service(WorkerInterface::class)])
        ->autoconfigure();

    $services->set(GrpcWorkerCommand::class)
        ->args([service(GrpcWorkerInterface::class)])
        ->autoconfigure();

    $services->set(KernelHandler::class)
        ->args([
            service('kernel'),
            service(HttpMessageFactoryInterface::class),
            service(HttpFoundationFactoryInterface::class),
        ]);

    $services->set(MiddlewareStack::class)
        ->args([service(KernelHandler::class)]);

    $services->alias(IteratorRequestHandlerInterface::class, MiddlewareStack::class);

    $services->set(NativeSessionMiddleware::class);

    $services->set(HttpMessageFactoryInterface::class, PsrHttpFactory::class)
        ->args([
            service(ServerRequestFactoryInterface::class),
            service(StreamFactoryInterface::class),
            service(UploadedFileFactoryInterface::class),
            service(ResponseFactoryInterface::class),
        ]);

    $services->set(HttpFoundationFactoryInterface::class, HttpFoundationFactory::class);

    $services->set(MetricFactory::class)
        ->args([
            '$rrRpc' => '%env(default::RR_RPC)%',
            '$rrEnabled' => '%env(bool:default::RR)%',
            '$metricsEnabled' => '%baldinof_road_runner.metrics_enabled%',
            '$kernelProjectDir' => '%kernel.project_dir%',
        ]);

    $services->set(MetricsInterface::class)
        ->factory([service(MetricFactory::class), 'getMetricService']);

    $services->set(StreamedResponseListener::class)
        ->decorate('streamed_response_listener')
        ->args([
            service(StreamedResponseListener::class.'.inner'),
            '%env(bool:default::RR)%',
        ]);
};
