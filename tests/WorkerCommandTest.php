<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle;

use Baldinof\RoadRunnerBundle\Command\WorkerCommand;
use Baldinof\RoadRunnerBundle\Worker\WorkerInterface;
use Baldinof\RoadRunnerBundle\Worker\WorkerResolverInterface;
use PHPUnit\Framework\TestCase;
use Spiral\RoadRunner\Environment\Mode;
use Spiral\RoadRunner\EnvironmentInterface;
use Symfony\Component\Console\Tester\CommandTester;

class WorkerCommandTest extends TestCase
{
    public static bool $workerExecuted;

    private CommandTester $command;

    public function setUp(): void
    {
        self::$workerExecuted = false;

        $environment = $this->createMock(EnvironmentInterface::class);
        $workerResolver = new class implements WorkerResolverInterface{
            public function resolve(string $mode): WorkerInterface
            {
                return new class() implements WorkerInterface {
                    public function start(): void
                    {
                        WorkerCommandTest::$workerExecuted = true;
                    }
                };
            }
        };

        $this->command = new CommandTester(new WorkerCommand($workerResolver, $environment));
    }

    public function test_it_start_the_worker_when_ran_by_roadrunner()
    {
        putenv('RR_MODE='.Mode::MODE_HTTP);

        $this->command->execute([]);

        $this->assertEmpty($this->command->getDisplay());

        $this->assertTrue(self::$workerExecuted);
    }
}
