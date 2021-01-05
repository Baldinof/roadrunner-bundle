<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass;

use Baldinof\RoadRunnerBundle\Grpc\GrpcServiceInterface;
use Baldinof\RoadRunnerBundle\Grpc\GrpcServiceProvider;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Exception\InvalidArgumentException;
use Symfony\Component\DependencyInjection\Reference;

class GrpcServiceCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(GrpcServiceProvider::class)) {
            return;
        }

        $provider = $container->findDefinition(GrpcServiceProvider::class);
        $taggedServices = $container->findTaggedServiceIds('baldinof.roadrunner.grpc_service');

        /** @var string $id */
        foreach ($taggedServices as $id => $tags) {
            /** @var array $classInterfaces */
            $classInterfaces = class_implements($id);
            if (!isset($classInterfaces[GrpcServiceInterface::class])) {
                throw new InvalidArgumentException($id.' should implement '.GrpcServiceInterface::class);
            }

            $provider->addMethodCall('registerService', [$id::getImplementedInterface(), new Reference($id)]);
        }
    }
}
