<?php

namespace Baldinof\RoadRunnerBundle\Http;

use function Baldinof\RoadRunnerBundle\consumes;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use SplStack;

final class MiddlewareStack implements IteratorRequestHandlerInterface
{
    /**
     * @var RequestHandlerInterface|IteratorRequestHandlerInterface
     */
    private $requestHandler;

    /**
     * @var SplStack
     */
    private $middlewares;

    /**
     * @param RequestHandlerInterface|IteratorRequestHandlerInterface $requestHandler
     */
    public function __construct(object $requestHandler)
    {
        if (!($requestHandler instanceof RequestHandlerInterface) && !($requestHandler instanceof IteratorRequestHandlerInterface)) {
            throw new \InvalidArgumentException(sprintf('Request handler should implement "%s" or "%s", "%s" given.', RequestHandlerInterface::class, IteratorRequestHandlerInterface::class, \get_class($requestHandler)));
        }

        $this->requestHandler = $requestHandler;
        $this->middlewares = new SplStack();
    }

    public function handle(ServerRequestInterface $request): \Iterator
    {
        $middlewares = clone $this->middlewares;

        $runner = new Runner($middlewares, $this->requestHandler);

        yield $runner->handle($request);

        $runner->close();
    }

    /**
     * @param MiddlewareInterface|IteratorMiddlewareInterface $middleware
     */
    public function pipe(object $middleware): void
    {
        if (!($middleware instanceof MiddlewareInterface) && !($middleware instanceof IteratorMiddlewareInterface)) {
            throw new \InvalidArgumentException(sprintf('Middleware should implement "%s" or "%s", "%s" given.', MiddlewareInterface::class, IteratorMiddlewareInterface::class, \get_class($middleware)));
        }

        $this->middlewares->push($middleware);
    }
}

/**
 * @internal
 */
final class Runner implements RequestHandlerInterface
{
    private $middlewares;
    private $handler;

    private $iterators;

    /**
     * @param SplStack                                                $middlewares A stack of MiddlewareInterface or IteratorMiddlewareInterface
     * @param RequestHandlerInterface|IteratorRequestHandlerInterface $handler
     */
    public function __construct(SplStack $middlewares, object $handler)
    {
        $this->middlewares = $middlewares;
        $this->handler = $handler;
        $this->iterators = new SplStack();
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->middlewares->isEmpty()) {
            if ($this->handler instanceof RequestHandlerInterface) {
                return $this->handler->handle($request);
            }

            $gen = $this->handler->handle($request);

            return $this->getResponse($gen, \get_class($this->handler).'::handle()');
        }

        $middleware = $this->middlewares->shift();

        if ($middleware instanceof MiddlewareInterface) {
            return $middleware->process($request, $this);
        }

        $gen = $middleware->process($request, $this);

        return $this->getResponse($gen, \get_class($middleware).'::process()');
    }

    public function close(): void
    {
        foreach ($this->iterators as $gen) {
            consumes($gen);
        }
    }

    private function getResponse(\Iterator $iterator, string $caller): ResponseInterface
    {
        $this->iterators->push($iterator);

        $resp = $iterator->current();

        if (!($resp instanceof ResponseInterface)) {
            throw new \UnexpectedValueException(sprintf("'%s' first yield should be a '%s' object, '%s' given", $caller, ResponseInterface::class, \is_object($resp) ? \get_class($resp) : \gettype($resp)));
        }

        return $resp;
    }
}
