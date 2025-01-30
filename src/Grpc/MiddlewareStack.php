<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Grpc;

use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequest;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequestInvokerInterface;

use function Baldinof\RoadRunnerBundle\consumes;

/**
 * @internal
 */
final class MiddlewareStack implements RequestHandlerInterface
{
    public function __construct(
        private RequestHandlerInterface $handler,
        /**
         * @var \SplStack<MiddlewareInterface>
         */
        private \SplStack $middlewares = new \SplStack(),
    ) {
    }

    public function handle(GrpcRequest $invocation): \Iterator
    {
        $middlewares = clone $this->middlewares;

        $runner = new Runner($middlewares, $this->handler);

        yield $runner->invoke($invocation);

        $runner->close();
    }

    public function pipe(MiddlewareInterface $middleware): void
    {
        $this->middlewares->push($middleware);
    }
}

/**
 * @internal
 */
final class Runner implements GrpcRequestInvokerInterface
{
    public function __construct(
        /** @var \SplStack<MiddlewareInterface> */
        private \SplStack $middlewares,
        private RequestHandlerInterface $handler,
        /** @var \SplStack<\Iterator<string>> */
        private \SplStack $iterators = new \SplStack(),
    ) {
    }

    public function invoke(GrpcRequest $request): string
    {
        if ($this->middlewares->isEmpty()) {
            $gen = $this->handler->handle($request);

            return $this->getResponse($gen, \get_class($this->handler).'::invoke()');
        }

        /** @var MiddlewareInterface $middleware */
        $middleware = $this->middlewares->shift();

        $gen = $middleware->processInvocation($request, $this);

        return $this->getResponse($gen, \get_class($middleware).'::process()');
    }

    public function close(): void
    {
        foreach ($this->iterators as $gen) {
            consumes($gen);
        }
    }

    private function getResponse(\Iterator $iterator, string $caller): string
    {
        $this->iterators->push($iterator);

        $resp = $iterator->current();

        if (!\is_string($resp)) {
            throw new \UnexpectedValueException(sprintf("'%s' first yield should be a string, '%s' given", $caller, \is_object($resp) ? \get_class($resp) : \gettype($resp)));
        }

        return $resp;
    }
}
