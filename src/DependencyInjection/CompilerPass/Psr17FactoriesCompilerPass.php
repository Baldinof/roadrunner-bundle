<?php

namespace Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass;

use Http\Discovery\Psr17FactoryDiscovery;
use Psr\Http\Message\ResponseFactoryInterface;
use Psr\Http\Message\ServerRequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Message\UploadedFileFactoryInterface;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;

class Psr17FactoriesCompilerPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        $factoryClasses = [
            ServerRequestFactoryInterface::class => function () { return Psr17FactoryDiscovery::findServerRequestFactory(); },
            StreamFactoryInterface::class => function () { return Psr17FactoryDiscovery::findStreamFactory(); },
            UploadedFileFactoryInterface::class => function () { return Psr17FactoryDiscovery::findUploadedFileFactory(); },
            ResponseFactoryInterface::class => function () { return Psr17FactoryDiscovery::findResponseFactory(); },
        ];

        // Try to find already registered factories to use. Check class existence
        // because some packages create
        // services definitions, but does not explicitly depends
        // on an implementation (sensio/framework-extra-bundle)
        foreach ($factoryClasses as $factoryClass => $instantiateFactory) {
            if ($this->hasValidDefinition($container, $factoryClass)) {
                continue;
            }

            // No valid existing service found, use php-http/discovery
            $container->register($factoryClass, \get_class($instantiateFactory()));
        }
    }

    private function hasValidDefinition(ContainerBuilder $container, string $serviceId): bool
    {
        $foundServiceId = $serviceId;
        $def = null;

        if ($container->hasDefinition($serviceId)) {
            $def = $container->getDefinition($serviceId);
        }

        if ($def === null && $container->hasAlias($serviceId)) {
            $foundServiceId = (string) $container->getAlias($serviceId);
            $def = $container->getDefinition($foundServiceId);
        }

        if (!$def) {
            return false;
        }

        $class = $def->getClass();

        return $class && class_exists($class);
    }
}
