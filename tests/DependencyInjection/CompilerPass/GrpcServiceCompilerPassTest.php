<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass;

use Baldinof\RoadRunnerBundle\DependencyInjection\CompilerPass\GrpcServiceCompilerPass;
use Baldinof\RoadRunnerBundle\Grpc\GrpcServiceProvider;
use PHPUnit\Framework\TestCase;
use Spiral\RoadRunner\GRPC\ServiceInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Reference;

class GrpcServiceCompilerPassTest extends TestCase
{
    public function test_it()
    {
        $pass = new GrpcServiceCompilerPass();

        $container = new ContainerBuilder();
        $container->register(GrpcServiceProvider::class, GrpcServiceProvider::class);

        $container->register('simple', SimpleGrpcService::class)
            ->addTag('baldinof.roadrunner.grpc_service');

        $container->register('multiple', FooBarGrpcService::class)
            ->addTag('baldinof.roadrunner.grpc_service');

        $pass->process($container);

        $def = $container->getDefinition(GrpcServiceProvider::class)->getMethodCalls();

        $this->assertEquals([
            ['registerService', [SimpleGrpcServiceInterface::class, new Reference('simple')]],
            ['registerService', [FooGrpcServiceInterface::class, new Reference('multiple')]],
            ['registerService', [BarGrpcServiceInterface::class, new Reference('multiple')]],
        ], $def);
    }
}

interface SimpleGrpcServiceInterface extends ServiceInterface
{
}

class SimpleGrpcService implements SimpleGrpcServiceInterface
{
}

interface FooGrpcServiceInterface extends ServiceInterface
{
}

interface BarGrpcServiceInterface extends ServiceInterface
{
}

interface NotAGrpcInterface
{
}

class FooBarGrpcService implements FooGrpcServiceInterface, BarGrpcServiceInterface, NotAGrpcInterface
{
}
