<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\Integration\Doctrine;

use function Baldinof\RoadRunnerBundle\consumes;
use Baldinof\RoadRunnerBundle\Event\ForceKernelRebootEvent;
use Baldinof\RoadRunnerBundle\Integration\Doctrine\DoctrineORMMiddleware;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use ProxyManager\Proxy\LazyLoadingInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class DoctrineORMMiddlewareTest extends TestCase
{
    const CONNECTION_NAME = 'doctrine.connection';
    const MANAGER_NAME = 'doctrine.manager';

    private $managerRegistryMock;
    private $connectionMock;
    private $request;
    private $handler;
    private $middleware;
    private $container;
    private $dispatcher;

    private $rebootForced = false;

    public function setUp(): void
    {
        $this->managerRegistryMock = $this->createMock(ManagerRegistry::class);
        $this->connectionMock = $this->createMock(Connection::class);

        $this->container = new Container();
        $this->container->set(self::CONNECTION_NAME, $this->connectionMock);

        $this->managerRegistryMock->method('getConnectionNames')->willReturn([self::CONNECTION_NAME]);
        $this->managerRegistryMock->method('getManagerNames')->willReturn([self::MANAGER_NAME]);

        $this->request = Request::create('https://example.org');
        $this->handler = new class() implements HttpKernelInterface {
            public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true)
            {
                return new Response();
            }
        };

        $this->dispatcher = new EventDispatcher();

        $this->middleware = new DoctrineORMMiddleware(
            $this->managerRegistryMock,
            $this->container,
            $this->dispatcher,
            new NullLogger()
        );
    }

    public function test_skip_not_initialized_connections()
    {
        $this->container->set(self::CONNECTION_NAME, null);

        $this->connectionMock->expects($this->never())->method('isConnected');
        $this->connectionMock->expects($this->never())->method('ping');
        $this->connectionMock->expects($this->never())->method('close');
        $this->connectionMock->expects($this->never())->method('connect');

        consumes($this->middleware->process($this->request, $this->handler));
    }

    public function test_skip_when_not_connected(): void
    {
        $this->connectionMock->method('isConnected')->willReturn(false);
        $this->connectionMock->expects($this->never())->method('ping');
        $this->connectionMock->expects($this->never())->method('close');
        $this->connectionMock->expects($this->never())->method('connect');

        consumes($this->middleware->process($this->request, $this->handler));
    }

    public function test_it_closes_not_pingable_connection(): void
    {
        $this->connectionMock->expects($this->once())->method('ping')->willReturn(false);
        $this->connectionMock->method('isConnected')->willReturn(true);
        $this->connectionMock->expects($this->once())->method('close');
        $this->connectionMock->expects($this->never())->method('connect');

        consumes($this->middleware->process($this->request, $this->handler));
    }

    public function test_it_force_reboot_on_closed_manager_when_missing_proxy_support()
    {
        $rebootForced = false;

        $this->dispatcher->addListener(ForceKernelRebootEvent::class, function () use (&$rebootForced) {
            $rebootForced = true;
        });

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->once())->method('isOpen')->willReturn(false);

        $this->container->set(self::MANAGER_NAME, $manager);

        consumes($this->middleware->process($this->request, $this->handler));

        $this->assertTrue($rebootForced, 'A ForceKernelRebootEvent should have been dispatched');
    }

    public function test_it_skip_lazy_entity_managers()
    {
        $rebootForced = false;

        $this->dispatcher->addListener(ForceKernelRebootEvent::class, function () use (&$rebootForced) {
            $rebootForced = true;
        });

        $manager = $this->createMock(LazyEntityManager::class);
        $manager->expects($this->never())->method('isOpen');

        $this->container->set(self::MANAGER_NAME, $manager);

        consumes($this->middleware->process($this->request, $this->handler));

        $this->assertFalse($rebootForced, 'A ForceKernelRebootEvent should not have been dispatched');
    }
}

/**
 * @internal Allow mock of multiple interface
 */
interface LazyEntityManager extends EntityManagerInterface, LazyLoadingInterface
{
}
