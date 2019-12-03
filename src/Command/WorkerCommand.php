<?php

namespace Baldinof\RoadRunnerBundle\Command;

use Baldinof\RoadRunnerBundle\Worker\WorkerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class WorkerCommand extends Command
{
    protected static $defaultName = 'baldinof:roadrunner:worker';

    private $worker;

    public function __construct(WorkerInterface $worker)
    {
        parent::__construct();

        $this->worker = $worker;
    }

    public function configure(): void
    {
        $this
            ->setDescription('Run the roadrunner worker')
            ->setHelp(<<<EOF
            This command should not be run manually but specified in a <info>.rr.yaml</info>
            configuration file.
            EOF);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        $this->worker->start();

        return 0;
    }
}
