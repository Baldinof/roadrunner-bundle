<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Event\Centrifuge;

use RoadRunner\Centrifugo\Payload\ResponseInterface;
use RoadRunner\Centrifugo\Payload\RPCResponse;
use RoadRunner\Centrifugo\Request\RPC;
use Symfony\Contracts\EventDispatcher\Event;

class RPCEvent extends Event implements CentrifugeEventInterface
{
    private ?RPCResponse $response = null;

    public function __construct(
        private readonly RPC $request,
    ) {
    }

    public function getRequest(): RPC
    {
        return $this->request;
    }

    public function getResponse(): ?RPCResponse
    {
        return $this->response;
    }

    public function setResponse(RPCResponse|ResponseInterface|null $response): self
    {
        $this->response = $response;

        return $this;
    }
}
