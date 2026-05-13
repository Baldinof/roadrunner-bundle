<?php

declare(strict_types=1);

namespace Baldinof\RoadRunnerBundle\Temporal\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand('temporal:debug:clients')]
class DebugClientsCommand extends Command
{
    /**
     * @param array<int, array{name: string, address: string, namespace: string, default: bool}> $clientsInfo
     */
    public function __construct(private readonly array $clientsInfo)
    {
        parent::__construct();
    }

    protected function configure(): void
    {
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $rows = [];
        foreach ($this->clientsInfo as $client) {
            $rows[] = [
                $client['name'].($client['default'] ? ' *' : ''),
                $client['address'],
                $client['namespace'],
            ];
        }

        $io->table(['Name', 'Address', 'Namespace'], $rows);

        return Command::SUCCESS;
    }
}
