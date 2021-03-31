<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle;

use Baldinof\RoadRunnerBundle\BaldinofRoadRunnerBundle;
use Baldinof\RoadRunnerBundle\Command\WorkerCommand;
use Baldinof\RoadRunnerBundle\EventListener\DeclareMetricsListener;
use Baldinof\RoadRunnerBundle\Integration\Doctrine\DoctrineORMMiddleware;
use Baldinof\RoadRunnerBundle\Integration\Sentry\SentryMiddleware;
use Baldinof\RoadRunnerBundle\Integration\Symfony\StreamedResponseListener;
use Doctrine\Bundle\DoctrineBundle\DoctrineBundle;
use PHPUnit\Framework\TestCase;
use Sentry\SentryBundle\SentryBundle;
use Spiral\RoadRunner\Metrics\MetricsInterface;
use Symfony\Bundle\FrameworkBundle\FrameworkBundle;
use Symfony\Bundle\FrameworkBundle\Kernel\MicroKernelTrait;
use Symfony\Component\Config\Loader\LoaderInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpKernel\Bundle\BundleInterface;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Routing\RouteCollectionBuilder;

class BaldinofRoadRunnerBundleTest extends TestCase
{
    public function test_it_expose_the_worker_command()
    {
        $k = $this->getKernel();
        $k->boot();
        $c = $k->getContainer()->get('test.service_container');

        $cmd = $c->get(WorkerCommand::class);

        $this->assertInstanceOf(WorkerCommand::class, $cmd);
    }

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
        $k = $this->getKernel([
            'baldinof_road_runner' => [
                'default_integrations' => false,
            ],
        ], [
            new SentryBundle(),
        ]);

        $k->boot();

        $c = $k->getContainer()->get('test.service_container');

        $this->assertFalse($c->has(SentryMiddleware::class));
    }

    public function test_metrics_can_be_configured()
    {
        $k = $this->getKernel([
            'baldinof_road_runner' => [
                'metrics' => [
                    'enabled' => true,
                    'collect' => [
                        'hello' => [
                            'type' => 'counter',
                            'labels' => ['hello'],
                        ],
                        'foo' => [
                            'type' => 'gauge',
                        ],
                    ],
                ],
            ],
        ]);

        $_SERVER['RR_RPC'] = 'tcp://localhost:6001'; // Allow RPCFactory to work

        $k->boot();

        $c = $k->getContainer()->get('test.service_container');

        $this->assertTrue($c->has(DeclareMetricsListener::class), "Service '".DeclareMetricsListener::class."' not defined");
        $listener = $c->get(DeclareMetricsListener::class);

        $expectedListener = new DeclareMetricsListener($c->get(MetricsInterface::class));
        $expectedListener->addCollector('hello', [
            'type' => 'counter',
            'labels' => ['hello'],
        ]);
        $expectedListener->addCollector('foo', ['type' => 'gauge']);

        $this->assertEquals($expectedListener, $listener);
    }

    public function test_it_decorates_StreamedResponseListener()
    {
        $k = $this->getKernel([], []);

        $k->boot();

        $c = $k->getContainer()->get('test.service_container');

        $this->assertInstanceOf(StreamedResponseListener::class, $c->get('streamed_response_listener'));
    }

    public function test_it_loads_doctrine_orm_middleware()
    {
        $k = $this->getKernel([
            'doctrine' => [
                'dbal' => [],
                'orm' => [],
            ],
        ], [
            new DoctrineBundle(),
        ]);

        $k->boot();

        $c = $k->getContainer()->get('test.service_container');

        $this->assertTrue($c->has(DoctrineORMMiddleware::class));
    }

    /**
     * @param BundleInterface[] $extraBundles
     */
    public function getKernel(array $config = [], array $extraBundles = []): KernelInterface
    {
        return new class('test', true, $config, $extraBundles) extends Kernel {
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
                $c->setParameter('container.dumper.inline_factories', true);

                // Prevent phpunit warning: 'Test code or tested code did not (only) close its own output buffers'
                $c->setParameter('baldinof_road_runner.intercept_side_effect', false);

                $c->loadFromExtension('framework', [
                    'test' => true,
                    'secret' => 'secret',
                ]);

                foreach ($this->config as $key => $config) {
                    $c->loadFromExtension($key, $config);
                }
            }
        };
    }
}
