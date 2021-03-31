<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Integration\Symfony;

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
    private DataDumperInterface $dumper;
    private ClonerInterface $cloner;
    private bool $rrEnabled;

    public function __construct(DataDumperInterface $dumper, ClonerInterface $cloner, ?bool $rrEnabled = null)
    {
        $this->dumper = $dumper;
        $this->cloner = $cloner;
        $this->rrEnabled = (bool) $rrEnabled;
    }

    public function __invoke(WorkerStartEvent $event): void
    {
        if ($this->rrEnabled) {
            VarDumper::setHandler(function ($var) {
                $data = $this->cloner->cloneVar($var);
                $this->dumper->dump($data);
            });
        }
    }
}
