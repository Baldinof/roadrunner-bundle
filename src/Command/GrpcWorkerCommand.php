<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Command;

use Baldinof\RoadRunnerBundle\Worker\GrpcWorkerInterface;
use Spiral\RoadRunner\Environment\Mode;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class GrpcWorkerCommand extends Command
{
    protected static $defaultName = 'baldinof:roadrunner:grpc-worker';

    private GrpcWorkerInterface $worker;

    public function __construct(GrpcWorkerInterface $worker)
    {
        parent::__construct();

        $this->worker = $worker;
    }

    public function configure(): void
    {
        $this
            ->setDescription('Run the roadrunner grpc worker')
            ->setHelp(<<<EOF
            This command should not be run manually but specified in a <info>.rr.yaml</info>
            configuration file.
            EOF);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        if (getenv('RR_MODE') !== Mode::MODE_GRPC) {
            $io = new SymfonyStyle($input, $output);

            $io->title('RoadRunner Bundle');
            $io->error('Command baldinof:roadrunner:grpc-worker should not be run manually');
            $io->writeln('You should reference this command in a <info>.rr.yaml</> configuration file, then run <info>bin/rr serve</>. Example:');
            $io->writeln(<<<YAML
            <comment>
            server:
                command: "php bin/console baldinof:roadrunner:grpc-worker"
                # If you are using symfony 5.3+ and the new Runtime component:
                # remove the previous `command` line above and uncomment the line below.
                # command: "php public/index.php"
                env:
                    APP_RUNTIME: Baldinof\RoadRunnerBundle\Runtime\Runtime

            grpc:
                listen: "tcp://:9001"

                proto:
                    - "first.proto"
                    - "second.proto"
            </comment>
            YAML);

            $io->writeln('See <href=https://roadrunner.dev/>RoadRunner</> and <href=https://github.com/Baldinof/roadrunner-bundle/blob/master/README.md>baldinof/roadrunner-bundle</> documentations.');

            return 1;
        }

        $this->worker->start();

        return 0;
    }
}
