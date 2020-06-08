<?php

namespace Baldinof\RoadRunnerBundle\DependencyInjection;

use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;
use Symfony\Component\Config\Definition\Builder\TreeBuilder;
use Symfony\Component\Config\Definition\ConfigurationInterface;

class Configuration implements ConfigurationInterface
{
    public function getConfigTreeBuilder(): TreeBuilder
    {
        $treeBuilder = new TreeBuilder('baldinof_road_runner');

        /** @var ArrayNodeDefinition */
        $root = $treeBuilder->getRootNode();
        $root
            ->children()
                ->arrayNode('middlewares')
                    ->defaultValue([])
                    ->scalarPrototype()->end()
                ->end()
                ->arrayNode('profiler')
                    ->addDefaultsIfNotSet()
                    ->children()
                        ->scalarNode('service_id')
                            ->isRequired()
                            ->defaultNull()
                        ->end()
                    ->end()
                ->end()
                ->booleanNode('should_reboot_kernel')->defaultFalse()->end()
                ->booleanNode('default_integrations')->defaultTrue()->end()
                ->booleanNode('metrics_enabled')->defaultFalse()->end()
            ->end();

        return $treeBuilder;
    }
}
