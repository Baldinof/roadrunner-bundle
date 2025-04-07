<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Runtime;

use Baldinof\RoadRunnerBundle\Worker\WorkerRegistryInterface;
use Spiral\RoadRunner\Environment\Mode;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Component\Runtime\RunnerInterface;

class Runner implements RunnerInterface
{
    public function __construct(
        private KernelInterface $kernel,
        private string $mode)
    {
    }

    public function run(): int
    {
        $_SERVER['APP_RUNTIME_MODE'] = \sprintf('web=%d&worker=1', $this->mode === Mode::MODE_HTTP ? 1 : 0);
        
        $this->kernel->boot();

        /** @var WorkerRegistryInterface $registry */
        $registry = $this->kernel->getContainer()->get(WorkerRegistryInterface::class);
        $worker = $registry->getWorker($this->mode);

        if (null === $worker) {
            error_log(\sprintf('Missing RR worker implementation for %s mode', $this->mode));

            return 1;
        }

        $worker->start();

        return 0;
    }
}
