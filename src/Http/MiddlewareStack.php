<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Http;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

use function Baldinof\RoadRunnerBundle\consumes;

/**
 * @internal
 */
final class MiddlewareStack
{
    public function __construct(
        private RequestHandlerInterface $kernelHandler,
        /**
         * @var \SplStack<MiddlewareInterface>
         */
        private \SplStack $middlewares = new \SplStack(),
    ) {
    }

    public function handle(Request $request): \Iterator
    {
        $middlewares = clone $this->middlewares;

        $runner = new Runner($middlewares, $this->kernelHandler);

        yield $runner->handle($request);

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
final class Runner implements HttpKernelInterface
{
    public function __construct(
        /** @var \SplStack<MiddlewareInterface> */
        private \SplStack $middlewares,
        private RequestHandlerInterface $handler,
        /** @var \SplStack<\Iterator<Response>> */
        private \SplStack $iterators = new \SplStack(),
    ) {
    }

    public function handle(Request $request, $type = self::MAIN_REQUEST, $catch = true): Response
    {
        if ($this->middlewares->isEmpty()) {
            $gen = $this->handler->handle($request);

            return $this->getResponse($gen, \get_class($this->handler).'::handle()');
        }

        /** @var MiddlewareInterface $middleware */
        $middleware = $this->middlewares->shift();

        $gen = $middleware->process($request, $this);

        return $this->getResponse($gen, \get_class($middleware).'::process()');
    }

    public function close(): void
    {
        foreach ($this->iterators as $gen) {
            consumes($gen);
        }
    }

    private function getResponse(\Iterator $iterator, string $caller): Response
    {
        $this->iterators->push($iterator);

        $resp = $iterator->current();

        if (!($resp instanceof Response)) {
            throw new \UnexpectedValueException(sprintf("'%s' first yield should be a '%s' object, '%s' given", $caller, Response::class, \is_object($resp) ? \get_class($resp) : \gettype($resp)));
        }

        return $resp;
    }
}
