<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Integration\Blackfire;

use Baldinof\RoadRunnerBundle\Http\MiddlewareInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class BlackfireMiddleware implements MiddlewareInterface
{
    public function process(Request $request, HttpKernelInterface $next): \Generator
    {
        if (!$request->headers->has('x-blackfire-query')) {
            yield $next->handle($request);

            return;
        }

        /** @var string $query */
        $query = $request->headers->get('x-blackfire-query');
        $probe = new \BlackfireProbe($query);

        $probe->enable();

        $response = $next->handle($request);

        if ($probe->isEnabled()) {
            $probe->close();

            $probeHeader = explode(':', $probe->getResponseLine(), 2);

            $response->headers->set('x-'.$probeHeader[0], trim($probeHeader[1]));
        }

        yield $response;
    }
}
