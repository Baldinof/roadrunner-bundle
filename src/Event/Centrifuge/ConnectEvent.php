<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Event\Centrifuge;

use RoadRunner\Centrifugo\Payload\ConnectResponse;
use RoadRunner\Centrifugo\Payload\ResponseInterface;
use RoadRunner\Centrifugo\Request\Connect;
use Symfony\Contracts\EventDispatcher\Event;

class ConnectEvent extends Event implements CentrifugeEventInterface
{
    private ?ConnectResponse $response = null;

    public function __construct(
        private readonly Connect $request,
    ) {
    }

    public function getRequest(): Connect
    {
        return $this->request;
    }

    public function getResponse(): ?ConnectResponse
    {
        return $this->response;
    }

    public function setResponse(ConnectResponse|ResponseInterface|null $response): self
    {
        $this->response = $response;

        return $this;
    }
}
