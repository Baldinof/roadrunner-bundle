<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\Http\Middleware;

use Baldinof\RoadRunnerBundle\Http\Middleware\DoctrineMiddleware;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DoctrineMiddlewareTest extends TestCase
{
    private $managerRegistryMock;
    private $connectionMock;
    private $requestMock;
    private $handlerMock;
    private $middleware;
    private $containerMock;

    public function setUp(): void
    {
        $this->managerRegistryMock = $this->createMock(ManagerRegistry::class);
        $this->connectionMock = $this->createMock(Connection::class);
        $this->containerMock = $this->createMock(ContainerInterface::class);

        $this->containerMock->method('initialized')->willReturn(true);
        $this->containerMock->method('get')->with('xxx')->willReturn($this->connectionMock);
        $this->managerRegistryMock->method('getConnectionNames')->willReturn(['xxx']);

        $this->requestMock = $this->createMock(ServerRequestInterface::class);
        $this->handlerMock = $this->createMock(RequestHandlerInterface::class);
        $this->middleware = new DoctrineMiddleware($this->managerRegistryMock, $this->containerMock);
    }

    public function test_skip_when_not_connected(): void
    {
        $this->connectionMock->method('isConnected')->willReturn(false);
        $this->connectionMock->expects($this->never())->method('ping');
        $this->connectionMock->expects($this->never())->method('close');
        $this->connectionMock->expects($this->never())->method('connect');

        $this->middleware->process($this->requestMock, $this->handlerMock);
    }

    public function test_reopen_not_respond_connection(): void
    {
        $this->connectionMock->expects($this->once())->method('ping')->willReturn(false);
        $this->connectionMock->method('isConnected')->willReturn(true);
        $this->connectionMock->expects($this->once())->method('close');
        $this->connectionMock->expects($this->never())->method('connect');
        $this->middleware->process($this->requestMock, $this->handlerMock);
    }
}
