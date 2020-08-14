<?php

namespace Baldinof\RoadRunnerBundle;

use Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass\MiddlewareCompilerPass;
use Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass\Psr17FactoriesCompilerPass;
use Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass\RemoveConfigureVarDumperListenerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class BaldinofRoadRunnerBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new RemoveConfigureVarDumperListenerPass());
        $container->addCompilerPass(new MiddlewareCompilerPass());
        $container->addCompilerPass(new Psr17FactoriesCompilerPass());
    }
}
