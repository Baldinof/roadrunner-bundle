<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Event\Centrifuge;

use RoadRunner\Centrifugo\Payload\ResponseInterface;
use RoadRunner\Centrifugo\Payload\SubscribeResponse;
use RoadRunner\Centrifugo\Request\Subscribe;
use Symfony\Contracts\EventDispatcher\Event;

class SubscribeEvent extends Event implements CentrifugeEventInterface
{
    private ?SubscribeResponse $response = null;

    public function __construct(
        private readonly Subscribe $request,
    ) {
    }

    public function getRequest(): Subscribe
    {
        return $this->request;
    }

    public function getResponse(): ?SubscribeResponse
    {
        return $this->response;
    }

    public function setResponse(SubscribeResponse|ResponseInterface|null $response): self
    {
        $this->response = $response;

        return $this;
    }
}
