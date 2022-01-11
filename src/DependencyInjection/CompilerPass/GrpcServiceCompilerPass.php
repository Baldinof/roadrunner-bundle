<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass;

use Baldinof\RoadRunnerBundle\Grpc\GrpcServiceProvider;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use function array_merge;
use function class_implements;
use function count;
use function in_array;

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
            $definition = $container->getDefinition($id);

            $grpcServiceInterfaces = $this->findServiceInterfaceAncestors($definition->getClass());

            foreach ($grpcServiceInterfaces as $grpcServiceInterface) {
                $provider->addMethodCall('registerService', [$grpcServiceInterface, new Reference($id)]);
            }
        }
    }

    private function findServiceInterfaceAncestors(string $className): array
    {
        $implementedInterfaces = class_implements($className);

        if (
            1 === count($implementedInterfaces)
            && in_array(ServiceInterface::class, $implementedInterfaces)
        ) {
            return [$className];
        }

        $resultingClasses = [];

        foreach ($implementedInterfaces as $implementedInterface) {
            $resultingClasses = array_merge($resultingClasses, $this->findServiceInterfaceAncestors($implementedInterface));
        }

        return $resultingClasses;
    }
}
