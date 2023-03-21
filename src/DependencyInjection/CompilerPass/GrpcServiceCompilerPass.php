<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass;

use Baldinof\RoadRunnerBundle\Grpc\GrpcServiceProvider;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

use function class_implements;

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

            $class = $definition->getClass();

            if ($class === null) {
                continue;
            }

            $grpcServiceInterfaces = $this->findGrpcServiceInterfaces($class);

            foreach ($grpcServiceInterfaces as $grpcServiceInterface) {
                $provider->addMethodCall('registerService', [$grpcServiceInterface, new Reference($id)]);
            }
        }
    }

    /**
     * @return \Generator<string>
     */
    private function findGrpcServiceInterfaces(string $className): \Generator
    {
        $interfaces = class_implements($className);

        if (!$interfaces) {
            return;
        }

        foreach ($interfaces as $interface) {
            if (is_subclass_of($interface, ServiceInterface::class, true)) {
                yield $interface;
            }
        }
    }
}
