<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Baldinof\RoadRunnerBundle\Command\WorkerCommand;
use Baldinof\RoadRunnerBundle\DependencyInjection\BaldinofRoadRunnerExtension;
use Baldinof\RoadRunnerBundle\EventListener\StreamedResponseListener;
use Baldinof\RoadRunnerBundle\Http\IteratorRequestHandlerInterface;
use Baldinof\RoadRunnerBundle\Http\KernelHandler;
use Baldinof\RoadRunnerBundle\Http\Middleware\NativeSessionMiddleware;
use Baldinof\RoadRunnerBundle\Http\MiddlewareStack;
use Baldinof\RoadRunnerBundle\Metric\MetricFactory;
use Baldinof\RoadRunnerBundle\Worker\Configuration;
use Baldinof\RoadRunnerBundle\Worker\Dependencies;
use Baldinof\RoadRunnerBundle\Worker\Worker;
use Baldinof\RoadRunnerBundle\Worker\WorkerInterface;
use Spiral\Goridge\RelayInterface;
use Spiral\Goridge\SocketRelay;
use Spiral\RoadRunner\MetricsInterface;
use Spiral\RoadRunner\PSR7Client;
use Spiral\RoadRunner\Worker as RoadRunnerWorker;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;

return static function (ContainerConfigurator $container) {
    $services = $container->services();
    $services->set(RoadRunnerWorker::class)
        ->autowire();

    $services->set(PSR7Client::class)
        ->autowire();

    $services->set(RelayInterface::class, SocketRelay::class)
        ->args([
            '%kernel.project_dir%/var/roadrunner.sock',
            null,
            SocketRelay::SOCK_UNIX,
        ]);

    $services->set(Configuration::class);

    $services->set(WorkerInterface::class, Worker::class)
        ->autowire()
        ->arg('$kernel', ref('kernel'))
        ->tag('monolog.logger', ['channel' => BaldinofRoadRunnerExtension::MONOLOG_CHANNEL]);

    $services->set(Dependencies::class)
        ->autowire()
        ->public() // Manually retrieved on the DIC in the Worker if the kernel has been rebooted
        ->arg('$requestHandler', ref(MiddlewareStack::class));

    $services->set(WorkerCommand::class)
        ->autowire()
        ->autoconfigure();

    $services->set(KernelHandler::class)
        ->autowire()
        ->arg('$kernel', ref('kernel'));

    $services->set(MiddlewareStack::class)
        ->autowire()
        ->args([ref(KernelHandler::class)]);

    $services->alias(IteratorRequestHandlerInterface::class, MiddlewareStack::class);

    $services->set(NativeSessionMiddleware::class);

    $services->set(HttpMessageFactoryInterface::class, PsrHttpFactory::class)
        ->args([
            ref('baldinof_road_runner.psr17.server_request_factory'),
            ref('baldinof_road_runner.psr17.stream_factory'),
            ref('baldinof_road_runner.psr17.uploaded_file_factory'),
            ref('baldinof_road_runner.psr17.response_factory'),
        ]);
    $services->set(HttpFoundationFactoryInterface::class, HttpFoundationFactory::class);

    $services->set(MetricFactory::class)
        ->autowire()
        ->args([
            '$rrRpc' => '%env(default::RR_RPC)%',
            '$rrEnabled' => '%env(bool:default::RR)%',
            '$metricsEnabled' => '%baldinof_road_runner.metrics_enabled%',
            '$kernelProjectDir' => '%kernel.project_dir%',
        ]);

    $services->set(MetricsInterface::class)
        ->factory([ref(MetricFactory::class), 'getMetricService']);

    $services->set(StreamedResponseListener::class)
        ->decorate('streamed_response_listener')
        ->args([
            ref(StreamedResponseListener::class.'.inner'),
            '%env(bool:default::RR)%',
        ]);
};
