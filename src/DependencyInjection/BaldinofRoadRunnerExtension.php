<?php

namespace Baldinof\RoadRunnerBundle\DependencyInjection;

use Baldinof\RoadRunnerBundle\Event\WorkerStartEvent;
use Baldinof\RoadRunnerBundle\EventListener\ConfigureVarDumperListener;
use Baldinof\RoadRunnerBundle\EventListener\DoctrineMongoDBListener;
use Baldinof\RoadRunnerBundle\EventListener\SentryListener;
use Baldinof\RoadRunnerBundle\Http\Middleware\BlackfireMiddleware;
use Baldinof\RoadRunnerBundle\Http\Middleware\DoctrineMiddleware;
use Baldinof\RoadRunnerBundle\Http\Middleware\NativeSessionMiddleware;
use Baldinof\RoadRunnerBundle\Http\Middleware\SentryMiddleware;
use Baldinof\RoadRunnerBundle\Reboot\AlwaysRebootStrategy;
use Baldinof\RoadRunnerBundle\Reboot\KernelRebootStrategyInterface;
use Baldinof\RoadRunnerBundle\Reboot\OnExceptionRebootStrategy;
use Baldinof\RoadRunnerBundle\Worker\Configuration as WorkerConfiguration;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;

class BaldinofRoadRunnerExtension extends Extension
{
    const MONOLOG_CHANNEL = 'roadrunner';

    /**
     * @param array<string, mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.php');

        if ($container->getParameter('kernel.debug')) {
            $this->loadDebug($container);
        }

        if ($config['should_reboot_kernel'] || $config['kernel_reboot']['strategy'] === Configuration::KERNEL_REBOOT_STRATEGY_ALWAYS) {
            $container->getDefinition(WorkerConfiguration::class)
                ->setArgument(0, true)
                ->setDeprecated('baldinof/roadrunner-bundle', '1.3.0', '');
            $container->register(KernelRebootStrategyInterface::class, AlwaysRebootStrategy::class);
        } else {
            $container
                ->register(KernelRebootStrategyInterface::class, OnExceptionRebootStrategy::class)
                ->setArgument('$allowedExceptions', $config['kernel_reboot']['allowed_exceptions'])
                ->setAutoconfigured(true)
                ->addTag('monolog.logger', ['channel' => self::MONOLOG_CHANNEL])
                ->setAutowired(true);
        }

        $container->setParameter('baldinof_road_runner.middlewares', $config['middlewares']);
        $container->setParameter('baldinof_road_runner.metrics_enabled', $config['metrics_enabled']);

        $this->loadPsrFactories($container);
        $this->loadIntegrations($container, $config);
    }

    private function loadDebug(ContainerBuilder $container): void
    {
        $container->register(ConfigureVarDumperListener::class, ConfigureVarDumperListener::class)
            ->addTag('kernel.event_listener', ['event' => WorkerStartEvent::class])
            ->addArgument(new Reference('data_collector.dump'))
            ->addArgument(new Reference('var_dumper.cloner'))
            ->addArgument('%env(bool:default::RR)%');
    }

    private function loadPsrFactories(ContainerBuilder $container): void
    {
        $container->addDefinitions([
            'baldinof_road_runner.psr17.server_request_factory' => new Definition(),
            'baldinof_road_runner.psr17.stream_factory' => new Definition(),
            'baldinof_road_runner.psr17.uploaded_file_factory' => new Definition(),
            'baldinof_road_runner.psr17.response_factory' => new Definition(),
        ]);
    }

    private function loadIntegrations(ContainerBuilder $container, array $config): void
    {
        $beforeMiddlewares = [];
        $lastMiddlewares = [];

        if (!$config['default_integrations']) {
            $container->setParameter('baldinof_road_runner.middlewares.default', ['before' => $beforeMiddlewares, 'after' => $lastMiddlewares]);

            return;
        }

        $bundles = $container->getParameter('kernel.bundles');

        if (class_exists(\BlackfireProbe::class)) {
            $container->register(BlackfireMiddleware::class);
            $beforeMiddlewares[] = BlackfireMiddleware::class;
        }

        if (isset($bundles['SentryBundle'])) {
            $container->autowire(SentryMiddleware::class);

            $container
                ->autowire(SentryListener::class)
                ->setAutoconfigured(true);

            $beforeMiddlewares[] = SentryMiddleware::class;
        }

        if (isset($bundles['DoctrineMongoDBBundle'])) {
            $container
                ->register(DoctrineMongoDBListener::class)
                ->addArgument(new Reference('service_container'))
                ->setAutoconfigured(true);
        }

        if (isset($bundles['DoctrineBundle'])) {
            $container->autowire(DoctrineMiddleware::class);
            $beforeMiddlewares[] = DoctrineMiddleware::class;
        }

        $beforeMiddlewares[] = NativeSessionMiddleware::class;

        $container->setParameter('baldinof_road_runner.middlewares.default', ['before' => $beforeMiddlewares, 'after' => $lastMiddlewares]);
    }

    private function registerAbstractIfNotExists(ContainerBuilder $container, string $id): void
    {
        if ($container->has($id)) {
            return;
        }

        $container->register($id)->setAbstract(true);
    }
}
