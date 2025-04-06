<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Integration\Doctrine;

use Baldinof\RoadRunnerBundle\Grpc\InterceptorInterface;
use Baldinof\RoadRunnerBundle\Http\MiddlewareInterface;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequest;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequestInvokerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

final class DoctrineORMMiddleware implements MiddlewareInterface, InterceptorInterface
{
    use DoctrineORMTraits;

    public function __construct(ManagerRegistry $managerRegistry, ContainerInterface $container, EventDispatcherInterface $eventDispatcher, LoggerInterface $logger)
    {
        $this->managerRegistry = $managerRegistry;
        $this->container = $container;
        $this->logger = $logger;
        $this->eventDispatcher = $eventDispatcher;
    }

    public function process(Request $request, HttpKernelInterface $next): \Iterator
    {
        $this->preRequest();

        yield $next->handle($request);

        $this->postResponse();
    }

    public function intercept(GrpcRequest $invocation, GrpcRequestInvokerInterface $next): \Iterator
    {
        $this->preRequest();

        yield $next->invoke($invocation);

        $this->postResponse();
    }
}
