<?php

namespace Baldinof\RoadRunnerBundle\Http\Response;

use Symfony\Component\HttpFoundation\Response;

/**
 * Basically a copy of Symfony's StreamedResponse,
 * but the callback needs to be a Generator
 */
class StreamedResponse extends Response
{
    protected ?\Closure $callback = null;
    protected bool $streamed = false;

    private bool $headersSent = false;

    public function __construct(\Generator $callback = null, int $status = 200, array $headers = [])
    {
        parent::__construct(null, $status, $headers);

        if (null !== $callback) {
            $this->setCallback($callback);
        }
    }

    public function setCallback(\Generator $callback): static
    {
        $this->callback = $callback(...);

        return $this;
    }

    public function getCallback(): ?\Generator
    {
        if (!isset($this->callback)) {
            return null;
        }

        return ($this->callback)(...);
    }

    public function sendHeaders(int $statusCode = null): static
    {
        if ($this->headersSent) {
            return $this;
        }

        if ($statusCode < 100 || $statusCode >= 200) {
            $this->headersSent = true;
        }

        return parent::sendHeaders($statusCode);
    }

    public function sendContent(): static
    {
        if ($this->streamed) {
            return $this;
        }

        $this->streamed = true;

        if (!isset($this->callback)) {
            throw new \LogicException('The Response callback must be set.');
        }

        foreach (($this->callback)() as $value) {
            echo $value;
        }

        return $this;
    }

    public function setContent(?string $content): static
    {
        if (null !== $content) {
            throw new \LogicException('The content cannot be set on a StreamedResponse instance.');
        }

        $this->streamed = true;

        return $this;
    }

    public function getContent(): string|false
    {
        return false;
    }
}
