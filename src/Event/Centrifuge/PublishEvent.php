<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Event\Centrifuge;

use RoadRunner\Centrifugo\Payload\PublishResponse;
use RoadRunner\Centrifugo\Payload\ResponseInterface;
use RoadRunner\Centrifugo\Request\Publish;
use Symfony\Contracts\EventDispatcher\Event;

class PublishEvent extends Event implements CentrifugeEventInterface
{
    private ?PublishResponse $response = null;

    public function __construct(
        private readonly Publish $request,
    ) {
    }

    public function getRequest(): Publish
    {
        return $this->request;
    }

    public function getResponse(): ?PublishResponse
    {
        return $this->response;
    }

    public function setResponse(PublishResponse|ResponseInterface|null $response): self
    {
        $this->response = $response;

        return $this;
    }
}
