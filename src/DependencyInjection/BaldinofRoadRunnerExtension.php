<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\DependencyInjection;

use Baldinof\RoadRunnerBundle\Cache\KvCacheAdapter;
use Baldinof\RoadRunnerBundle\DataCollector\TemporalDataCollector;
use Baldinof\RoadRunnerBundle\Event\WorkerStartEvent;
use Baldinof\RoadRunnerBundle\EventListener\DeclareMetricsListener;
use Baldinof\RoadRunnerBundle\Integration\Blackfire\BlackfireMiddleware;
use Baldinof\RoadRunnerBundle\Integration\Doctrine\DoctrineODMListener;
use Baldinof\RoadRunnerBundle\Integration\Doctrine\DoctrineORMIntegration;
use Baldinof\RoadRunnerBundle\Integration\Doctrine\DoctrineORMMiddleware;
use Baldinof\RoadRunnerBundle\Integration\Sentry\SentryListener;
use Baldinof\RoadRunnerBundle\Integration\Sentry\SentryMiddleware;
use Baldinof\RoadRunnerBundle\Integration\Sentry\SentryTracingRequestListenerDecorator;
use Baldinof\RoadRunnerBundle\Integration\Symfony\ConfigureVarDumperListener;
use Baldinof\RoadRunnerBundle\Integration\Xdebug\XdebugProxy;
use Baldinof\RoadRunnerBundle\Integration\Xdebug\XdebugTriggerMiddleware;
use Baldinof\RoadRunnerBundle\Reboot\AlwaysRebootStrategy;
use Baldinof\RoadRunnerBundle\Reboot\ChainRebootStrategy;
use Baldinof\RoadRunnerBundle\Reboot\KernelRebootStrategyInterface;
use Baldinof\RoadRunnerBundle\Reboot\MaxJobsRebootStrategy;
use Baldinof\RoadRunnerBundle\Reboot\MemoryRebootStrategy;
use Baldinof\RoadRunnerBundle\Reboot\OnExceptionRebootStrategy;
use Baldinof\RoadRunnerBundle\Temporal\Attributes\AssignToWorker;
use Baldinof\RoadRunnerBundle\Temporal\ClientOptionsFactory;
use Baldinof\RoadRunnerBundle\Temporal\Command\DebugClientsCommand;
use Baldinof\RoadRunnerBundle\Temporal\Command\DebugWorkersCommand;
use Baldinof\RoadRunnerBundle\Temporal\Interceptors\CollectingClientInterceptor;
use Baldinof\RoadRunnerBundle\Temporal\Interceptors\DoctrineORMInterceptor;
use Baldinof\RoadRunnerBundle\Temporal\Interceptors\RebootKernelInterceptor;
use Baldinof\RoadRunnerBundle\Temporal\ServiceClientConfig;
use Baldinof\RoadRunnerBundle\Temporal\ServiceClientFactory;
use Baldinof\RoadRunnerBundle\Worker\TemporalWorker;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Sentry\SentryBundle\EventListener\TracingRequestListener;
use Sentry\State\HubInterface;
use Spiral\Goridge\RPC\RPC;
use Spiral\Goridge\RPC\RPCInterface;
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
use Symfony\Component\DependencyInjection\Extension\PrependExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Temporal\Activity\ActivityInterface;
use Temporal\Client\ClientOptions;
use Temporal\Client\GRPC\ServiceClientInterface;
use Temporal\Client\ScheduleClient;
use Temporal\Client\ScheduleClientInterface;
use Temporal\Client\WorkflowClient;
use Temporal\Client\WorkflowClientInterface;
use Temporal\DataConverter\BinaryConverter;
use Temporal\DataConverter\DataConverter;
use Temporal\DataConverter\DataConverterInterface;
use Temporal\DataConverter\JsonConverter;
use Temporal\DataConverter\NullConverter;
use Temporal\DataConverter\ProtoConverter;
use Temporal\DataConverter\ProtoJsonConverter;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\WorkerFactory as TemporalWorkerFactory;
use Temporal\Workflow\WorkflowInterface;

class BaldinofRoadRunnerExtension extends Extension implements PrependExtensionInterface
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
            } elseif ($strategy === Configuration::KERNEL_REBOOT_STRATEGY_MEMORY) {
                $strategyService = (new Definition(MemoryRebootStrategy::class))
                    ->addArgument($config['kernel_reboot']['memory_threshold_mb'])
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
        if (interface_exists(ServiceInterface::class)) {
            $container->setParameter('baldinof_road_runner.interceptors', $config['interceptors']);
        }

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

        if (interface_exists(WorkflowClientInterface::class)) {
            $this->configureTemporal($config['temporal'], $container);
        }
    }

    public function prepend(ContainerBuilder $container): void
    {
        $container->prependExtensionConfig('twig', [
            'paths' => [__DIR__.'/../../templates' => 'BaldinofRoadRunner'],
        ]);
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
        $beforeInterceptors = [];
        $afterInterceptors = [];

        if (!$config['default_integrations']) {
            $container->setParameter('baldinof_road_runner.middlewares.default', ['before' => $beforeMiddlewares, 'after' => $lastMiddlewares]);
            if (interface_exists(ServiceInterface::class)) {
                $container->setParameter('baldinof_road_runner.interceptors.default', ['before' => $beforeInterceptors, 'after' => $afterInterceptors]);
            }

            return;
        }

        // ext-xdebug might not be there when building the container, so let's not test for the extension to be loaded.
        if ($container->getParameter('kernel.debug')) {
            $container
                ->register(XdebugProxy::class)
                ->setAutoconfigured(true);
            $container
                ->register(XdebugTriggerMiddleware::class)
                ->addArgument(new Reference(XdebugProxy::class))
                ->setAutoconfigured(true);

            // Xdebug should always be the first middleware/interceptor to be executed
            $beforeMiddlewares[] = XdebugTriggerMiddleware::class;
            $beforeInterceptors[] = XdebugTriggerMiddleware::class;
        }

        /** @var array<string,mixed> $bundles */
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
            $beforeInterceptors[] = SentryMiddleware::class;
        }

        if (isset($bundles['DoctrineMongoDBBundle'])) {
            $container
                ->register(DoctrineODMListener::class)
                ->addArgument(new Reference('service_container'))
                ->setAutoconfigured(true);
        }

        if (isset($bundles['DoctrineBundle'])) {
            $container
                ->register(DoctrineORMIntegration::class)
                ->addArgument(new Reference(ManagerRegistry::class))
                ->addArgument(new Reference('service_container'))
                ->addArgument(new Reference(EventDispatcherInterface::class))
                ->addArgument(new Reference(LoggerInterface::class))
                ->addTag('monolog.logger', ['channel' => self::MONOLOG_CHANNEL])
            ;

            $container
                ->register(DoctrineORMMiddleware::class)
                ->addArgument(new Reference(DoctrineORMIntegration::class))
            ;

            $beforeMiddlewares[] = DoctrineORMMiddleware::class;
            $beforeInterceptors[] = DoctrineORMMiddleware::class;
        }

        $container->setParameter('baldinof_road_runner.middlewares.default', ['before' => $beforeMiddlewares, 'after' => $lastMiddlewares]);
        if (interface_exists(ServiceInterface::class)) {
            $container->setParameter('baldinof_road_runner.interceptors.default', ['before' => $beforeInterceptors, 'after' => $afterInterceptors]);
        }
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
            $container->register('cache.adapter.roadrunner.kv_'.$storage, KvCacheAdapter::class)
                ->setFactory([KvCacheAdapter::class, 'createConnection'])
                ->setArguments([
                    '', // Symfony overrides the first argument with the DSN, so we pass an empty string
                    [
                        'rpc' => $container->getDefinition(RPCInterface::class),
                        'storage' => $storage,
                    ],
                ]);
        }
    }

    private function configureTemporal(array $config, ContainerBuilder $container): void
    {
        /** @var array<string,mixed> $bundles */
        $bundles = $container->getParameter('kernel.bundles');

        $container->setParameter('temporal.config', $config);
        $defaultInterceptors = [];

        $container->registerAttributeForAutoconfiguration(
            WorkflowInterface::class,
            /** @phpstan-ignore-next-line */
            function (ChildDefinition $definition, WorkflowInterface $attribute, \ReflectionClass $reflection): void {
                $definition->addTag(
                    'temporal.workflow',
                    ['worker_name' => $this->getTemporalWorkerName($reflection)]
                );
            }
        );

        $container->registerAttributeForAutoconfiguration(
            ActivityInterface::class,
            /** @phpstan-ignore-next-line */
            function (ChildDefinition $definition, ActivityInterface $attribute, \ReflectionClass $reflection): void {
                $definition->addTag(
                    'temporal.activity',
                    [
                        'worker_name' => $this->getTemporalWorkerName($reflection),
                        'prefix' => $attribute->prefix,
                    ]
                );
            }
        );

        $container->register('temporal.data_converter.null', NullConverter::class)
            ->addTag('temporal.data_converter');
        $container->register('temporal.data_converter.binary', BinaryConverter::class)
            ->addTag('temporal.data_converter');
        $container->register('temporal.data_converter.proto_json', ProtoJsonConverter::class)
            ->addTag('temporal.data_converter');
        $container->register('temporal.data_converter.proto', ProtoConverter::class)
            ->addTag('temporal.data_converter');
        $container->register('temporal.data_converter.json', JsonConverter::class)
            ->addTag('temporal.data_converter');

        $container->register(DataConverter::class, DataConverter::class)
            ->setArguments([]); // will be overwritten in TemporalCompilerPass

        $container->setAlias('temporal.data_converter', DataConverter::class);
        $container->setAlias(DataConverterInterface::class, 'temporal.data_converter');

        $container
            ->register(RebootKernelInterceptor::class)
            ->addArgument(new Reference(KernelInterface::class))
            ->addArgument(new Reference(LoggerInterface::class))
            ->addTag('monolog.logger', ['channel' => BaldinofRoadRunnerExtension::MONOLOG_CHANNEL])
        ;

        $defaultInterceptors[] = RebootKernelInterceptor::class;

        if (isset($bundles['DoctrineBundle'])) {
            $container
                ->register(DoctrineORMInterceptor::class)
                ->addArgument(new Reference(DoctrineORMIntegration::class))
            ;

            $defaultInterceptors[] = DoctrineORMInterceptor::class;
        }

        $container->setParameter('temporal.default_interceptors', $defaultInterceptors);

        $collectingInterceptor = null;
        if ($container->getParameter('kernel.debug')) {
            $container->register('temporal.data_collector.client.interceptor', CollectingClientInterceptor::class)
                ->addArgument(new Reference('logger', ContainerInterface::NULL_ON_INVALID_REFERENCE))
            ;
            $container->register('data_collector.temporal', TemporalDataCollector::class)
                ->addArgument(new Reference('temporal.data_collector.client.interceptor'))
                ->addArgument([])
                ->addArgument([])
                ->addArgument([])
                ->addArgument([])
                ->addTag('data_collector', ['id' => 'temporal', 'template' => '@BaldinofRoadRunner/data_collector/temporal.html.twig'])
            ;
            $collectingInterceptor = new Reference('temporal.data_collector.client.interceptor');
        }

        $clients = [];
        foreach ($config['clients'] as $name => $options) {
            $container->register("temporal.client.$name.service_client_config", ServiceClientConfig::class)
                ->setFactory([ServiceClientConfig::class, 'createFromArray'])
                ->setArguments([
                    '$options' => [
                        'address' => $options['address'],
                        'crt' => $options['crt'] ?? null,
                        'client_key' => $options['client_key'] ?? null,
                        'client_pem' => $options['client_pem'] ?? null,
                        'override_server_name' => $options['override_server_name'] ?? null,
                    ],
                ]);

            $container->register("temporal.client.$name.service_client.factory", ServiceClientFactory::class)
                ->addArgument(new Reference("temporal.client.$name.service_client_config"));

            $container->register("temporal.client.$name.service_client", ServiceClientInterface::class)
                ->setFactory([new Reference("temporal.client.$name.service_client.factory"), '__invoke'])
            ;

            $container->register("temporal.client.$name.options", ClientOptions::class)
                ->setFactory([ClientOptionsFactory::class, 'createFromArray'])
                ->setArguments([
                    '$options' => [
                        'namespace' => $options['namespace'],
                        'identity' => $options['identity'] ?? null,
                        'query_reject_condition' => $options['query_reject_condition'] ?? null,
                    ],
                ]);

            $interceptorServices = array_map(static fn ($id) => new Reference($id), $options['interceptors'] ?? []);
            if ($collectingInterceptor) {
                array_unshift($interceptorServices, $collectingInterceptor);
            }

            $container->register("temporal.client.$name.interceptors", SimplePipelineProvider::class)
                ->addArgument($interceptorServices);

            $container->register("temporal.client.$name.workflow", WorkflowClientInterface::class)
                ->setFactory([WorkflowClient::class, 'create'])
                ->setArguments([
                    new Reference("temporal.client.$name.service_client"),
                    new Reference("temporal.client.$name.options"),
                    new Reference('temporal.data_converter'),
                    new Reference("temporal.client.$name.interceptors"),
                ]);

            $container->register("temporal.client.$name.schedule", ScheduleClientInterface::class)
                ->setFactory([ScheduleClient::class, 'create'])
                ->setArguments([
                    new Reference("temporal.client.$name.service_client"),
                    new Reference("temporal.client.$name.options"),
                    new Reference('temporal.data_converter'),
                ]);

            $clients[] = [
                'name' => $name,
                'options' => $options,
                'interceptors' => array_map(strval(...), $interceptorServices),
            ];
        }

        if (!$container->hasDefinition("temporal.client.{$config['default_client']}.workflow")) {
            throw new \InvalidArgumentException(\sprintf('%s not found in service container', "temporal.client.{$config['default_client']}"));
        }

        $container->setAlias(WorkflowClientInterface::class, "temporal.client.{$config['default_client']}.workflow");
        $container->setAlias(ScheduleClientInterface::class, "temporal.client.{$config['default_client']}.schedule");

        $container->register(TemporalWorkerFactory::class)
            ->setFactory([TemporalWorkerFactory::class, 'create'])
            ->setArguments([
                new Reference('temporal.data_converter'),
            ]);

        $container->register(DebugWorkersCommand::class)
            ->addArgument(new Reference(TemporalWorker::class))
            ->addTag('console.command');

        $container->register(DebugClientsCommand::class)
            ->addArgument($clients)
            ->addTag('console.command');

        if ($container->hasDefinition('data_collector.temporal')) {
            $container->getDefinition('data_collector.temporal')->replaceArgument(3, $clients);
        }
    }

    /** @param \ReflectionClass<object> $class */
    private function getTemporalWorkerName(\ReflectionClass $class): ?string
    {
        return ($class->getAttributes(AssignToWorker::class)[0] ?? null)?->newInstance()->workerName;
    }
}
