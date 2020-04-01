<?php

namespace Baldinof\RoadRunnerBundle\DependencyInjection;

use Baldinof\RoadRunnerBundle\Event\WorkerStartEvent;
use Baldinof\RoadRunnerBundle\EventListener\ConfigureVarDumperListener;
use Baldinof\RoadRunnerBundle\EventListener\DoctrineMongoDBListener;
use Baldinof\RoadRunnerBundle\EventListener\SentryListener;
use Baldinof\RoadRunnerBundle\Http\Middleware\BlackfireMiddleware;
use Baldinof\RoadRunnerBundle\Http\Middleware\NativeSessionMiddleware;
use Baldinof\RoadRunnerBundle\Http\Middleware\SentryMiddleware;
use Baldinof\RoadRunnerBundle\Worker\Configuration as WorkerConfiguration;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Zend\Diactoros\ResponseFactory as DiactorosResponseFactory;
use Zend\Diactoros\ServerRequestFactory as DiactorosServerRequestFactory;
use Zend\Diactoros\StreamFactory as DiactorosStreamFactory;
use Zend\Diactoros\UploadedFileFactory as DiactorosUploadedFileFactory;

class BaldinofRoadRunnerExtension extends Extension
{
    /**
     * @param array<string, mixed> $configs
     */
    public function load(array $configs, ContainerBuilder $container): void
    {
        $configuration = new Configuration();
        $config = $this->processConfiguration($configuration, $configs);

        $loader = new YamlFileLoader($container, new FileLocator(__DIR__.'/../../config'));
        $loader->load('services.yaml');

        if ($container->getParameter('kernel.debug')) {
            $this->loadDebug($container);
        }

        if ($config['should_reboot_kernel']) {
            $container->getDefinition(WorkerConfiguration::class)->setArgument(0, true);
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
            ->addArgument(new Reference('var_dumper.cloner'));
    }

    private function loadPsrFactories(ContainerBuilder $container): void
    {
        if ($this->hasAllDefinitions($container, ServerRequestFactoryInterface::class, StreamFactoryInterface::class, UploadedFileFactoryInterface::class, ResponseFactoryInterface::class)) {
            return;
        }

        // if nyholm/psr7 is installed ensure factories are registered
        if (class_exists(Psr17Factory::class)) {
            $container->register('nyholm.psr7.psr17_factory', Psr17Factory::class);

            $this->aliasIfNotExists($container, ServerRequestFactoryInterface::class, 'nyholm.psr7.psr17_factory');
            $this->aliasIfNotExists($container, StreamFactoryInterface::class, 'nyholm.psr7.psr17_factory');
            $this->aliasIfNotExists($container, UploadedFileFactoryInterface::class, 'nyholm.psr7.psr17_factory');
            $this->aliasIfNotExists($container, ResponseFactoryInterface::class, 'nyholm.psr7.psr17_factory');

            return;
        }

        // if zend-framework/diactoros is installed ensure factories are registered
        if (class_exists(DiactorosServerRequestFactory::class)) {
            $this->registerIfNotExists($container, ServerRequestFactoryInterface::class, DiactorosServerRequestFactory::class);
            $this->registerIfNotExists($container, StreamFactoryInterface::class, DiactorosStreamFactory::class);
            $this->registerIfNotExists($container, UploadedFileFactoryInterface::class, DiactorosUploadedFileFactory::class);
            $this->registerIfNotExists($container, ResponseFactoryInterface::class, DiactorosResponseFactory::class);

            return;
        }
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

        $beforeMiddlewares[] = NativeSessionMiddleware::class;

        $container->setParameter('baldinof_road_runner.middlewares.default', ['before' => $beforeMiddlewares, 'after' => $lastMiddlewares]);
    }

    private function hasAllDefinitions(ContainerBuilder $container, string ...$definitions): bool
    {
        foreach ($definitions as $definition) {
            if (!$container->hasDefinition($definition)) {
                return false;
            }
        }

        return true;
    }

    private function registerIfNotExists(ContainerBuilder $container, string $id, string $class): void
    {
        if ($container->has($id)) {
            return;
        }

        $container->register($id, $class);
    }

    private function aliasIfNotExists(ContainerBuilder $container, string $alias, string $id): void
    {
        if ($container->has($alias)) {
            return;
        }

        $container->setAlias($alias, $id);
    }
}
