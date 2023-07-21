<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Event\Centrifuge;

use RoadRunner\Centrifugo\Payload\ResponseInterface;
use RoadRunner\Centrifugo\Request\RequestInterface;

interface CentrifugeEventInterface
{
    public function getResponse(): ?ResponseInterface;

    public function getRequest(): RequestInterface;

    public function setResponse(?ResponseInterface $response): self;
}
