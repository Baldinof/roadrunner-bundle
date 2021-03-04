<?php

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Baldinof\RoadRunnerBundle\Command\WorkerCommand;
use Baldinof\RoadRunnerBundle\DependencyInjection\BaldinofRoadRunnerExtension;
use Baldinof\RoadRunnerBundle\EventListener\StreamedResponseListener;
use Baldinof\RoadRunnerBundle\Http\IteratorRequestHandlerInterface;
use Baldinof\RoadRunnerBundle\Http\KernelHandler;
use Baldinof\RoadRunnerBundle\Http\Middleware\NativeSessionMiddleware;
use Baldinof\RoadRunnerBundle\Http\MiddlewareStack;
use Baldinof\RoadRunnerBundle\Reboot\KernelRebootStrategyInterface;
use Baldinof\RoadRunnerBundle\Worker\Dependencies;
use Baldinof\RoadRunnerBundle\Worker\Worker;
use Baldinof\RoadRunnerBundle\Worker\WorkerInterface;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Psr\Log\LoggerInterface;
use Spiral\Goridge\RPC\RPC;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunner\Http\PSR7Worker;
use Spiral\RoadRunner\Metrics\Metrics;
use Spiral\RoadRunner\Metrics\MetricsInterface;
use Spiral\RoadRunner\Worker as RoadRunnerWorker;
use Spiral\RoadRunner\WorkerInterface as RoadRunnerWorkerInterface;
use Symfony\Bridge\PsrHttpMessage\Factory\HttpFoundationFactory;
use Symfony\Bridge\PsrHttpMessage\Factory\PsrHttpFactory;
use Symfony\Bridge\PsrHttpMessage\HttpFoundationFactoryInterface;
use Symfony\Bridge\PsrHttpMessage\HttpMessageFactoryInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

// Polyfill of the `service()` function introduced in Symfony 5.1 when using older version
if (!\function_exists('Symfony\Component\DependencyInjection\Loader\Configurator\service')) {
    function service(string $id): ReferenceConfigurator
    {
        return ref($id);
    }
}

return static function (ContainerConfigurator $container) {
    $services = $container->services();

    // RoadRuner services
    $services->set(EnvironmentInterface::class)
        ->factory([Environment::class, 'fromGlobals']);

    $services->set(RoadRunnerWorkerInterface::class, RoadRunnerWorker::class)
        ->factory([RoadRunnerWorker::class, 'createFromEnvironment'])
        ->args([service(EnvironmentInterface::class), false]);

    $services->set(PSR7Worker::class)
        ->args([
            service(RoadRunnerWorkerInterface::class),
            service(ServerRequestFactoryInterface::class),
            service(StreamFactoryInterface::class),
            service(UploadedFileFactoryInterface::class),
        ]);

    $services->set(RPCInterface::class)
        ->factory([RPC::class, 'create'])
        ->args([
            expr(sprintf('service("%s").getRPCAddress()', EnvironmentInterface::class)),
        ]);

    $services->set(MetricsInterface::class, Metrics::class)
        ->args([service(RPCInterface::class)]);

    // Bundle services
    $services->set(WorkerInterface::class, Worker::class)
        ->tag('monolog.logger', ['channel' => BaldinofRoadRunnerExtension::MONOLOG_CHANNEL])
        ->args([
            service('kernel'),
            service(LoggerInterface::class),
            service(PSR7Worker::class),
        ]);

    $services->set(Dependencies::class)
        ->public() // Manually retrieved on the DIC in the Worker if the kernel has been rebooted
        ->args([
            service(MiddlewareStack::class),
            service(KernelRebootStrategyInterface::class),
            service(EventDispatcherInterface::class),
        ]);

    $services->set(WorkerCommand::class)
        ->args([service(WorkerInterface::class)])
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

    $services->set(StreamedResponseListener::class)
        ->decorate('streamed_response_listener')
        ->args([
            service(StreamedResponseListener::class.'.inner'),
            '%env(bool:default::RR)%',
        ]);
};
