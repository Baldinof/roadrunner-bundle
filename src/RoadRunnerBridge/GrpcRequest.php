<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\RoadRunnerBridge;

use Spiral\RoadRunner\GRPC\ContextInterface;
use Spiral\RoadRunner\GRPC\Method;
use Spiral\RoadRunner\GRPC\ServiceInterface;

final class GrpcRequest
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

    public function withContext(ContextInterface $context): self
    {
        $request = clone $this;

        $request->context = $context;

        return $request;
    }

    /**
     * @param non-empty-string $key
     */
    public function withContextValue(string $key, mixed $value): self
    {
        return $this->withContext($this->context->withValue($key, $value));
    }

    public function getInput(): ?string
    {
        return $this->input;
    }

    public function withInput(?string $input): self
    {
        $request = clone $this;

        $request->input = $input;

        return $request;
    }
}
