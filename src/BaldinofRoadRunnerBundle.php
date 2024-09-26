<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle;

use Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass\GrpcServiceCompilerPass;
use Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass\MiddlewareCompilerPass;
use Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass\RemoveConfigureVarDumperListenerPass;
use Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass\TemporalCompilerPass;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;
use Temporal\Client\WorkflowClientInterface;
use Temporal\Workflow\WorkflowInterface;

final class BaldinofRoadRunnerBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new RemoveConfigureVarDumperListenerPass());
        $container->addCompilerPass(new MiddlewareCompilerPass());
        if (interface_exists(ServiceInterface::class)) {
            $container->addCompilerPass(new GrpcServiceCompilerPass());
        }

        if (interface_exists(WorkflowClientInterface::class) && class_exists(WorkflowInterface::class)) {
            $container->addCompilerPass(new TemporalCompilerPass());
        }
    }
}
