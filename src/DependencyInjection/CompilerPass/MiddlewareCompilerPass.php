<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass;

use Baldinof\RoadRunnerBundle\Grpc\MiddlewareInterface as GrpcMiddlewareInterface;
use Baldinof\RoadRunnerBundle\Grpc\MiddlewareStack as GrpcMiddlewareStack;
use Baldinof\RoadRunnerBundle\Http\MiddlewareInterface as HttpMiddlewareInterface;
use Baldinof\RoadRunnerBundle\Http\MiddlewareStack as HttpMiddlewareStack;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Exception\LogicException;
use Symfony\Component\DependencyInjection\Reference;

class MiddlewareCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $hasHttp = $container->hasDefinition(HttpMiddlewareStack::class);
        $hasGrpc = $container->hasDefinition(GrpcMiddlewareStack::class);
        if (!($hasHttp || $hasGrpc)) {
            return;
        }

        /** @var string[] */
        $confMiddlewares = $container->getParameter('baldinof_road_runner.middlewares');
        /** @var array{before: string[], after: string[]} */
        $defaultMiddlewares = $container->getParameter('baldinof_road_runner.middlewares.default');

        $middlewaresToRemove = [];

        $beforeMiddlewares = array_diff($defaultMiddlewares['before'], $middlewaresToRemove);
        $afterMiddlewares = array_diff($defaultMiddlewares['after'], $middlewaresToRemove);
        $middlewares = array_merge($beforeMiddlewares, $confMiddlewares, $afterMiddlewares);
        foreach ($middlewares as $m) {
            if (!$container->has($m)) {
                throw new LogicException("No service found for middleware '$m'.");
            }

            $definition = $container->findDefinition($m);
            $class = $definition->getClass();

            if (null === $class) {
                throw new InvalidArgumentException("Missing class definition for service '$m'.");
            }
            $isHttp = $hasHttp && is_a($class, HttpMiddlewareInterface::class, true);
            $isGrpc = $hasGrpc && is_a($class, GrpcMiddlewareInterface::class, true);
            if (!$isHttp && !$isGrpc) {
                throw new InvalidArgumentException(sprintf("Service '%s' should implements either '%s' or '%s'.", $m, HttpMiddlewareInterface::class, GrpcMiddlewareInterface::class));
            }
            if ($isHttp) {
                $httpMiddlewares[] = $m;
            }
            if ($isGrpc) {
                $grpcMiddlewares[] = $m;
            }
        }

        if ($hasHttp) {
            $this->processHttp($container, $httpMiddlewares ?? []);
        }
        if ($hasGrpc) {
            $this->processGrpc($container, $grpcMiddlewares ?? []);
        }
    }

    /**
     * @param string[] $middlewares
     */
    private function processHttp(ContainerBuilder $container, array $middlewares): void
    {
        $stack = $container->getDefinition(HttpMiddlewareStack::class);
        foreach ($middlewares as $m) {
            $stack->addMethodCall('pipe', [new Reference($m)]);
        }
    }

    /**
     * @param string[] $middlewares
     */
    private function processGrpc(ContainerBuilder $container, array $middlewares): void
    {
        $stack = $container->getDefinition(GrpcMiddlewareStack::class);
        foreach ($middlewares as $m) {
            $stack->addMethodCall('pipe', [new Reference($m)]);
        }
    }
}
