<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Integration\Doctrine;

use Doctrine\ODM\MongoDB\DocumentManager;
use ProxyManager\Proxy\LazyLoadingInterface;
use Symfony\Bridge\Doctrine\ManagerRegistry;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;

final class DoctrineODMListener implements EventSubscriberInterface
{
    private ?ManagerRegistry $registry = null;
    private ContainerInterface $container;

    public function __construct(ContainerInterface $container)
    {
        $this->container = $container;
    }

    public function onTerminate(TerminateEvent $event): void
    {
        $registry = $this->getRegistry();

        if (null === $registry) {
            return;
        }

        foreach ($registry->getManagerNames() as $serviceId) {
            if (!$this->container->initialized($serviceId)) {
                continue;
            }

            $manager = $this->container->get($serviceId);

            \assert($manager instanceof DocumentManager);

            if (!$manager instanceof LazyLoadingInterface || $manager->isOpen()) {
                $manager->clear();
            }
        }
    }

    public function getRegistry(): ?ManagerRegistry
    {
        if ($this->registry) {
            return $this->registry;
        }

        if ($this->container->initialized('doctrine_mongodb')) {
            $registry = $this->container->get('doctrine_mongodb');

            \assert($registry instanceof ManagerRegistry);

            return $this->registry = $registry;
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'onTerminate',
        ];
    }
}
