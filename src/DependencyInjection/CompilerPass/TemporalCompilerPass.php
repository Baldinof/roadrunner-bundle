<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass;

use Baldinof\RoadRunnerBundle\Worker\TemporalWorker;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

final class TemporalCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $config = (array) $container->getParameter('temporal.config');

        $definition = $container->findDefinition(TemporalWorker::class);
        $this->registerWorkflows($container, $definition, $config);
        $this->registerActivitties($container, $definition, $config);
    }

    private function registerWorkflows(ContainerBuilder $container, Definition $temporalWorkerDefinition, array $config): void
    {
        /**
         * @var array<string, array>
         */
        $workflows = $container->findTaggedServiceIds('temporal.workflows');

        foreach ($workflows as $key => $value) {
            $temporalWorkerDefinition->addMethodCall('registerWorkflow', [$key, $value['worker_name'] ?? null]);
        }
    }

    private function registerActivitties(ContainerBuilder $container, Definition $temporalWorkerDefinition, array $config): void
    {
        /**
         * @var array<string, array>
         */
        $activities = $container->findTaggedServiceIds('temporal.activities');
        foreach ($activities as $key => $value) {
            $temporalWorkerDefinition->addMethodCall('registerActivity', [new Reference($key), $value['worker_name'] ?? null]);
        }
    }
}
