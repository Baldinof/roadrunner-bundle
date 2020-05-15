<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\Http\Middleware;

use Baldinof\RoadRunnerBundle\Http\Middleware\DoctrineMiddleware;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DoctrineMiddlewareTest extends TestCase
{
    private $managerRegistryMock;
    private $entityManagerMock;
    private $connectionMock;
    private $requestMock;
    private $handlerMock;
    private $middleware;

    public function setUp(): void
    {
        $this->managerRegistryMock = $this->createMock(ManagerRegistry::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->connectionMock = $this->createMock(Connection::class);

        $this->entityManagerMock->method('getConnection')->willReturn($this->connectionMock);
        $this->managerRegistryMock->method('getManagers')->willReturn([$this->entityManagerMock]);

        $this->requestMock = $this->createMock(ServerRequestInterface::class);
        $this->handlerMock = $this->createMock(RequestHandlerInterface::class);
        $this->middleware = new DoctrineMiddleware($this->managerRegistryMock);
    }

    public function test_skip_when_not_connected(): void
    {
        $this->connectionMock->method('isConnected')->willReturn(false);
        $this->connectionMock->expects($this->never())->method('ping');
        $this->connectionMock->expects($this->never())->method('connect');
        $this->connectionMock->expects($this->never())->method('connect');

        $this->managerRegistryMock->expects($this->never())->method('resetManager');

        $this->middleware->process($this->requestMock, $this->handlerMock);
    }

    public function test_reset_closed_entity_manager(): void
    {
        $this->connectionMock->expects($this->once())->method('ping')->willReturn(true);
        $this->entityManagerMock->expects($this->once())->method('isOpen')->willReturn(false);
        $this->managerRegistryMock->expects($this->once())->method('resetManager');
        $this->connectionMock->method('isConnected')->willReturn(true);
        $this->middleware->process($this->requestMock, $this->handlerMock);
    }

    public function test_reopen_not_respond_connection(): void
    {
        $this->connectionMock->expects($this->once())->method('ping')->willReturn(false);
        $this->entityManagerMock->expects($this->once())->method('isOpen')->willReturn(true);
        $this->connectionMock->method('isConnected')->willReturn(true);
        $this->connectionMock->expects($this->once())->method('close');
        $this->connectionMock->expects($this->once())->method('connect');
        $this->middleware->process($this->requestMock, $this->handlerMock);
    }
}
