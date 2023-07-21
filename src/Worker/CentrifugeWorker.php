<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Worker;

use Baldinof\RoadRunnerBundle\Event\Centrifuge\CentrifugeEventInterface;
use Baldinof\RoadRunnerBundle\Event\Centrifuge\ConnectEvent;
use Baldinof\RoadRunnerBundle\Event\Centrifuge\InvalidEvent;
use Baldinof\RoadRunnerBundle\Event\Centrifuge\PublishEvent;
use Baldinof\RoadRunnerBundle\Event\Centrifuge\RefreshEvent;
use Baldinof\RoadRunnerBundle\Event\Centrifuge\RPCEvent;
use Baldinof\RoadRunnerBundle\Event\Centrifuge\SubRefreshEvent;
use Baldinof\RoadRunnerBundle\Event\Centrifuge\SubscribeEvent;
use Psr\Log\LoggerInterface;
use RoadRunner\Centrifugo\CentrifugoWorker;
use RoadRunner\Centrifugo\Payload\ConnectResponse;
use RoadRunner\Centrifugo\Payload\PublishResponse;
use RoadRunner\Centrifugo\Payload\RefreshResponse;
use RoadRunner\Centrifugo\Payload\RPCResponse;
use RoadRunner\Centrifugo\Payload\SubRefreshResponse;
use RoadRunner\Centrifugo\Payload\SubscribeResponse;
use RoadRunner\Centrifugo\Request;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

class CentrifugeWorker implements WorkerInterface
{
    public function __construct(
        private LoggerInterface $logger,
        private CentrifugoWorker $centrifugoWorker,
        private EventDispatcherInterface $eventDispatcher,
        private HttpDependencies $httpDependencies,
    ) {
    }

    public function start(): void
    {
        $this->logger->debug('Centrifuge worker started');

        while ($request = $this->centrifugoWorker->waitRequest()) {
            $eventClass = match (true) {
                $request instanceof Request\Connect => ConnectEvent::class,
                $request instanceof Request\Publish => PublishEvent::class,
                $request instanceof Request\Refresh => RefreshEvent::class,
                $request instanceof Request\SubRefresh => SubRefreshEvent::class,
                $request instanceof Request\Subscribe => SubscribeEvent::class,
                $request instanceof Request\RPC => RPCEvent::class,
                $request instanceof Request\Invalid => InvalidEvent::class,
                default => throw new \RuntimeException(sprintf("Unsupported \$request type '%s'", $request::class)),
            };

            $event = new $eventClass($request);

            try {
                $processedEvent = $this->eventDispatcher->dispatch($event);
                \assert($processedEvent instanceof CentrifugeEventInterface);

                $response = $processedEvent->getResponse() ?? match ($eventClass) {
                    ConnectEvent::class => new ConnectResponse(),
                    PublishEvent::class => new PublishResponse(),
                    RefreshEvent::class => new RefreshResponse(),
                    SubRefreshEvent::class => new SubRefreshResponse(),
                    SubscribeEvent::class => new SubscribeResponse(),
                    RPCEvent::class => new RPCResponse(),
                    default => null,
                };

                if ($response !== null) {
                    $request->respond($response);
                }
            } catch (\Throwable $throwable) {
                $request->disconnect(500, 'Server error');

                throw $throwable;
            }
        }
    }
}
