<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\EventListener;

use Baldinof\RoadRunnerBundle\Event\WorkerStartEvent;
use Baldinof\RoadRunnerBundle\EventListener\ConfigureVarDumperListener;
use PHPUnit\Framework\TestCase;
use Symfony\Component\VarDumper\Cloner\Data;
use Symfony\Component\VarDumper\Cloner\VarCloner;
use Symfony\Component\VarDumper\Dumper\DataDumperInterface;
use Symfony\Component\VarDumper\VarDumper;

class ConfigureVarDumperListenerTest extends TestCase
{
    public static $dumped;

    public function test_it_replaces_VarDumper_handler()
    {
        $dumperCloner = new class() implements DataDumperInterface {
            public function dump(Data $data)
            {
                ConfigureVarDumperListenerTest::$dumped = $data;
            }
        };

        (new ConfigureVarDumperListener($dumperCloner, new VarCloner(), true))(new WorkerStartEvent());

        VarDumper::dump('foo');

        $this->assertSame('foo', self::$dumped->getValue());
    }
}
