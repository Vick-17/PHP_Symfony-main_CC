<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Contrôleur de test pour vérifier que l'envoi d'e-mails fonctionne en local.
 * Sert à valider la configuration Mailpit sans passer par un formulaire.
 */
class TestMailController extends AbstractController
{
    /**
     * Envoie un e-mail simple vers une adresse de démo et affiche un message de confirmation.
     */
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
