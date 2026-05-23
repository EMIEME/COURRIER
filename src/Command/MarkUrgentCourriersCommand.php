<?php

namespace App\Command;

use App\Service\CourrierUrgencyUpdater;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

#[AsCommand(name: 'app:courriers:mark-urgent', description: 'Passe en urgent les courriers en cours dont l echeance de reponse est depassee.')]
class MarkUrgentCourriersCommand extends Command
{
    public function __construct(
        private readonly CourrierUrgencyUpdater $urgencyUpdater,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $updated = $this->urgencyUpdater->updateOverdueCourriers();

        if (0 === $updated) {
            $output->writeln('<info>Aucun courrier a passer en urgent.</info>');

            return Command::SUCCESS;
        }

        $output->writeln(sprintf('<info>%d courrier(s) passe(s) en urgent.</info>', $updated));

        return Command::SUCCESS;
    }
}
