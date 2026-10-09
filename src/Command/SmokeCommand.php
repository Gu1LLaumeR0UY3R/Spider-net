<?php

namespace App\Command;

use App\Dao\IncidentDaoInterface;
use App\Model\IncidentStatus;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(name: 'app:smoke', description: 'Test rapide du DAO incident')]
final class SmokeCommand extends Command
{
    public function __construct(private readonly IncidentDaoInterface $incidents)
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $id = $this->incidents->create(
            typeId: 1, reporterId: 1, severity: 3,
            latitude: 40.7580, longitude: -73.9855,
            altitude: 12.0, accuracy: 8.5, description: 'Test fumée',
        );
        $io->success("Incident créé: $id");

        $near = $this->incidents->findNearby(40.7585, -73.9850, 500);
        $io->writeln(sprintf('Proches (500 m): %d', count($near)));

        $this->incidents->changeStatus($id, IncidentStatus::Validated, 1, 'Smoke test');
        $io->writeln('Statut après: ' . $this->incidents->findById($id)?->status->value);

        return Command::SUCCESS;
    }
}