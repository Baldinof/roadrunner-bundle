<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Temporal\Interceptors;

use Baldinof\RoadRunnerBundle\Integration\Doctrine\DoctrineORMIntegration;
use Temporal\Interceptor\ActivityInbound\ActivityInput;
use Temporal\Interceptor\ActivityInboundInterceptor;

class DoctrineORMInterceptor implements ActivityInboundInterceptor
{
    public function __construct(private readonly DoctrineORMIntegration $integration)
    {
    }

    public function handleActivityInbound(ActivityInput $input, callable $next): mixed
    {
        $this->integration->preRequest();

        try {
            return $next($input);
        } finally {
            $this->integration->postResponse();
        }
    }
}
