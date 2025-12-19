<?php

namespace App\Command;

use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

#[AsCommand(
    name: 'app:send-mail',
    description: 'Envoie un email à une adresse donnée'
)]
class SendMailCommand extends Command
{
    private $mailer;

    public function __construct(MailerInterface $mailer)
    {
        parent::__construct();
        $this->mailer = $mailer;
    }

    protected function configure()
    {
        $this
            ->addArgument('to', InputArgument::REQUIRED, 'Adresse email du destinataire')
            ->addArgument('subject', InputArgument::OPTIONAL, 'Sujet du mail', 'Test Symfony Mailer')
            ->addArgument('body', InputArgument::OPTIONAL, 'Contenu du mail', 'ça marche fréro.');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $email = (new Email())
            ->from('no-reply@example.com')
            ->to($input->getArgument('to'))
            ->subject($input->getArgument('subject'))
            ->text($input->getArgument('body'));

        $this->mailer->send($email);

        $output->writeln('Email envoyé à ' . $input->getArgument('to'));
        return Command::SUCCESS;
    }
}
