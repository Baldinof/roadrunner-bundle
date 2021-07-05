<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Baldinof\RoadRunnerBundle\Command\WorkerCommand;
use Baldinof\RoadRunnerBundle\DependencyInjection\BaldinofRoadRunnerExtension;
use Baldinof\RoadRunnerBundle\Helpers\RPCFactory;
use Baldinof\RoadRunnerBundle\Http\KernelHandler;
use Baldinof\RoadRunnerBundle\Http\MiddlewareStack;
use Baldinof\RoadRunnerBundle\Http\RequestHandlerInterface;
use Baldinof\RoadRunnerBundle\Integration\PHP\NativeSessionMiddleware;
use Baldinof\RoadRunnerBundle\Integration\Symfony\StreamedResponseListener;
use Baldinof\RoadRunnerBundle\Reboot\KernelRebootStrategyInterface;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\HttpFoundationWorker;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\HttpFoundationWorkerInterface;
use Baldinof\RoadRunnerBundle\Worker\Dependencies;
use Baldinof\RoadRunnerBundle\Worker\HttpWorker;
use Baldinof\RoadRunnerBundle\Worker\WorkerInterface;
use Psr\Log\LoggerInterface;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\EnvironmentInterface;
use Spiral\RoadRunner\Http\HttpWorker as RoadRunnerHttpWorker;
use Spiral\RoadRunner\Http\HttpWorkerInterface as RoadRunnerHttpWorkerInterface;
use Spiral\RoadRunner\Metrics\Metrics;
use Spiral\RoadRunner\Metrics\MetricsInterface;
use Spiral\RoadRunner\Worker as RoadRunnerWorker;
use Spiral\RoadRunner\WorkerInterface as RoadRunnerWorkerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

// Polyfill of the `service()` function introduced in Symfony 5.1 when using older version
if (!\function_exists('Symfony\Component\DependencyInjection\Loader\Configurator\service')) {
    function service(string $id): ReferenceConfigurator
    {
        return ref($id);
    }
}

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

    $services->set(RoadRunnerHttpWorkerInterface::class, RoadRunnerHttpWorker::class)
        ->args([service(RoadRunnerWorkerInterface::class)]);

    $services->set(RPCInterface::class)
        ->factory([RPCFactory::class, 'fromEnvironment'])
        ->args([service(EnvironmentInterface::class)]);

    $services->set(MetricsInterface::class, Metrics::class)
        ->args([service(RPCInterface::class)]);

    // Bundle services
    $services->set(HttpFoundationWorkerInterface::class, HttpFoundationWorker::class)
        ->args([service(RoadRunnerHttpWorkerInterface::class)]);

    $services->set(HttpWorker::class)
        ->public() // Manually retrieved on the DIC in the Worker if the kernel has been rebooted
        ->tag('monolog.logger', ['channel' => BaldinofRoadRunnerExtension::MONOLOG_CHANNEL])
        ->args([
            service('kernel'),
            service(LoggerInterface::class),
            service(HttpFoundationWorkerInterface::class),
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
        ]);

    $services->set(MiddlewareStack::class)
        ->args([service(KernelHandler::class)]);

    $services->alias(RequestHandlerInterface::class, MiddlewareStack::class);

    $services->set(NativeSessionMiddleware::class);

    $services->set(StreamedResponseListener::class)
        ->decorate('streamed_response_listener')
        ->args([
            service(StreamedResponseListener::class.'.inner'),
            '%env(default::RR_MODE)%',
        ]);
};
