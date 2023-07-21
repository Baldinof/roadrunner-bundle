<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Event\Centrifuge;

use RoadRunner\Centrifugo\Payload\ResponseInterface;
use RoadRunner\Centrifugo\Payload\SubRefreshResponse;
use RoadRunner\Centrifugo\Request\SubRefresh;
use Symfony\Contracts\EventDispatcher\Event;

class SubRefreshEvent extends Event implements CentrifugeEventInterface
{
    private ?SubRefreshResponse $response = null;

    public function __construct(
        private readonly SubRefresh $request,
    ) {
    }

    public function getRequest(): SubRefresh
    {
        return $this->request;
    }

    public function getResponse(): ?SubRefreshResponse
    {
        return $this->response;
    }

    public function setResponse(SubRefreshResponse|ResponseInterface|null $response): self
    {
        $this->response = $response;

        return $this;
    }
}
