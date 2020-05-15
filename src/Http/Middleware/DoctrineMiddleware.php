<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Http\Middleware;

use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DoctrineMiddleware implements MiddlewareInterface
{
    /**
     * @var ManagerRegistry
     */
    private $managerRegistry;

    public function __construct(ManagerRegistry $managerRegistry)
    {
        $this->managerRegistry = $managerRegistry;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        foreach ($this->managerRegistry->getManagers() as $name => $manager) {
            assert($manager instanceof EntityManagerInterface);

            /** @var \Doctrine\DBAL\Connection $connection */
            $connection = $manager->getConnection();

            if ($connection->isConnected()) {
                if (false === $connection->ping()) {
                    $connection->close();
                    $connection->connect();
                }

                if (!$manager->isOpen()) {
                    $this->managerRegistry->resetManager($name);
                }
            }
        }

        return $handler->handle($request);
    }
}
