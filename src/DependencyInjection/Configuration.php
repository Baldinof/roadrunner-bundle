<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\DependencyInjection;

use Baldinof\RoadRunnerBundle\Reboot\KernelRebootStrategyInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;
use Temporal\Api\Enums\V1\QueryRejectCondition;
use Temporal\DataConverter\BinaryConverter;
use Temporal\DataConverter\JsonConverter;
use Temporal\DataConverter\NullConverter;
use Temporal\DataConverter\ProtoJsonConverter;

class Configuration implements ConfigurationInterface
{
    public const MONOLOG_CHANNEL = 'roadrunner';

    public const KERNEL_REBOOT_STRATEGY_ALWAYS = 'always';
    public const KERNEL_REBOOT_STRATEGY_ON_EXCEPTION = 'on_exception';
    public const KERNEL_REBOOT_STRATEGY_MAX_JOBS = 'max_jobs';

    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('baldinof_road_runner');

        /** @var ArrayNodeDefinition */
        $root = $treeBuilder->getRootNode();
        $root
            ->children()
                ->arrayNode('kernel_reboot')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('strategy')
                            ->info(\sprintf(
                                'Possible values are "%s", "%s", "%s" or any service that implements "%s"/',
                                self::KERNEL_REBOOT_STRATEGY_ALWAYS,
                                self::KERNEL_REBOOT_STRATEGY_ON_EXCEPTION,
                                self::KERNEL_REBOOT_STRATEGY_MAX_JOBS,
                                KernelRebootStrategyInterface::class
                            ))
                            ->defaultValue([self::KERNEL_REBOOT_STRATEGY_ON_EXCEPTION])
                            ->beforeNormalization()->castToArray()->end()
                            ->scalarPrototype()->end()
                        ->end()
                        ->arrayNode('allowed_exceptions')
                            ->info('Only used when `reboot_kernel.strategy: on_exception`. Exceptions defined here will not cause kernel reboots.')
                            ->defaultValue([])
                            ->scalarPrototype()->end()
                        ->end()
                        ->scalarNode('max_jobs')
                            ->info('Only used when `reboot_kernel.strategy: max_jobs`. Maximum numbers of jobs before kernel reboot')
                            ->defaultValue(1000)
                        ->end()
                        ->scalarNode('max_jobs_dispersion')
                            ->info('Only used when `reboot_kernel.strategy: max_jobs`. Dispersion persent')
                            ->defaultValue(0.2)
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('middlewares')
                    ->defaultValue([])
                    ->scalarPrototype()->end()
                ->end()
                ->booleanNode('default_integrations')->defaultTrue()->end()
                ->arrayNode('metrics')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->booleanNode('enabled')->defaultFalse()->end()
                        ->arrayNode('collect')
                            ->defaultValue([])
                            ->arrayPrototype()
                                ->children()
                                    ->enumNode('type')
                                        ->values(['counter', 'histogram', 'gauge'])
                                        ->isRequired()
                                    ->end()
                                    ->scalarNode('help')->defaultNull()->end()
                                    ->scalarNode('namespace')->defaultNull()->end()
                                    ->scalarNode('subsystem')->defaultNull()->end()
                                    ->arrayNode('labels')
                                        ->scalarPrototype()
                                            ->validate()
                                                ->ifEmpty()->thenInvalid('Metric label value cannot be empty')
                                                ->always(fn ($value) => (string) $value)
                                            ->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('buckets')
                                        ->floatPrototype()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('kv')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('storages')
                            ->defaultValue([])
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('temporal')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->arrayNode('data_converters')
                            ->defaultValue([
                                NullConverter::class,
                                BinaryConverter::class,
                                ProtoJsonConverter::class,
                                JsonConverter::class,
                            ])->scalarPrototype()->end()
                        ->end()
                        ->scalarNode('default_client')->defaultValue('default')->isRequired()->end()
                        ->arrayNode('clients')
                            ->defaultValue([
                                'default' => [
                                    'namespace' => 'default',
                                    'address' => 'localhost:7233',
                                ],
                            ])
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->scalarNode('address')->defaultValue('localhost:7233')->cannotBeEmpty()->end()
                                    ->scalarNode('namespace')->defaultValue('default')->cannotBeEmpty()->end()
                                    ->scalarNode('crt')->end()
                                    ->scalarNode('client_key')->end()
                                    ->scalarNode('client_pem')->end()
                                    ->scalarNode('override_server_name')->end()
                                    ->scalarNode('identity')->end()
                                    ->arrayNode('interceptors')
                                            ->defaultValue([])
                                            ->scalarPrototype()->end()
                                    ->end()
                                    ->enumNode('query_reject_condition')
                                        ->values([
                                            QueryRejectCondition::QUERY_REJECT_CONDITION_UNSPECIFIED,
                                            QueryRejectCondition::QUERY_REJECT_CONDITION_NONE,
                                            QueryRejectCondition::QUERY_REJECT_CONDITION_NOT_OPEN,
                                            QueryRejectCondition::QUERY_REJECT_CONDITION_NOT_COMPLETED_CLEANLY,
                                        ])
                                        ->validate()
                                            ->ifNotInArray([
                                                QueryRejectCondition::QUERY_REJECT_CONDITION_UNSPECIFIED,
                                                QueryRejectCondition::QUERY_REJECT_CONDITION_NONE,
                                                QueryRejectCondition::QUERY_REJECT_CONDITION_NOT_OPEN,
                                                QueryRejectCondition::QUERY_REJECT_CONDITION_NOT_COMPLETED_CLEANLY,
                                            ])
                                            ->thenInvalid(\sprintf('"queryRejectionCondition" value is not in the enum: %s', QueryRejectCondition::class))
                                        ->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                        ->arrayNode('workers')
                            ->defaultValue([
                                'default' => [
                                    'queue' => 'default',
                                    'exception_interceptor' => 'temporal.exception_interceptor',
                                    'optons' => [],
                                    'interceptors' => [],
                                ],
                            ])
                            ->useAttributeAsKey('name')
                            ->arrayPrototype()
                                ->addDefaultsIfNotSet()
                                ->children()
                                    ->scalarNode('queue')->defaultValue('default')->end()
                                    ->scalarNode('exception_interceptor')->defaultValue('temporal.exception_interceptor')->end()
                                    ->arrayNode('options')
                                        ->addDefaultsIfNotSet()
                                        ->children()
                                            ->integerNode('max_concurrent_activity_execution_size')->defaultValue(1)->end()
                                            ->floatNode('worker_activities_per_second')->defaultValue(1)->end()
                                            ->integerNode('max_concurrent_local_activity_execution_size')->defaultValue(0)->end()
                                            ->floatNode('worker_local_activities_per_second')->defaultValue(1)->end()
                                            ->floatNode('task_queue_activities_per_second')->defaultValue(1)->end()
                                            ->integerNode('max_concurrent_activity_task_pollers')->defaultValue(5)->end()
                                            ->integerNode('max_concurrent_workflow_task_execution_size')->defaultValue(0)->end()
                                            ->integerNode('max_concurrent_workflow_task_pollers')->defaultValue(1)->end()
                                            ->integerNode('sticky_schedule_to_start_timeout')->defaultValue(5)->end()
                                            ->integerNode('worker_stop_timeout')->defaultValue(5)->end()
                                            ->booleanNode('enable_session_worker')->defaultFalse()->end()
                                            ->scalarNode('session_resource_id')->defaultNull()->end()
                                            ->integerNode('max_concurrent_session_execution_size')->defaultValue(0)->end()
                                        ->end()
                                    ->end()
                                    ->arrayNode('interceptors')
                                        ->defaultValue([])
                                        ->scalarPrototype()->end()
                                    ->end()
                                ->end()
                            ->end()
                        ->end()
                    ->end()
                ->end()
            ->end();

        return $treeBuilder;
    }
}
