<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle;

use Baldinof\RoadRunnerBundle\DependencyInjection\BaldinofRoadRunnerExtension;
use Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass\GrpcServiceCompilerPass;
use Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass\InterceptorCompilerPass;
use Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass\MiddlewareCompilerPass;
use Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass\RemoveConfigureVarDumperListenerPass;
use Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass\TemporalCompilerPass;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Extension\ExtensionInterface;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\HttpKernel\Bundle\AbstractBundle;
use Temporal\Client\WorkflowClientInterface;

final class BaldinofRoadRunnerBundle extends AbstractBundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new RemoveConfigureVarDumperListenerPass());
        $container->addCompilerPass(new MiddlewareCompilerPass());
        if (interface_exists(ServiceInterface::class)) {
            $container->addCompilerPass(new GrpcServiceCompilerPass());
            $container->addCompilerPass(new InterceptorCompilerPass());
        }

        if (interface_exists(WorkflowClientInterface::class)) {
            $container->addCompilerPass(new TemporalCompilerPass());
        }
    }

    public function getContainerExtension(): ?ExtensionInterface
    {
        if (!$this->extension) {
            $this->extension = new BaldinofRoadRunnerExtension();
        }

        return $this->extension;
    }

    public function prependExtension(ContainerConfigurator $container, ContainerBuilder $builder): void
    {
        $builder->prependExtensionConfig('twig', [
            'paths' => [__DIR__.'/../templates' => 'BaldinofRoadRunnerBundle'],
        ]);
    }
}
