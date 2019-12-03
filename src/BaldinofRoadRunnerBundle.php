<?php

namespace Baldinof\RoadRunnerBundle;

use Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass\RemoveConfigureVarDumperListenerPass;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

final class BaldinofRoadRunnerBundle extends Bundle
{
    public function build(ContainerBuilder $container): void
    {
        parent::build($container);

        $container->addCompilerPass(new RemoveConfigureVarDumperListenerPass());
    }
}
