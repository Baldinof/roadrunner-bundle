<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Temporal\Command;

use Baldinof\RoadRunnerBundle\Worker\TemporalWorker;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('temporal:debug:workers')]
class DebugWorkersCommand extends Command
{
    public function __construct(private readonly TemporalWorker $temporalWorker)
    {
        parent::__construct();
    }


    protected function configure(): void
    {
        $this->addArgument('worker', InputArgument::OPTIONAL, 'Name of the worker to display details for');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        /** @var string|null $workerName */
        $workerName = $input->getArgument('worker');

        if (!empty($workerName)) {
            $worker = $this->temporalWorker->getWorkers()[$workerName] ?? null;
            if (!$worker) {
                $io->error("Worker '$workerName' not configured.");
                return Command::FAILURE;
            }

            $io->title("Worker: $workerName (Queue: {$worker->getID()})");

            $io->section('Workflows');
            $io->table(['Workflow'], array_map(
                fn($w) => [$w->getClass()->getName()],
                iterator_to_array($worker->getWorkflows())
            ));

            $io->section('Activities');
            $grouped = [];
            foreach ($worker->getActivities() as $activity) {
                $className = $activity->getClass()->getName();
                $grouped[$className][] = $activity->getID();
            }

            $rows = [];
            foreach ($grouped as $class => $methods) {
                $rows[] = [$class, implode("\n", $methods)];
            }
            $io->table(['Class', 'Activities'], $rows);


        } else {
            $rows = [];
            foreach ($this->temporalWorker->getWorkers() as $name => $worker) {
                $rows[] = [
                    $name,
                    $worker->getID(),
                    count($worker->getWorkflows()),
                    count($worker->getActivities())
                ];
            }

            $io->table([ 'Name', 'Queue', '# Workflow', '# Activities' ], $rows);
        }


        return Command::SUCCESS;
    }

}