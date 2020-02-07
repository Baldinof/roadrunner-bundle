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
                ->booleanNode('should_reboot_kernel')->defaultFalse()->end()
                ->booleanNode('default_middlewares')->defaultTrue()->end()
            ->end();

        return $treeBuilder;
    }
}
