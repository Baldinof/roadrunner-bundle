<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Event\Centrifuge;

use RoadRunner\Centrifugo\Payload\ResponseInterface;
use RoadRunner\Centrifugo\Request\Invalid;
use Symfony\Contracts\EventDispatcher\Event;

class InvalidEvent extends Event implements CentrifugeEventInterface
{
    public function __construct(
        private readonly Invalid $request,
    ) {
    }

    public function getRequest(): Invalid
    {
        return $this->request;
    }

    public function getResponse(): ?ResponseInterface
    {
        return null;
    }

    public function setResponse(?ResponseInterface $response): CentrifugeEventInterface
    {
        throw new \RuntimeException('Setting response for invalid request is not supported');
    }
}
