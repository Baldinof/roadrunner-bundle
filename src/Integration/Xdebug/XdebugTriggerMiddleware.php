<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Integration\Xdebug;

use Baldinof\RoadRunnerBundle\Grpc\InterceptorInterface;
use Baldinof\RoadRunnerBundle\Http\MiddlewareInterface;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequest;
use Baldinof\RoadRunnerBundle\RoadRunnerBridge\GrpcRequestInvokerInterface;
use Symfony\Component\HttpFoundation\Request as HttpRequest;
use Symfony\Component\HttpKernel\HttpKernelInterface;

final class XdebugTriggerMiddleware implements MiddlewareInterface, InterceptorInterface
{
    public function __construct(
        private readonly XdebugProxy $xdebug,
    ) {
    }

    public function process(HttpRequest $request, HttpKernelInterface $next): \Iterator
    {
        $connected = $this->preRequest($request);
        try {
            yield $next->handle($request);
        } finally {
            if ($connected) {
                $this->postResponse();
            }
        }
    }

    public function intercept(GrpcRequest $invocation, GrpcRequestInvokerInterface $next): \Iterator
    {
        $connected = $this->preRequest($invocation);
        try {
            yield $next->invoke($invocation);
        } finally {
            if ($connected) {
                $this->postResponse();
            }
        }
    }

    private function preRequest(HttpRequest|GrpcRequest $request): bool
    {
        if (!$this->xdebug->isExtensionLoaded()) {
            return false;
        }
        $shouldTriggerXdebug = $this->shouldStartWithRequest() ?? $this->hasTrigger($request);
        if (!$shouldTriggerXdebug) {
            return false;
        }
        $this->xdebug->notify('rr-pre-request');
        $this->xdebug->connectToClient();

        return true;
    }

    private function postResponse(): void
    {
        $this->xdebug->notify('rr-post-response');
    }

    private function shouldStartWithRequest(): ?bool
    {
        return match ($this->xdebug->startWithRequest()) {
            'yes' => true,
            'no' => false,
            'trigger' => null,
        };
    }

    private function hasTrigger(HttpRequest|GrpcRequest $request): bool
    {
        return match (true) {
            $request instanceof HttpRequest => $this->hasHttpTrigger($request),
            $request instanceof GrpcRequest => $this->hasGrpcTrigger($request),
        };
    }

    private function hasHttpTrigger(HttpRequest $request): bool
    {
        // Trigger behavior is tricky
        // See https://xdebug.org/docs/step_debug#start_with_request
        if ($this->hasNamedTrigger($request, 'XDEBUG_TRIGGER')) {
            return true;
        }
        if (($this->hasNamedTrigger($request, 'XDEBUG_SESSION') || $this->hasNamedTrigger($request, 'XDEBUG_SESSION_START')) && $this->xdebug->hasMode('debug')) {
            return true;
        }
        if ($this->hasNamedTrigger($request, 'XDEBUG_PROFILE') && $this->xdebug->hasMode('profile')) {
            return true;
        }
        if ($this->hasNamedTrigger($request, 'XDEBUG_TRACE') && $this->xdebug->hasMode('trace')) {
            return true;
        }

        return false;
    }

    private function hasNamedTrigger(HttpRequest $request, string $name): bool
    {
        // Trigger behavior is tricky
        // See https://xdebug.org/docs/step_debug#start_with_request
        $triggerValue = $this->xdebug->triggerValue();
        foreach ([
            $request->query,
            $request->request,
            $request->cookies,
        ] as $inputBag) {
            if (empty($triggerValue) ? $inputBag->has($name) : $triggerValue === $inputBag->get($name)) {
                return true;
            }
        }

        return false;
    }

    private function hasGrpcTrigger(GrpcRequest $request): bool
    {
        // There is no standard for gRPC trigger, so we can make one
        $triggerValue = $this->xdebug->triggerValue();
        $context = $request->getContext();
        if ($trigger = $context->getValue('xdebug-trigger')) {
            if (\is_array($trigger)) {
                $trigger = reset($trigger);
            }

            return empty($triggerValue) || $triggerValue === $trigger;
        }

        return false;
    }
}
