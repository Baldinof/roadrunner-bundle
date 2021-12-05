<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Http;

use function Baldinof\RoadRunnerBundle\consumes;
use SplStack;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\HttpKernelInterface;

/**
 * @internal
 */
final class MiddlewareStack
{
    private RequestHandlerInterface $kernelHandler;

    private SplStack $middlewares;

    public function __construct(RequestHandlerInterface $kernelHandler)
    {
        $this->kernelHandler = $kernelHandler;
        $this->middlewares = new SplStack();
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
    private RequestHandlerInterface $handler;

    /** @var SplStack<MiddlewareInterface> */
    private SplStack $middlewares;
    /** @var SplStack<\Iterator<Response>> */
    private SplStack $iterators;

    /**
     * @param SplStack<MiddlewareInterface> $middlewares A stack of MiddlewareInterface or IteratorMiddlewareInterface
     */
    public function __construct(SplStack $middlewares, RequestHandlerInterface $handler)
    {
        $this->middlewares = $middlewares;
        $this->handler = $handler;
        $this->iterators = new SplStack();
    }

    public function handle(Request $request, $type = self::MASTER_REQUEST, $catch = true): Response
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
