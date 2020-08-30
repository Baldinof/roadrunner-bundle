<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Http\Middleware;

use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

class DoctrineMiddleware implements MiddlewareInterface
{
    /**
     * @var ManagerRegistry
     */
    private $managerRegistry;

    /**
     * @var ContainerInterface
     */
    private $container;

    public function __construct(ManagerRegistry $managerRegistry, ContainerInterface $container)
    {
        $this->managerRegistry = $managerRegistry;
        $this->container = $container;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $connectionServices = $this->managerRegistry->getConnectionNames();

        foreach ($connectionServices as $connectionServiceName) {
            if (!$this->container->initialized($connectionServiceName)) {
                continue;
            }

            $connection = $this->container->get($connectionServiceName);
            assert($connection instanceof Connection);

            if ($connection->isConnected() && false === $connection->ping()) {
                $connection->close();
            }
        }

        return $handler->handle($request);
    }
}
