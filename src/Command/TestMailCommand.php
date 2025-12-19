<?php
namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:test-mail',
    description: 'Teste l’envoi d’un mail avec Symfony Mailer.',
)]
class TestMailCommand extends Command
{
    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        parent::__construct();
        $this->mailer = $mailer;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (new Email())
            ->from('no-reply@example.com')
            ->to('test@example.com') // Tu verras ce mail dans Mailpit
            ->subject('Test Mailpit via Symfony Console')
            ->text('Ceci est un email de test envoyé depuis une commande Symfony.')
            ->html('<p><strong>Ceci est un email de test</strong> envoyé depuis une commande Symfony.</p>');

        $this->mailer->send($email);
        $output->writeln('✅ Mail envoyé avec succès ! Vérifie Mailpit.');

        return Command::SUCCESS;
    }
}
