<?php

namespace Baldinof\RoadRunnerBundle\Http;

use Symfony\Component\HttpFoundation\StreamedResponse as SymfonyStreamedResponse;

class StreamedResponse extends SymfonyStreamedResponse
{
    private \Closure $obControl;

    /**
     * @param callable|null $obControl Sets the output buffering control. Called only when sending response trough sendResponse().
     */
    public function __construct(?callable $callback = null, int $status = 200, array $headers = [], ?callable $obControl = null)
    {
        parent::__construct($callback, $status, $headers);

        $this->obControl = $obControl ?? function () {
            if (\ob_get_status()) {
                \ob_flush();
            }
            \flush();
        };
    }

    public function getGenerator(): \Generator
    {
        if (!isset($this->callback)) {
            throw new \LogicException('The Response callback must be set.');
        }

        $generator = ($this->callback)();
        if (!$generator instanceof \Generator) {
            throw new \LogicException('The Response callback is not a valid generator.');
        }

        return $generator;
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

        foreach ($this->getGenerator() as $chunk) {
            echo $chunk;
            ($this->obControl)();
        }

        return $this;
    }
}
