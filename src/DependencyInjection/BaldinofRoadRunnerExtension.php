<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\DependencyInjection;

use Baldinof\RoadRunnerBundle\Event\WorkerStartEvent;
use Baldinof\RoadRunnerBundle\EventListener\DeclareMetricsListener;
use Baldinof\RoadRunnerBundle\Integration\Blackfire\BlackfireMiddleware;
use Baldinof\RoadRunnerBundle\Integration\Doctrine\DoctrineODMListener;
use Baldinof\RoadRunnerBundle\Integration\Doctrine\DoctrineORMMiddleware;
use Baldinof\RoadRunnerBundle\Integration\PHP\NativeSessionMiddleware;
use Baldinof\RoadRunnerBundle\Integration\Sentry\SentryListener;
use Baldinof\RoadRunnerBundle\Integration\Sentry\SentryMiddleware;
use Baldinof\RoadRunnerBundle\Integration\Sentry\SentryTracingRequestListenerDecorator;
use Baldinof\RoadRunnerBundle\Integration\Symfony\ConfigureVarDumperListener;
use Baldinof\RoadRunnerBundle\Reboot\AlwaysRebootStrategy;
use Baldinof\RoadRunnerBundle\Reboot\ChainRebootStrategy;
use Baldinof\RoadRunnerBundle\Reboot\KernelRebootStrategyInterface;
use Baldinof\RoadRunnerBundle\Reboot\MaxJobsRebootStrategy;
use Baldinof\RoadRunnerBundle\Reboot\OnExceptionRebootStrategy;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Sentry\SentryBundle\EventListener\TracingRequestListener;
use Sentry\State\HubInterface;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Spiral\RoadRunner\Metrics\Collector;
use Spiral\RoadRunner\Metrics\MetricsInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

class BaldinofRoadRunnerExtension extends Extension
{
    public const MONOLOG_CHANNEL = 'roadrunner';

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

        $strategies = $config['kernel_reboot']['strategy'];
        $strategyServices = [];

        foreach ($strategies as $strategy) {
            if ($strategy === Configuration::KERNEL_REBOOT_STRATEGY_ALWAYS) {
                $strategyService = (new Definition(AlwaysRebootStrategy::class))
                    ->setAutoconfigured(true);
            } elseif ($strategy === Configuration::KERNEL_REBOOT_STRATEGY_ON_EXCEPTION) {
                $strategyService = (new Definition(OnExceptionRebootStrategy::class))
                    ->addArgument($config['kernel_reboot']['allowed_exceptions'])
                    ->addArgument(new Reference(LoggerInterface::class))
                    ->setAutoconfigured(true)
                    ->addTag('monolog.logger', ['channel' => self::MONOLOG_CHANNEL]);
            } elseif ($strategy === Configuration::KERNEL_REBOOT_STRATEGY_MAX_JOBS) {
                $strategyService = (new Definition(MaxJobsRebootStrategy::class))
                    ->addArgument($config['kernel_reboot']['max_jobs'])
                    ->addArgument($config['kernel_reboot']['max_jobs_dispersion'])
                    ->setAutoconfigured(true);
            } else {
                $strategyService = new Reference($strategy);
            }

            $strategyServices[] = $strategyService;
        }

        if (\count($strategyServices) > 1) {
            $container->register(KernelRebootStrategyInterface::class, ChainRebootStrategy::class)
                ->setArguments([$strategyServices]);
        } else {
            $strategy = $strategyServices[0];

            if ($strategy instanceof Reference) {
                $container->setAlias(KernelRebootStrategyInterface::class, (string) $strategy);
            } else {
                $container->setDefinition(KernelRebootStrategyInterface::class, $strategy);
            }
        }

        $container->setParameter('baldinof_road_runner.middlewares', $config['middlewares']);

        $this->loadIntegrations($container, $config);

        if ($config['metrics']['enabled']) {
            $this->configureMetrics($config, $container);
        }

        if (interface_exists(ServiceInterface::class)) {
            $container->registerForAutoconfiguration(ServiceInterface::class)
                ->addTag('baldinof.roadrunner.grpc_service');
        }
    }

    private function loadDebug(ContainerBuilder $container): void
    {
        $container->register(ConfigureVarDumperListener::class, ConfigureVarDumperListener::class)
            ->addTag('kernel.event_listener', ['event' => WorkerStartEvent::class])
            ->addArgument(new Reference('data_collector.dump'))
            ->addArgument(new Reference('var_dumper.cloner'))
            ->addArgument('%env(default::RR_MODE)%');
    }

    private function loadIntegrations(ContainerBuilder $container, array $config): void
    {
        $beforeMiddlewares = [];
        $lastMiddlewares = [];

        if (!$config['default_integrations']) {
            $container->setParameter('baldinof_road_runner.middlewares.default', ['before' => $beforeMiddlewares, 'after' => $lastMiddlewares]);

            return;
        }

        /** @var array */
        $bundles = $container->getParameter('kernel.bundles');

        if (class_exists(\BlackfireProbe::class)) {
            $container->register(BlackfireMiddleware::class);
            $beforeMiddlewares[] = BlackfireMiddleware::class;
        }

        if (isset($bundles['SentryBundle'])) {
            $container
                ->register(SentryMiddleware::class)
                ->addArgument(new Reference(HubInterface::class));

            $container
                ->register(SentryListener::class)
                ->addArgument(new Reference(HubInterface::class))
                ->setAutoconfigured(true);

            $container
                ->register(SentryTracingRequestListenerDecorator::class)
                ->setDecoratedService(TracingRequestListener::class, null, 0, ContainerInterface::IGNORE_ON_INVALID_REFERENCE)
                ->setArguments([
                    new Reference(SentryTracingRequestListenerDecorator::class.'.inner'),
                    new Reference(HubInterface::class),
                ]);

            $beforeMiddlewares[] = SentryMiddleware::class;
        }

        if (isset($bundles['DoctrineMongoDBBundle'])) {
            $container
                ->register(DoctrineODMListener::class)
                ->addArgument(new Reference('service_container'))
                ->setAutoconfigured(true);
        }

        if (isset($bundles['DoctrineBundle'])) {
            $container
                ->register(DoctrineORMMiddleware::class)
                ->addArgument(new Reference(ManagerRegistry::class))
                ->addArgument(new Reference('service_container'))
                ->addArgument(new Reference(EventDispatcherInterface::class))
                ->addArgument(new Reference(LoggerInterface::class))
                ->addTag('monolog.logger', ['channel' => self::MONOLOG_CHANNEL])
            ;

            $beforeMiddlewares[] = DoctrineORMMiddleware::class;
        }

        // @phpstan-ignore-next-line - PHPStan says this is always true, but the constant value depends on the currently installed Symfony version
        if (Kernel::VERSION_ID < 50400) {
            $beforeMiddlewares[] = NativeSessionMiddleware::class;
        }

        $container->setParameter('baldinof_road_runner.middlewares.default', ['before' => $beforeMiddlewares, 'after' => $lastMiddlewares]);
    }

    private function configureMetrics(array $config, ContainerBuilder $container): void
    {
        if (!interface_exists(MetricsInterface::class)) {
            throw new LogicException('RoadRunner Metrics support cannot be enabled as the spiral/roadrunner-metrics is not installed. Try running "composer require spiral/roadrunner-metrics".');
        }

        $listenerDef = $container->register(DeclareMetricsListener::class)
            ->setAutoconfigured(true)
            ->addArgument(new Reference(MetricsInterface::class));

        foreach ($config['metrics']['collect'] as $name => $metric) {
            $def = new Definition(Collector::class);
            $def->setFactory([Collector::class, $metric['type']]);

            $id = "baldinof_road_runner.metrics.internal.collector.$name";
            $container->setDefinition($id, $def);

            $listenerDef->addMethodCall('addCollector', [$name, $metric]);
        }
    }
}
