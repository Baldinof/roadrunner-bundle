<?php

namespace Baldinof\RoadRunnerBundle\EventListener;

use Baldinof\RoadRunnerBundle\Event\WorkerStartEvent;
use Symfony\Component\VarDumper\Cloner\ClonerInterface;
use Symfony\Component\VarDumper\Dumper\DataDumperInterface;
use Symfony\Component\VarDumper\VarDumper;

/**
 * Reset the VarDumper handler to use the profiler
 * data collector dumper even in CLI mode.
 */
final class ConfigureVarDumperListener
{
    private $dumper;
    private $cloner;

    public function __construct(DataDumperInterface $dumper, ClonerInterface $cloner)
    {
        $this->dumper = $dumper;
        $this->cloner = $cloner;
    }

    public function __invoke(WorkerStartEvent $event): void
    {
        VarDumper::setHandler(function ($var) {
            $data = $this->cloner->cloneVar($var);
            $this->dumper->dump($data);
        });
    }
}
