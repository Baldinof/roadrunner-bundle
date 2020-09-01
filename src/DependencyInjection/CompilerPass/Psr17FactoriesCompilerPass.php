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
            ServerRequestFactoryInterface::class => [
                'baldinof_road_runner.psr17.server_request_factory',
                function () { return Psr17FactoryDiscovery::findServerRequestFactory(); },
            ],
            StreamFactoryInterface::class => [
                'baldinof_road_runner.psr17.stream_factory',
                function () { return Psr17FactoryDiscovery::findStreamFactory(); },
            ],
            UploadedFileFactoryInterface::class => [
                'baldinof_road_runner.psr17.uploaded_file_factory',
                function () { return Psr17FactoryDiscovery::findUploadedFileFactory(); },
            ],
            ResponseFactoryInterface::class => [
                'baldinof_road_runner.psr17.response_factory',
                function () { return Psr17FactoryDiscovery::findResponseFactory(); },
            ],
        ];

        // Try to find already registered factories to use. Check class existence
        // because some packages create
        // services definitions, but does not explicitly depends
        // on an implementation (sensio/framework-extra-bundle)
        foreach ($factoryClasses as $factoryClass => [$serviceId, $instantiateFactory]) {
            $existingServiceId = $this->existingServiceId($container, $factoryClass);

            if ($existingServiceId) {
                $container->setAlias($serviceId, $existingServiceId);
                continue;
            }

            // No valid existing service found, use php-http/discovery
            $def = $container->getDefinition($serviceId);
            if (!$def->getClass()) {
                $def->setClass(\get_class($instantiateFactory()));
            }
        }
    }

    private function existingServiceId(ContainerBuilder $container, string $serviceId): ?string
    {
        $foundServiceId = $serviceId;
        $def = null;

        if ($container->hasDefinition($serviceId)) {
            $def = $container->getDefinition($serviceId);
        }

        if ($container->hasAlias($serviceId)) {
            $foundServiceId = (string) $container->getAlias($serviceId);
            $def = $container->getDefinition($foundServiceId);
        }

        if (!$def) {
            return null;
        }

        $class = $def->getClass();

        if ($class && class_exists($class)) {
            return $foundServiceId;
        }

        return null;
    }
}
