<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass;

use Baldinof\RoadRunnerBundle\Reboot\KernelRebootStrategyInterface;
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
            ->setArguments(array_map(fn ($id) => new Reference($id), $converters));

        /** @var array $defaultInterceptors */
        $defaultInterceptors = $container->getParameter('temporal.default_interceptors');

        $queues = array_column($config['workers'], 'queue');
        if (\count($queues) !== \count(array_unique($queues))) {
            throw new InvalidArgumentException('Temporal workers must have unique task queues.');
        }

        $workerInfo = [];
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
                ->addArgument($interceptorReferences);

            $container->register("temporal.worker.$name.options", WorkerOptions::class)
                ->setFactory([WorkerOptionsFactory::class, 'createFromArray'])
                ->addArgument($options['options'])
            ;

            $temporalWorkerDefinition->addMethodCall('addWorker', [
                $name,
                $options['queue'],
                new Reference("temporal.worker.$name.options"),
                new Reference($options['exception_interceptor']),
                new Reference("temporal.worker.$name.interceptors"),
            ]);

            $workerInfo[] = [
                'name' => $name,
                'queue' => $options['queue'],
                'options' => $options['options'],
                'interceptors' => $interceptors,
            ];
        }

        if ($container->hasDefinition('data_collector.temporal')) {
            $container->getDefinition('data_collector.temporal')->replaceArgument(4, $workerInfo);
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
        $workflows = [];
        foreach ($container->findTaggedServiceIds('temporal.workflow') as $key => $value) {
            $class = $container->getDefinition($key)->getClass();
            $workerName = $value[0]['worker_name'] ?? null;
            $temporalWorkerDefinition->addMethodCall('registerWorkflow', [$class, $workerName]);
            $workflows[] = ['class' => $class, 'worker' => $workerName];
        }

        if ($container->hasDefinition('data_collector.temporal')) {
            $container->getDefinition('data_collector.temporal')->replaceArgument(1, $workflows);
        }
    }

    private function registerActivities(ContainerBuilder $container, Definition $temporalWorkerDefinition): void
    {
        $activities = [];
        foreach ($container->findTaggedServiceIds('temporal.activity') as $key => $value) {
            $class = $container->getDefinition($key)->getClass();
            $workerName = $value[0]['worker_name'] ?? null;
            $temporalWorkerDefinition->addMethodCall('registerActivity', [$class, $workerName]);
            $activities[] = ['class' => $class, 'worker' => $workerName];
        }

        if ($container->hasDefinition('data_collector.temporal')) {
            $container->getDefinition('data_collector.temporal')->replaceArgument(2, $activities);
        }
    }
}
