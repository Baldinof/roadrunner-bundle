<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\Integration\Doctrine;

use Baldinof\RoadRunnerBundle\Event\ForceKernelRebootEvent;
use Baldinof\RoadRunnerBundle\Integration\Doctrine\DoctrineORMMiddleware;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException;
use Doctrine\DBAL\Exception;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use ProxyManager\Proxy\LazyLoadingInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Tests\Baldinof\RoadRunnerBundle\Utils\CallableHttpKernel;

use function Baldinof\RoadRunnerBundle\consumes;

class DoctrineORMMiddlewareTest extends TestCase
{
    public const CONNECTION_NAME = 'doctrine.connection';
    public const MANAGER_NAME = 'doctrine.manager';

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
        $platform = $this->createMock(AbstractPlatform::class);
        $platform->method('getDummySelectSQL')->willReturn('SELECT 1');

        $this->managerRegistryMock = $this->createMock(ManagerRegistry::class);
        $this->connectionMock = $this->createMock(Connection::class);
        $this->connectionMock->method('getDatabasePlatform')->willReturn($platform);

        $this->container = new Container();
        $this->container->set(self::CONNECTION_NAME, $this->connectionMock);

        $this->managerRegistryMock->method('getConnectionNames')->willReturn([self::CONNECTION_NAME]);
        $this->managerRegistryMock->method('getManagerNames')->willReturn([self::MANAGER_NAME]);

        $this->request = Request::create('https://example.org');
        $this->handler = new CallableHttpKernel(fn () => new Response());

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
        $this->connectionMock->expects($this->never())->method('executeQuery');
        $this->connectionMock->expects($this->never())->method('close');
        $this->connectionMock->expects($this->never())->method('connect');

        consumes($this->middleware->process($this->request, $this->handler));
    }

    public function test_skip_when_not_connected(): void
    {
        $this->connectionMock->method('isConnected')->willReturn(false);
        $this->connectionMock->expects($this->never())->method('executeQuery');
        $this->connectionMock->expects($this->never())->method('close');
        $this->connectionMock->expects($this->never())->method('connect');

        consumes($this->middleware->process($this->request, $this->handler));
    }

    public function test_it_closes_not_pingable_connection(): void
    {
        if (class_exists(Exception::class)) {
            $this->connectionMock->expects($this->once())->method('executeQuery')->will($this->throwException(new Exception()));
        } else {
            // For DBAL 2.x
            $this->connectionMock->expects($this->once())->method('executeQuery')->will($this->throwException(new DBALException()));
        }
        $this->connectionMock->method('isConnected')->willReturn(true);
        $this->connectionMock->expects($this->once())->method('close');
        $this->connectionMock->expects($this->never())->method('connect');

        consumes($this->middleware->process($this->request, $this->handler));
    }

    public function test_it_does_not_close_pingable_connection(): void
    {
        $this->connectionMock->expects($this->once())->method('executeQuery');
        $this->connectionMock->method('isConnected')->willReturn(true);
        $this->connectionMock->expects($this->never())->method('close');
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
