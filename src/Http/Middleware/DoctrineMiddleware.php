<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Http\Middleware;

use Baldinof\RoadRunnerBundle\Event\ForceKernelRebootEvent;
use Baldinof\RoadRunnerBundle\Http\IteratorMiddlewareInterface;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use ProxyManager\Proxy\LazyLoadingInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class DoctrineMiddleware implements IteratorMiddlewareInterface
{
    private $managerRegistry;
    private $container;
    private $logger;
    private $eventDispatcher;

    public function __construct(ManagerRegistry $managerRegistry, ContainerInterface $container, EventDispatcherInterface $eventDispatcher, LoggerInterface $logger)
    {
        $this->managerRegistry = $managerRegistry;
        $this->container = $container;
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): \Iterator
    {
        $connectionServices = $this->managerRegistry->getConnectionNames();

        foreach ($connectionServices as $connectionServiceName) {
            if (!$this->container->initialized($connectionServiceName)) {
                continue;
            }

            $connection = $this->container->get($connectionServiceName);

            \assert($connection instanceof Connection);

            if ($connection->isConnected() && false === $connection->ping()) {
                $connection->close();

                $this->logger->debug('Doctrine connection was not re-usable, it has been closed', [
                    'connection_name' => $connectionServiceName,
                ]);
            }
        }

        yield $handler->handle($request);

        $managerNames = $this->managerRegistry->getManagerNames();

        foreach ($managerNames as $managerName) {
            if (!$this->container->initialized($managerName)) {
                continue;
            }

            $manager = $this->container->get($managerName);

            \assert($manager instanceof EntityManagerInterface);

            if ($manager instanceof LazyLoadingInterface) {
                continue; // Doctrine bundle will handle manager reset on next request
            }

            if (!$manager->isOpen()) {
                $this->eventDispatcher->dispatch(new ForceKernelRebootEvent(
                    "entity manager '$managerName' is closed and the package `symfony/proxy-manager-bridge` is not installed so kernel reset will not re-open it"
                ));

                return;
            }
        }
    }
}
