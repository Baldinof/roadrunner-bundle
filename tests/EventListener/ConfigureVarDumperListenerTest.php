<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle\EventListener;

use Baldinof\RoadRunnerBundle\Event\WorkerStartEvent;
use Baldinof\RoadRunnerBundle\Integration\Symfony\ConfigureVarDumperListener;
use PHPUnit\Framework\TestCase;
use Spiral\RoadRunner\Environment\Mode;
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

        (new ConfigureVarDumperListener($dumperCloner, new VarCloner(), Mode::MODE_HTTP))(new WorkerStartEvent());

        VarDumper::dump('foo');

        $this->assertSame('foo', self::$dumped->getValue());
    }
}
