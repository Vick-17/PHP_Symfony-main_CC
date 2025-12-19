<?php
namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

/**
 * Contrôleur de gestion des envois d'e-mails.
 * Permet d'envoyer un e-mail via un formulaire.
 */
class MailController extends AbstractController
{
    /**
     * Affiche un formulaire d'envoi d'e-mail et traite l'envoi.
     *
     * @param Request $request La requête HTTP
     * @param MailerInterface $mailer Le service d'envoi d'e-mails de Symfony
     * @return Response
     */
    #[Route('/mail/send', name: 'mail_send', methods: ['GET', 'POST'])]
    public function sendMail(Request $request, MailerInterface $mailer): Response
    {
        $sent = false;

        if ($request->isMethod('POST')) {
            // Récupère les données du formulaire
            $to = $request->request->get('to');
            $subject = $request->request->get('subject');
            $message = $request->request->get('message');

            // Crée l'e-mail à envoyer
            $email = (new Email())
                ->from('no-reply@example.com')
                ->to($to)
                ->subject($subject)
                ->text($message);

            // Envoie l'e-mail
            $mailer->send($email);
            $sent = true;
        }

        // Affiche le formulaire et l'état d'envoi
        return $this->render('mail/send.html.twig', [
            'sent' => $sent
        ]);
    }
}