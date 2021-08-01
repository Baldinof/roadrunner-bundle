<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Baldinof\RoadRunnerBundle\Command\WorkerCommand;
use Baldinof\RoadRunnerBundle\DependencyInjection\BaldinofRoadRunnerExtension;
use Baldinof\RoadRunnerBundle\Worker\HttpWorker;
use Baldinof\RoadRunnerBundle\Worker\TemporalWorker;
use Baldinof\RoadRunnerBundle\Worker\WorkerResolver;
use Baldinof\RoadRunnerBundle\Worker\WorkerResolverInterface;
use Spiral\RoadRunner\EnvironmentInterface;
use Temporal\Client\GRPC\ServiceClient;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Worker\WorkerFactoryInterface;
use Temporal\WorkerFactory;
use function function_exists;

// Polyfill of the `service()` function introduced in Symfony 5.1 when using older version
if (!function_exists('Symfony\Component\DependencyInjection\Loader\Configurator\service')) {
    function service(string $id): ReferenceConfigurator
    {
        return ref($id);
    }
}

return static function (ContainerConfigurator $container) {
    $container->parameters()
        ->set('baldinof_road_runner.temporal_address', '127.0.0.1:7233');

    $services = $container->services();

    $services->set(WorkerFactoryInterface::class)
        ->factory([WorkerFactory::class, 'create']);

    $services->set(TemporalWorker::class)
        ->public() // Manually retrieved on the DIC in the Worker if the kernel has been rebooted
        ->tag('monolog.logger', ['channel' => BaldinofRoadRunnerExtension::MONOLOG_CHANNEL])
        ->args([
            service('kernel'),
            service(WorkerFactoryInterface::class),
            tagged_iterator('baldinof_road_runner.temporal_workflows'),
            tagged_iterator('baldinof_road_runner.temporal_activities'),
        ]);

    $services->set(WorkerResolverInterface::class, WorkerResolver::class)
        ->args([
            service(EnvironmentInterface::class),
            service(HttpWorker::class),
            service(TemporalWorker::class),
        ]);

    $services->set(WorkerCommand::class)
        ->args([
            service(WorkerResolverInterface::class),
            service(EnvironmentInterface::class),
        ])
        ->autoconfigure();

    $services
        ->set(WorkflowClientInterface::class, WorkflowClient::class)
        ->args([
            service(ServiceClient::class),
        ]);

    $services
        ->set(ServiceClient::class)
        ->factory([ServiceClient::class, 'create'])
        ->args([
            param('baldinof_road_runner.temporal_address')
        ]);
};
