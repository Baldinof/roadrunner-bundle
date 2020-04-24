<?php

namespace Tests\Baldinof\RoadRunnerBundle;

use Baldinof\RoadRunnerBundle\BaldinofRoadRunnerBundle;
use Baldinof\RoadRunnerBundle\Http\Middleware\SentryMiddleware;
use PHPUnit\Framework\TestCase;
use Sentry\SentryBundle\SentryBundle;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Routing\RouteCollectionBuilder;

class BaldinofRoadRunnerBundleTest extends TestCase
{
    public function test_it_loads_sentry_middleware_if_needed()
    {
        $k = $this->getKernel([], [
            new SentryBundle(),
        ]);

        $k->boot();

        $c = $k->getContainer()->get('test.service_container');

        $this->assertTrue($c->has(SentryMiddleware::class));
    }

    public function test_it_loads_sentry_middleware_if_not_needed()
    {
        $k = $this->getKernel([], []);
        $k->boot();

        $c = $k->getContainer()->get('test.service_container');

        $this->assertFalse($c->has(SentryMiddleware::class));
    }

    public function test_it_does_not_load_default_integrations_according_to_config()
    {
        $k = $this->getKernel(['default_integrations' => false], [
            new SentryBundle(),
        ]);

        $k->boot();

        $c = $k->getContainer()->get('test.service_container');

        $this->assertFalse($c->has(SentryMiddleware::class));
    }

    public function getKernel(array $config, array $extraBundles)
    {
        return new TestKernel('test', true, $config, $extraBundles);
    }
}

class TestKernel extends Kernel
{
    use MicroKernelTrait;

    private $config;
    private $extraBundles;

    public function __construct(string $env, bool $debug, array $config, array $extraBundles)
    {
        (new Filesystem())->remove(__DIR__.'/__cache');

        parent::__construct($env, $debug);

        $this->config = $config;
        $this->extraBundles = $extraBundles;
    }

    public function getCacheDir()
    {
        return __DIR__.'/__cache';
    }

    public function registerBundles()
    {
        yield new FrameworkBundle();

        yield from $this->extraBundles;

        yield new BaldinofRoadRunnerBundle();
    }

    protected function configureRoutes(RouteCollectionBuilder $routes)
    {
    }

    protected function configureContainer(ContainerBuilder $c, LoaderInterface $loader)
    {
        $c->loadFromExtension('framework', [
            'test' => true,
            'secret' => 'secret',
        ]);

        $c->loadFromExtension('baldinof_road_runner', $this->config);
    }
}
