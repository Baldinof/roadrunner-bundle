<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Command;

use Baldinof\RoadRunnerBundle\Exception\UnsupportedRoadRunnerModeException;
use Baldinof\RoadRunnerBundle\Worker\WorkerResolverInterface;
use Spiral\RoadRunner\EnvironmentInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

final class WorkerCommand extends Command
{
    protected static $defaultName = 'baldinof:roadrunner:worker';

    private WorkerResolverInterface $workerResolver;
    private EnvironmentInterface $environment;

    public function __construct(
        WorkerResolverInterface $workerResolver,
        EnvironmentInterface $environment
    )
    {
        parent::__construct();

        $this->workerResolver = $workerResolver;
        $this->environment = $environment;
    }

    public function configure(): void
    {
        $this
            ->setDescription('Run the roadrunner')
            ->setHelp(<<<EOF
            This command should not be run manually but specified in a <info>.rr.yaml</info>
            configuration file.
            EOF);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        try {
            $worker = $this->workerResolver->resolve($this->environment->getMode());
        } catch (UnsupportedRoadRunnerModeException $e) {
            $content = file_get_contents(__DIR__.'/../../.rr.dev.yaml');

            $io = new SymfonyStyle($input, $output);

            $io->title('RoadRunner Bundle');
            $io->error('Command baldinof:roadrunner:worker should not be run manually');
            $io->writeln('You should reference this command in a <info>.rr.yaml</> configuration file, then run <info>bin/rr serve</>. Example:');
            $io->writeln(<<<YAML
            <comment>
            {$content}
            </comment>
            YAML);

            $io->writeln('See <href=https://roadrunner.dev/>RoadRunner</> and <href=https://github.com/Baldinof/roadrunner-bundle/blob/2.x/README.md>baldinof/roadrunner-bundle</> documentations.');

            return 1;
        }

        $worker->start();

        return 0;
    }
}
