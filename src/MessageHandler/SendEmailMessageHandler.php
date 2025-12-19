<?php
namespace App\MessageHandler;

use App\Message\SendEmailMessage;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendEmailMessageHandler
{
    private MailerInterface $mailer;

    public function __construct(MailerInterface $mailer)
    {
        $this->mailer = $mailer;
    }

    public function __invoke(SendEmailMessage $message)
    {
        $email = (new Email())
            ->from('ton-adresse@example.com')
            ->to($message->getEmail())
            ->subject($message->getSubject())
            ->text($message->getContent());

        $this->mailer->send($email);
    }
}
