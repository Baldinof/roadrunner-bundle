<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Http\Middleware;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Symfony\WebpackEncoreBundle\Asset\EntrypointLookupInterface;

final class WebpackEncoreMiddleware implements MiddlewareInterface
{
    /**
     * @var EntrypointLookupInterface
     */
    private $entrypointLookup;

    public function __construct(EntrypointLookupInterface $entrypointLookup)
    {
        $this->entrypointLookup = $entrypointLookup;
    }

    /**
     * {@inheritdoc}
     */
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $this->entrypointLookup->reset();

        return $handler->handle($request);
    }
}
