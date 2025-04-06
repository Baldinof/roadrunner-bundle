<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Integration\Doctrine;

use Baldinof\RoadRunnerBundle\Event\ForceKernelRebootEvent;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\DBALException; // for dbal 2.x
use Doctrine\DBAL\Exception;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use ProxyManager\Proxy\LazyLoadingInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\VarExporter\LazyObjectInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

trait DoctrineORMTraits
{
    private ManagerRegistry $managerRegistry;

    private ContainerInterface $container;

    private EventDispatcherInterface $eventDispatcher;

    private LoggerInterface $logger;

    private function preRequest(): void
    {
        $connectionServices = $this->managerRegistry->getConnectionNames();

        foreach ($connectionServices as $connectionServiceName) {
            if (!$this->container->initialized($connectionServiceName)) {
                continue;
            }

            $connection = $this->container->get($connectionServiceName);

            \assert($connection instanceof Connection);

            if ($connection->isConnected() && false === $this->ping($connection)) {
                $connection->close();

                $this->logger->debug('Doctrine connection was not re-usable, it has been closed', [
                    'connection_name' => $connectionServiceName,
                ]);
            }
        }
    }

    private function postResponse(): void
    {
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

            if (class_exists(LazyObjectInterface::class) && $manager instanceof LazyObjectInterface) {
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

    private function ping(Connection $con): bool
    {
        try {
            $con->executeQuery($con->getDatabasePlatform()->getDummySelectSQL());

            return true;
        } catch (Exception|DBALException $e) { // @phpstan-ignore-line
            return false;
        }
    }
}
