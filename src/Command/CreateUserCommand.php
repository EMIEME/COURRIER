<?php

namespace App\Command;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

#[AsCommand(name: 'app:create-user', description: 'Cree un utilisateur applicatif.')]
class CreateUserCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Email de connexion')
            ->addArgument('password', InputArgument::REQUIRED, 'Mot de passe')
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Nom complet')
            ->addOption('role', null, InputOption::VALUE_REQUIRED | InputOption::VALUE_IS_ARRAY, 'Role a attribuer', ['ROLE_ADMIN']);
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (string) $input->getArgument('email');
        $name = (string) ($input->getOption('name') ?: $email);

        $user = (new User())
            ->setEmail($email)
            ->setFullName($name)
            ->setRoles($input->getOption('role'));

        $user->setPassword($this->passwordHasher->hashPassword($user, (string) $input->getArgument('password')));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $output->writeln(sprintf('<info>Utilisateur %s cree.</info>', $email));

        return Command::SUCCESS;
    }
}
