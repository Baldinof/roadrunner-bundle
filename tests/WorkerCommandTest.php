<?php

declare(strict_types=1);

namespace Tests\Baldinof\RoadRunnerBundle;

use Baldinof\RoadRunnerBundle\Command\WorkerCommand;
use Baldinof\RoadRunnerBundle\Worker\WorkerInterface;
use PHPUnit\Framework\TestCase;
use Spiral\RoadRunner\Environment\Mode;
use Symfony\Component\Console\Tester\CommandTester;

use function putenv;

class WorkerCommandTest extends TestCase
{
    public static bool $workerExecuted;

    private CommandTester $command;

    public function setUp(): void
    {
        self::$workerExecuted = false;

        $worker = new class() implements WorkerInterface {
            public function start(): void
            {
                WorkerCommandTest::$workerExecuted = true;
            }
        };

        $this->command = new CommandTester(new WorkerCommand($worker));
    }

    protected function tearDown(): void
    {
        putenv('RR_MODE'); // Reset after every test
    }

    public function test_it_displays_help_on_manual_run()
    {
        $this->command->execute([]);

        $this->assertStringContainsString('should not be run manually', $this->command->getDisplay());
    }

    public function test_it_start_the_worker_when_ran_by_roadrunner()
    {
        putenv('RR_MODE='.Mode::MODE_HTTP);

        $this->command->execute([]);

        $this->assertEmpty($this->command->getDisplay());

        $this->assertTrue(self::$workerExecuted);
    }

    public function test_it_does_not_start_the_worker_when_ran_without_roadrunner()
    {
        $this->command->execute([]);

        $this->assertNotEmpty($this->command->getDisplay());

        $this->assertFalse(self::$workerExecuted);
    }
}
