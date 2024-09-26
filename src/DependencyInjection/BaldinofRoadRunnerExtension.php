<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\DependencyInjection;

use Baldinof\RoadRunnerBundle\Cache\KvCacheAdapter;
use Baldinof\RoadRunnerBundle\Event\WorkerStartEvent;
use Baldinof\RoadRunnerBundle\EventListener\DeclareMetricsListener;
use Baldinof\RoadRunnerBundle\Integration\Blackfire\BlackfireMiddleware;
use Baldinof\RoadRunnerBundle\Integration\Doctrine\DoctrineODMListener;
use Baldinof\RoadRunnerBundle\Integration\Doctrine\DoctrineORMMiddleware;
use Baldinof\RoadRunnerBundle\Integration\Sentry\SentryListener;
use Baldinof\RoadRunnerBundle\Integration\Sentry\SentryMiddleware;
use Baldinof\RoadRunnerBundle\Integration\Sentry\SentryTracingRequestListenerDecorator;
use Baldinof\RoadRunnerBundle\Integration\Symfony\ConfigureVarDumperListener;
use Baldinof\RoadRunnerBundle\Reboot\AlwaysRebootStrategy;
use Baldinof\RoadRunnerBundle\Reboot\ChainRebootStrategy;
use Baldinof\RoadRunnerBundle\Reboot\KernelRebootStrategyInterface;
use Baldinof\RoadRunnerBundle\Reboot\MaxJobsRebootStrategy;
use Baldinof\RoadRunnerBundle\Reboot\OnExceptionRebootStrategy;
use Baldinof\RoadRunnerBundle\Temporal\Attributes\AssignToWorker;
use Baldinof\RoadRunnerBundle\Temporal\ClientFactory;
use Baldinof\RoadRunnerBundle\Temporal\ClientOptionsFactory;
use Baldinof\RoadRunnerBundle\Temporal\ConnectionFactory;
use Baldinof\RoadRunnerBundle\Temporal\WorkerFactory;
use Baldinof\RoadRunnerBundle\Temporal\WorkerOptionsFactory;
use Baldinof\RoadRunnerBundle\Worker\TemporalWorker;
use Baldinof\RoadRunnerBundle\Worker\WorkerRegistryInterface;
use Doctrine\Persistence\ManagerRegistry;
use FTP\Connection;
use Psr\Log\LoggerInterface;
use Sentry\SentryBundle\EventListener\TracingRequestListener;
use Sentry\State\HubInterface;
use Spiral\Goridge\RPC\RPC;
use Spiral\Goridge\RPC\RPCInterface;
use Spiral\RoadRunner\Environment;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Spiral\RoadRunner\KeyValue\Factory;
use Spiral\RoadRunner\Metrics\Collector;
use Spiral\RoadRunner\Metrics\MetricsInterface;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\DependencyInjection\ChildDefinition;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Extension\Extension;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Temporal\Activity\ActivityInterface;
use Temporal\Client\ClientOptions;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowClientInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Worker\WorkerOptions;
use Temporal\WorkerFactory as TemporalWorkerFactory;
use Temporal\Workflow\WorkflowInterface;

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

        $loader = new PhpFileLoader($container, new FileLocator(__DIR__ . '/../../config'));
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

        if (!empty($config['kv']['storages'])) {
            $this->configureKv($config, $container);
        }

        if (interface_exists(ServiceInterface::class)) {
            $container->registerForAutoconfiguration(ServiceInterface::class)
                ->addTag('baldinof.roadrunner.grpc_service');
        }

        if (interface_exists(WorkflowClientInterface::class) && class_exists(WorkflowInterface::class)) {
            $this->configureTemporal($config, $container);
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
                    new Reference(SentryTracingRequestListenerDecorator::class . '.inner'),
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
        $container->setParameter('baldinof_road_runner.middlewares.default', ['before' => $beforeMiddlewares, 'after' => $lastMiddlewares]);
    }

    private function configureMetrics(array $config, ContainerBuilder $container): void
    {
        if (!interface_exists(MetricsInterface::class)) {
            throw new LogicException('RoadRunner Metrics support cannot be enabled as spiral/roadrunner-metrics is not installed. Try running "composer require spiral/roadrunner-metrics".');
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

    private function configureKv(array $config, ContainerBuilder $container): void
    {
        if (!class_exists(Factory::class)) {
            throw new LogicException('RoadRunner KV support cannot be enabled as spiral/roadrunner-kv is not installed. Try running "composer require spiral/roadrunner-kv".');
        }

        if (!class_exists(RPC::class)) {
            throw new LogicException('RoadRunner KV support cannot be enabled as spiral/goridge is not installed. Try running "composer require spiral/goridge".');
        }

        if (!interface_exists(AdapterInterface::class)) {
            throw new LogicException('RoadRunner KV support cannot be enabled as symfony/cache is not installed. Try running "composer require symfony/cache".');
        }

        $storages = $config['kv']['storages'];

        foreach ($storages as $storage) {
            $container->register('cache.adapter.roadrunner.kv_' . $storage, KvCacheAdapter::class)
                ->setFactory([KvCacheAdapter::class, 'createConnection'])
                ->setArguments([
                    '',
                    [ // Symfony overrides the first argument with the DSN, so we pass an empty string
                        'rpc' => $container->getDefinition(RPCInterface::class),
                        'storage' => $storage,
                    ],
                ]);
        }
    }

    private function configureTemporal(array $config, ContainerBuilder $container): void
    {
        $container->setParameter('temporal.config', $config['temporal']);
        $config = $config['temporal'];

        $workerAssignmentAttrExtractor = function (\ReflectionClass $class): ?string {
            $workers = array_map(function (\ReflectionAttribute $attr): ?string {
                return $attr->newInstance()->workerName;
            }, $class->getAttributes(AssignToWorker::class));

            return $workers[0] ?? null;
        };

        $registerServiceArray = function (array $services) use ($container): array {
            return array_map(function (string $id) use ($container): Reference {
                if (!$container->hasDefinition($id)) {
                    $container->register($id)
                        ->setAutoconfigured(true)
                        ->setAutowired(true);
                }
                return new Reference($id);
            }, $services);
        };

        $container->registerAttributeForAutoconfiguration(
            WorkflowInterface::class,
            /** @phpstan-ignore-next-line */
            function (ChildDefinition $defintion, WorkflowInterface $attribute, \ReflectionClass $reflection) use ($workerAssignmentAttrExtractor): void {
                $defintion->addTag(
                    'temporal.workflows',
                    ['worker_name' => $workerAssignmentAttrExtractor($reflection)]
                );
            }
        );

        $container->registerAttributeForAutoconfiguration(
            ActivityInterface::class,
            /** @phpstan-ignore-next-line */
            function (ChildDefinition $defintion, ActivityInterface $attribute, \ReflectionClass $reflection) use ($workerAssignmentAttrExtractor): void {
                $defintion->addTag(
                    'temporal.activities',
                    [
                        'worker_name' => $workerAssignmentAttrExtractor($reflection),
                        'prefix' => $attribute->prefix,
                    ]
                );
            }
        );

        $container->register(DataConverter::class, DataConverter::class)
            ->setArguments($registerServiceArray($config['data_converters'] ?? null));
        $container->setAlias('temporal.data_converter', DataConverter::class);
        $container->setAlias(DataConverterInterface::class, 'temporal.data_converter');

        foreach ($config['clients'] as $name => $options) {
            $container->register("temporal.client.{$name}.connection", Connection::class)
                ->setFactory([ConnectionFactory::class, 'createFromArray'])
                ->setArguments([
                    '$options' => [
                        'address' => $options['address'],
                        'crt' => $options['client_key'] ?? null,
                        'client_key' => $options['client_key'] ?? null,
                        'client_pem' => $options['client_pem'] ?? null,
                        'override_server_name' => $options['override_server_name'] ?? null,
                    ],
                ]);

            $container->register("temporal.client.{$name}.option", ClientOptions::class)
                ->setFactory([ClientOptionsFactory::class, 'createFromArray'])
                ->setArguments([
                    '$options' => [
                        'namespace' => $options['namespace'],
                        'identity' => $options['identity'] ?? null,
                        'query_reject_condition' => $options['query_reject_condition'] ?? null,
                    ],
                ]);

            $container->register("temporal.client.{$name}.interceptors", SimplePipelineProvider::class)
                ->setArguments([
                    $registerServiceArray($options['interceptors'] ?? []),
                ]);

            $container->register("temporal.client.{$name}.factory", ClientFactory::class)
                ->setArguments([
                    '$dataConverter' => new Reference('temporal.data_converter'),
                    '$clientOptions' => new Reference("temporal.client.{$name}.option"),
                    '$interceptors' => new Reference("temporal.client.{$name}.interceptors"),
                    '$connection' => new Reference("temporal.client.{$name}.connection"),
                ]);
            $container->register("temporal.client.{$name}", WorkflowClient::class)
                ->setFactory([new Reference("temporal.client.{$name}.factory"), '__invoke'])
                ->setAutoconfigured(true)
                ->setPublic(true)
                ->setAutowired(true);
        }

        if (!$container->hasDefinition("temporal.client.{$config['default_client']}")) {
            throw new \InvalidArgumentException(\sprintf('%s not found in service container', "temporal.client.{$config['default_client']}"));
        }
        $container->setAlias(WorkflowClientInterface::class, "temporal.client.{$config['default_client']}");

        $container->register(WorkerFactory::class, WorkerFactory::class)
            ->setArguments([new Reference(DataConverterInterface::class)])
            ->setAutoconfigured(true)
            ->setAutowired(true);

        $container->register(TemporalWorkerFactory::class)
            ->setFactory([
                new Reference(WorkerFactory::class),
                '__invoke',
            ]);

        $temporalWorker = $container->register(TemporalWorker::class, TemporalWorker::class)
            ->setArguments([
                new Reference(TemporalWorkerFactory::class),
            ]);

        foreach ($config['workers'] as $name => $options) {
            $container->register("temporal.worker.{$name}.interceptors", SimplePipelineProvider::class)
                ->setArguments([
                    $registerServiceArray($options['interceptors'] ?? []),
                ]);

            $container->register("temporal.worker.{$name}.option", WorkerOptions::class)
                ->setFactory([WorkerOptionsFactory::class, 'createFromArray'])
                ->setArguments([
                    '$options' => $options['options'] ?? [],
                ]);

            $temporalWorker->addMethodCall('addWorker', [
                $name,
                $options['queue'],
                new Reference("temporal.worker.{$name}.interceptors"),
                new Reference($options['exception_interceptor']),
                new Reference("temporal.worker.{$name}.option"),
            ]);
        }

        $workerRegistry = $container->findDefinition(WorkerRegistryInterface::class);
        $workerRegistry->addMethodCall('registerWorker', [
            Environment\Mode::MODE_TEMPORAL,
            new Reference(TemporalWorker::class),
        ]);
    }
}
