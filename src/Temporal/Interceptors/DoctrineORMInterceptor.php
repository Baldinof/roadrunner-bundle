<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Temporal\Interceptors;

use Baldinof\RoadRunnerBundle\Integration\Doctrine\DoctrineORMTraits;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Temporal\Interceptor\ActivityInbound\ActivityInput;
use Temporal\Interceptor\ActivityInboundInterceptor;
use Temporal\Interceptor\Trait\ActivityInboundInterceptorTrait;

class DoctrineORMInterceptor implements ActivityInboundInterceptor
{
    use DoctrineORMTraits;
    use ActivityInboundInterceptorTrait;

    public function __construct(ManagerRegistry $managerRegistry, ContainerInterface $container, EventDispatcherInterface $eventDispatcher, LoggerInterface $logger)
    {
        $this->managerRegistry = $managerRegistry;
        $this->container = $container;
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function handleActivityInbound(ActivityInput $input, callable $next): mixed
    {
        $this->preRequest();

        try {
            return $next($input);
        } finally {
            $this->postResponse();
        }
    }
}
