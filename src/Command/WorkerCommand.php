<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Command;

use Baldinof\RoadRunnerBundle\Worker\WorkerResolverInterface;
use Spiral\RoadRunner\EnvironmentInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

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
        $worker = $this->workerResolver->resolve($this->environment->getMode());
        $worker->start();

        return 0;
    }
}
