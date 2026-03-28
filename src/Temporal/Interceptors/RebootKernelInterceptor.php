<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Temporal\Interceptors;

use Baldinof\RoadRunnerBundle\Event\WorkerExceptionEvent;
use Baldinof\RoadRunnerBundle\Event\WorkerKernelRebootedEvent;
use Baldinof\RoadRunnerBundle\Worker\TemporalDependencies;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\HttpKernel\RebootableInterface;
use Symfony\Contracts\Service\ResetInterface;
use Temporal\Interceptor\ActivityInbound\ActivityInput;
use Temporal\Interceptor\ActivityInboundInterceptor;
use Temporal\Interceptor\Trait\ActivityInboundInterceptorTrait;

final class RebootKernelInterceptor implements ActivityInboundInterceptor
{
    use ActivityInboundInterceptorTrait;

    private LoggerInterface $logger;

    private KernelInterface $kernel;

    private TemporalDependencies $dependencies;

    public function __construct(KernelInterface $kernel, LoggerInterface $logger)
    {
        $this->kernel = $kernel;
        $this->logger = $logger;

        /** @var TemporalDependencies $dependencies */
        $dependencies = $kernel->getContainer()->get(TemporalDependencies::class);
        $this->dependencies = $dependencies;
    }

    public function handleActivityInbound(ActivityInput $input, callable $next): mixed
    {
        try {
            return $next($input);
        } catch (\Throwable $e) {
            $this->logger->error('An error occured: '.$e->getMessage(), ['throwable' => $e]);
            $this->dependencies->getEventDispatcher()->dispatch(new WorkerExceptionEvent($e));
            throw $e;
        } finally {
            if ($this->kernel instanceof RebootableInterface && $this->dependencies->getKernelRebootStrategy()->shouldReboot()) {
                $this->kernel->reboot(null);

                /** @var TemporalDependencies $deps */
                $deps = $this->kernel->getContainer()->get(TemporalDependencies::class);

                $this->dependencies = $deps;
                $this->dependencies->getEventDispatcher()->dispatch(new WorkerKernelRebootedEvent());
            } elseif ($this->kernel->getContainer()->has('services_resetter')) {
                /** @var ResetInterface $resetter */
                $resetter = $this->kernel->getContainer()->get('services_resetter');
                $resetter->reset();
            }

            $this->dependencies->getKernelRebootStrategy()->clear();
        }
    }
}
