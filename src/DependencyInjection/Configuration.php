<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\DependencyInjection;

use Baldinof\RoadRunnerBundle\Reboot\KernelRebootStrategyInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

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
                            ->info(sprintf(
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
                                        ->floatPrototype()
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
