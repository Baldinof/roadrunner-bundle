<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Grpc;

use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\Method;
use Spiral\RoadRunner\GRPC\ServiceInterface;

class GrpcInvocation
{
    public function __construct(
        private ServiceInterface $service,
        private Method $method,
        private ContextInterface $context,
        private ?string $input
    ) {
    }

    public function getService(): ServiceInterface
    {
        return $this->service;
    }

    public function getMethod(): Method
    {
        return $this->method;
    }

    public function getContext(): ContextInterface
    {
        return $this->context;
    }

    public function getInput(): ?string
    {
        return $this->input;
    }
}
