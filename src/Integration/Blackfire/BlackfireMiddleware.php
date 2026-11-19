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

        // The prolog written by enable() carries the name, so it must be set first.
        if (method_exists(\BlackfireProbe::class, 'setTransactionName')) { // @phpstan-ignore function.alreadyNarrowedType, function.impossibleType
            \BlackfireProbe::setTransactionName($request->getMethod().' '.$request->getPathInfo());
        }

        if (!$probe->enable()) {
            yield $next->handle($request);

            return;
        }

        \BlackfireProbe::addMarker('request:start');

        try {
            $response = $next->handle($request);
        } catch (\Throwable $e) {
            if ($probe->isEnabled()) {
                \BlackfireProbe::addMarker('request:end');
                $probe->close();
            }

            throw $e;
        }

        if ($probe->isEnabled()) {
            \BlackfireProbe::addMarker('request:end');
            $probe->close();

            $responseLine = $probe->getResponseLine();
            if (\is_string($responseLine) && str_contains($responseLine, ':')) {
                [$name, $value] = explode(':', $responseLine, 2);

                $response->headers->set('x-'.$name, trim($value));
            }
        }

        yield $response;
    }
}
