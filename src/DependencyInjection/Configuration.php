<?php

namespace Baldinof\RoadRunnerBundle\DependencyInjection;

use Baldinof\RoadRunnerBundle\Reboot\KernelRebootStrategyInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    const MONOLOG_CHANNEL = 'roadrunner';

    const KERNEL_REBOOT_STRATEGY_ALWAYS = 'always';
    const KERNEL_REBOOT_STRATEGY_ON_EXCEPTION = 'on_exception';

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
                        ->scalarNode('strategy')
                            ->info(sprintf(
                                'Possible values are "%s", "%s" or any service that implements "%s"/',
                                self::KERNEL_REBOOT_STRATEGY_ALWAYS,
                                self::KERNEL_REBOOT_STRATEGY_ON_EXCEPTION,
                                KernelRebootStrategyInterface::class
                            ))
                            ->defaultValue(self::KERNEL_REBOOT_STRATEGY_ON_EXCEPTION)
                        ->end()
                        ->arrayNode('allowed_exceptions')
                            ->info('Only used when `reboot_kernel.strategy: on_exception`. Exceptions defined here will not cause kernel reboots.')
                            ->defaultValue([])
                            ->scalarPrototype()->end()
                        ->end()
                    ->end()
                ->end()
                ->arrayNode('middlewares')
                    ->defaultValue([])
                    ->scalarPrototype()->end()
                ->end()
                ->booleanNode('should_reboot_kernel')
                    ->defaultFalse()
                    ->setDeprecated('Configuration "%path%" is deprecated in favor of "baldinor_road_runner.kernel_reboot_strategy"')
                ->end()
                ->booleanNode('default_integrations')->defaultTrue()->end()
                ->booleanNode('metrics_enabled')->defaultFalse()->end()
            ->end();

        return $treeBuilder;
    }
}
