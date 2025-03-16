<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass;

use Baldinof\RoadRunnerBundle\Grpc\InterceptorInterface;
use Baldinof\RoadRunnerBundle\Grpc\InterceptorStack;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

class InterceptorCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->hasDefinition(InterceptorStack::class)) {
            return;
        }

        $stack = $container->getDefinition(InterceptorStack::class);

        /** @var string[] */
        $interceptors = $container->getParameter('baldinof_road_runner.interceptors');
        /** @var array{before: string[], after: string[]} */
        $defaultInterceptors = $container->getParameter('baldinof_road_runner.interceptors.default');

        $interceptorsToRemove = [];

        $beforeInterceptors = array_diff($defaultInterceptors['before'], $interceptorsToRemove);
        $afterInterceptors = array_diff($defaultInterceptors['after'], $interceptorsToRemove);

        foreach (array_merge($beforeInterceptors, $interceptors, $afterInterceptors) as $i) {
            if (!$container->has($i)) {
                throw new LogicException("No service found for interceptor '$i'.");
            }

            $definition = $container->findDefinition($i);
            $class = $definition->getClass();

            if (null === $class) {
                throw new InvalidArgumentException("Missing class definition for service '$i'.");
            }

            if (!is_a($class, InterceptorInterface::class, true) && !is_a($class, InterceptorInterface::class, true)) {
                throw new InvalidArgumentException(\sprintf("Service '%s' should implements '%s'.", $i, InterceptorInterface::class));
            }

            $stack->addMethodCall('pipe', [new Reference($i)]);
        }
    }
}
