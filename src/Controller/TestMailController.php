<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class TestMailController extends AbstractController
{
    #[Route('/test-email', name: 'test_email')]
    public function sendTestEmail(MailerInterface $mailer): Response
    {
        $email = (new Email())
            ->from('no-reply@example.com')
            ->to('demo@example.com')
            ->subject('Test Mail depuis Symfony avec Mailpit')
            ->text('Ceci est un e-mail de test envoyé via Mailpit')
            ->html('<p>Ceci est un <strong>e-mail de test</strong> envoyé via Mailpit.</p>');

        $mailer->send($email);

        return new Response('E-mail de test envoyé avec succès. Vérifiez Mailpit sur http://localhost:8025');
    }
}
