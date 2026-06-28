<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Component\Mime\Email;

#[AsCommand(name: 'app:mailer:test', description: 'Envoie un email de test pour valider la configuration SMTP.')]
class TestMailerCommand extends Command
{
    public function __construct(
        private readonly MailerInterface $mailer,
        #[Autowire('%env(MAILER_DSN)%')]
        private readonly string $mailerDsn,
        #[Autowire('%app.mail_from_address%')]
        private readonly string $fromAddress,
        #[Autowire('%app.mail_from_name%')]
        private readonly string $fromName,
        #[Autowire('%app.mail_reply_to_address%')]
        private readonly string $replyToAddress,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addArgument('recipient', InputArgument::REQUIRED, 'Adresse email qui recevra le message de test');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if (str_starts_with($this->mailerDsn, 'null://')) {
            $output->writeln('<error>MAILER_DSN utilise le transport null://null : aucun email ne sera envoyé.</error>');

            return Command::FAILURE;
        }

        $recipient = trim((string) $input->getArgument('recipient'));

        if ('' === $recipient) {
            $output->writeln('<error>Le destinataire est requis.</error>');

            return Command::FAILURE;
        }

        $email = (new Email())
            ->from(new Address($this->fromAddress, $this->fromName))
            ->to(new Address($recipient))
            ->subject('Test SMTP - Gestion Courrier')
            ->text('Si vous recevez ce message, la configuration SMTP de Gestion Courrier fonctionne.');

        if ('' !== trim($this->replyToAddress)) {
            $email->replyTo(new Address($this->replyToAddress, $this->fromName));
        }

        try {
            $this->mailer->send($email);
        } catch (TransportExceptionInterface $exception) {
            $output->writeln('<error>Le test SMTP a échoué.</error>');
            $output->writeln($exception->getMessage());

            return Command::FAILURE;
        } catch (\Throwable $exception) {
            $output->writeln('<error>Le test email a échoué.</error>');
            $output->writeln($exception->getMessage());

            return Command::FAILURE;
        }

        $output->writeln(sprintf('<info>Email de test envoyé à %s.</info>', $recipient));

        return Command::SUCCESS;
    }
}
