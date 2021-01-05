<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Command;

use Baldinof\RoadRunnerBundle\Worker\GrpcWorkerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class GrpcWorkerCommand extends Command
{
    protected static $defaultName = 'baldinof:roadrunner:grpc-worker';

    private $worker;

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
        if (!getenv('RR_GRPC')) {
            $io = new SymfonyStyle($input, $output);

            $io->title('RoadRunner Bundle');
            $io->error('Command baldinof:roadrunner:grpc-worker should not be run manually');

            $io->writeln('See documentations - https://spiral.dev/docs/grpc-configuration.');

            return 1;
        }

        $this->worker->start();

        return 0;
    }
}
