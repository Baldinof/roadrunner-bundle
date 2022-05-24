<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Command;

use Baldinof\RoadRunnerBundle\Worker\WorkerInterface;
use Spiral\RoadRunner\Environment\Mode;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class WorkerCommand extends Command
{
    protected static $defaultName = 'baldinof:roadrunner:worker';

    private WorkerInterface $worker;

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

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (getenv('RR_MODE') !== Mode::MODE_HTTP) {
            $io = new SymfonyStyle($input, $output);

            $io->title('RoadRunner Bundle');
            $io->error('Command baldinof:roadrunner:worker should not be run manually');
            $io->writeln('You should reference this command in a <info>.rr.yaml</> configuration file, then run <info>bin/rr serve</>. Example:');
            $io->writeln(<<<YAML
            <comment>
            http:
                address: "0.0.0.0:8080"

                uploads:
                    forbid: [".php", ".exe", ".bat"]

                workers:
                    command: "php bin/console baldinof:roadrunner:worker"
                    relay: "unix://var/roadrunner.sock"

            static:
                dir:   "public"
                forbid: [".php", ".htaccess"]
            </comment>
            YAML);

            $io->writeln('See <href=https://roadrunner.dev/>RoadRunner</> and <href=https://github.com/Baldinof/roadrunner-bundle/blob/master/README.md>baldinof/roadrunner-bundle</> documentations.');

            return 1;
        }

        $this->worker->start();

        return 0;
    }
}
