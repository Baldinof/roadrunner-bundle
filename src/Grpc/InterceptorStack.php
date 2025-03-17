<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Grpc;

use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequest;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequestInvokerInterface;

use function Baldinof\RoadRunnerBundle\consumes;

/**
 * @internal
 */
final class InterceptorStack implements GrpcRequestHandlerInterface
{
    public function __construct(
        private GrpcRequestHandlerInterface $handler,
        /**
         * @var \SplStack<InterceptorInterface>
         */
        private \SplStack $interceptors = new \SplStack(),
    ) {
    }

    public function handle(GrpcRequest $request): \Iterator
    {
        $interceptors = clone $this->interceptors;

        $runner = new Runner($interceptors, $this->handler);

        yield $runner->invoke($request);

        $runner->close();
    }

    public function pipe(InterceptorInterface $interceptor): void
    {
        $this->interceptors->push($interceptor);
    }
}

/**
 * @internal
 */
final class Runner implements GrpcRequestInvokerInterface
{
    public function __construct(
        /** @var \SplStack<InterceptorInterface> */
        private \SplStack $interceptors,
        private GrpcRequestHandlerInterface $handler,
        /** @var \SplStack<\Iterator<string>> */
        private \SplStack $iterators = new \SplStack(),
    ) {
    }

    public function invoke(GrpcRequest $request): string
    {
        if ($this->interceptors->isEmpty()) {
            $gen = $this->handler->handle($request);

            return $this->getResponse($gen, \get_class($this->handler).'::invoke()');
        }

        /** @var InterceptorInterface $interceptor */
        $interceptor = $this->interceptors->shift();

        $gen = $interceptor->intercept($request, $this);

        return $this->getResponse($gen, \get_class($interceptor).'::intercept()');
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
            throw new \UnexpectedValueException(\sprintf("'%s' first yield should be a string, '%s' given", $caller, \is_object($resp) ? \get_class($resp) : \gettype($resp)));
        }

        return $resp;
    }
}
