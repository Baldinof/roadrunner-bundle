<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass;

use Baldinof\RoadRunnerBundle\Reboot\KernelRebootStrategyInterface;
use Baldinof\RoadRunnerBundle\Temporal\Command\DebugWorkersCommand;
use Baldinof\RoadRunnerBundle\Temporal\WorkerOptionsFactory;
use Baldinof\RoadRunnerBundle\Worker\TemporalDependencies;
use Baldinof\RoadRunnerBundle\Worker\TemporalWorker;
use Baldinof\RoadRunnerBundle\Worker\WorkerRegistryInterface;
use Spiral\RoadRunner\Environment\Mode;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\Compiler\ServiceLocatorTagPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Temporal\DataConverter\DataConverter;
use Temporal\Interceptor\SimplePipelineProvider;
use Temporal\Internal\Interceptor\Interceptor as TemporalInterceptor;
use Temporal\Worker\WorkerOptions;
use Temporal\WorkerFactory as TemporalWorkerFactory;

final class TemporalCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $temporalWorkerDefinition = $this->registerTemporalWorker($container);

        $this->registerWorkflows($container, $temporalWorkerDefinition);
        $this->registerActivities($container, $temporalWorkerDefinition);
    }

    private function registerTemporalWorker(ContainerBuilder $container): Definition
    {
        $config = (array) $container->getParameter('temporal.config');

        $activitiesMap = [];
        foreach ($container->findTaggedServiceIds('temporal.activity') as $id => $attributes) {
            $activityDefinition = $container->getDefinition($id);
            $activitiesMap[$activityDefinition->getClass()] = new Reference($id);
        }

        $container->register(TemporalDependencies::class, TemporalDependencies::class)
            ->setArguments([
                new Reference(KernelRebootStrategyInterface::class),
                new Reference(EventDispatcherInterface::class),
                ServiceLocatorTagPass::register($container, $activitiesMap),
            ])
            ->setPublic(true);

        $temporalWorkerDefinition = $container->register(TemporalWorker::class, TemporalWorker::class)
            ->setArguments([
                new Reference('kernel'),
                new Reference(TemporalWorkerFactory::class),
            ]);

        $converters = array_keys($container->findTaggedServiceIds('temporal.data_converter'));
        $container->getDefinition(DataConverter::class)
            ->setArguments(array_map(fn($id) => new Reference($id), $converters));

        /** @var array $defaultInterceptors */
        $defaultInterceptors = $container->getParameter('temporal.default_interceptors');

        $queues = array_column($config['workers'], 'queue');
        if (count($queues) !== count(array_unique($queues))) {
            throw new InvalidArgumentException('Temporal workers must have unique task queues.');
        }

        foreach ($config['workers'] as $name => $options) {
            $useDefaultInterceptors = $options['default_interceptors'];

            $interceptors = $useDefaultInterceptors ? $defaultInterceptors : [];
            $interceptorReferences = [];

            foreach (array_merge($interceptors, $options['interceptors'] ?? []) as $m) {
                if (!$container->has($m)) {
                    throw new LogicException("No service found for interceptor '$m'.");
                }

                $definition = $container->findDefinition($m);
                $class = $definition->getClass();

                if (null === $class) {
                    throw new InvalidArgumentException("Missing class definition for service '$m'.");
                }

                if (!is_a($class, TemporalInterceptor::class, true)) {
                    throw new InvalidArgumentException(\sprintf("Service '%s' should implement '%s'.", $m, TemporalInterceptor::class));
                }

                $interceptorReferences[] = new Reference($m);
            }

            $container->register("temporal.worker.$name.interceptors", SimplePipelineProvider::class)
                ->setArguments([$interceptorReferences]);

            $container->register("temporal.worker.$name.options", WorkerOptions::class)
                ->setFactory([WorkerOptionsFactory::class, 'createFromArray'])
                ->setArguments([
                    '$options' => $options['options'],
                ]);

            $temporalWorkerDefinition->addMethodCall('addWorker', [
                $name,
                $options['queue'],
                new Reference("temporal.worker.$name.options"),
                new Reference($options['exception_interceptor']),
                new Reference("temporal.worker.$name.interceptors"),
            ]);
        }

        $workerRegistry = $container->findDefinition(WorkerRegistryInterface::class);
        $workerRegistry->addMethodCall('registerWorker', [
            Mode::MODE_TEMPORAL,
            new Reference(TemporalWorker::class),
        ]);

        return $temporalWorkerDefinition;
    }

    private function registerWorkflows(ContainerBuilder $container, Definition $temporalWorkerDefinition): void
    {
        $workflows = $container->findTaggedServiceIds('temporal.workflow');

        foreach ($workflows as $key => $value) {
            $class = $container->getDefinition($key)->getClass();
            $temporalWorkerDefinition->addMethodCall('registerWorkflow', [$class, $value[0]['worker_name'] ?? null]);
        }
    }

    private function registerActivities(ContainerBuilder $container, Definition $temporalWorkerDefinition): void
    {
        $activities = $container->findTaggedServiceIds('temporal.activity');
        foreach ($activities as $key => $value) {
            $class = $container->getDefinition($key)->getClass();
            $temporalWorkerDefinition->addMethodCall('registerActivity', [
                $class,
                $value[0]['worker_name'] ?? null,
            ]);
        }
    }
}
