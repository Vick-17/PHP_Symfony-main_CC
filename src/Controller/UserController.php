<?php
namespace App\Controller;

use App\Document\Client;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Message\SendResetPasswordEmail;
use App\Message\SendEmailMessage;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use App\Service\SmsService;

/**
 * Contrôleur de gestion des utilisateurs (clients).
 * Permet l'inscription, la connexion, la gestion des utilisateurs, la réinitialisation du mot de passe, etc.
 */
class UserController extends AbstractController
{
    /**
     * Affiche le formulaire de connexion et gère l'authentification.
     */
    #[Route('/login', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        // Si l'utilisateur est déjà connecté, redirigez-le vers la page d'accueil
        if ($this->getUser()) {
            return $this->redirectToRoute('home');
        }

        // Récupérer les erreurs de connexion
        $error = $authenticationUtils->getLastAuthenticationError();
        $lastUsername = $authenticationUtils->getLastUsername();

        return $this->render('security/login.html.twig', [
            'last_username' => $lastUsername,
            'error' => $error,
        ]);
    }

    /**
     * Affiche la liste des utilisateurs (clients) pour l'administration.
     * Protégé par ROLE_ADMIN pour éviter qu'un simple utilisateur consulte tout le monde.
     */
    #[Route('/users', name: 'user_management', methods: ['GET'])]
    public function manageUsers(DocumentManager $dm): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette page.');
        }
        // Récupérer tous les utilisateurs (clients)
        $users = $dm->getRepository(Client::class)->findAll();

        return $this->render('client/index.html.twig', [
            'users' => $users,
        ]);
    }

    /**
     * Déconnecte l'utilisateur.
     * (La méthode est interceptée par le firewall de Symfony)
     */
    #[Route('/logout', name: 'app_logout', methods: ['GET'])]
    public function logout(): void
    {
        throw new \LogicException('This method can be blank - it will be intercepted by the logout key on your firewall.');
    }

    /**
     * Gère l'inscription d'un nouvel utilisateur (client).
     * Valide les mots de passe, l'unicité email/téléphone puis crée le compte avec un ID auto-incrémenté.
     */
    #[Route('/register', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, UserPasswordHasherInterface $passwordHasher, DocumentManager $dm): Response
    {
        if ($request->isMethod('POST')) {
            $nom = $request->request->get('nom');
            $email = $request->request->get('email');
            $telephone = $request->request->get('telephone');
            $password = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');

            // Vérifiez que les mots de passe correspondent
            if ($password !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                return $this->redirectToRoute('app_register');
            }

            // Vérifiez l'unicité de l'email
            $existingEmail = $dm->getRepository(Client::class)->findOneBy(['email' => $email]);
            if ($existingEmail) {
                $this->addFlash('error', 'Cet email est déjà utilisé.');
                return $this->redirectToRoute('app_register');
            }

            // Vérifiez l'unicité du numéro de téléphone
            $existingTelephone = $dm->getRepository(Client::class)->findOneBy(['telephone' => $telephone]);
            if ($existingTelephone) {
                $this->addFlash('error', 'Ce numéro de téléphone est déjà utilisé.');
                return $this->redirectToRoute('app_register');
            }

            // Créez un nouvel utilisateur
            $user = new Client();
            $user->setNom($nom);
            $user->setEmail($email);
            $user->setTelephone($telephone);
            $user->setPassword($passwordHasher->hashPassword($user, $password));

            // Rôle par défaut
            $user->setRoles(['ROLE_USER']);

            // Générer l'ID auto-incrémenté
            $lastUser = $dm->createQueryBuilder(Client::class)
                ->sort('autoIncrementId', 'DESC')
                ->limit(1)
                ->getQuery()
                ->getSingleResult();

            $nextId = $lastUser ? $lastUser->getAutoIncrementId() + 1 : 1;
            $user->setAutoIncrementId($nextId);

            // Persistez l'utilisateur dans la base de données
            $dm->persist($user);
            $dm->flush();

            $this->addFlash('success', 'Inscription réussie. Vous pouvez maintenant vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/register.html.twig');
    }

    /**
     * Gère la demande de réinitialisation de mot de passe.
     * Envoie un code de réinitialisation par email.
     * Le code et la date de demande sont stockés pour valider l'étape suivante.
     */
    #[Route('/forgot-password', name: 'app_forgot_password', methods: ['GET', 'POST'])]
    public function forgotPassword(Request $request, DocumentManager $dm, MessageBusInterface $bus): Response
    {
        if ($request->isMethod('POST')) {
            $email = $request->request->get('email');
            $user = $dm->getRepository(Client::class)->findOneBy(['email' => $email]);

            if (!$user) {
                $this->addFlash('error', 'Aucun utilisateur trouvé avec cet email.');
                return $this->redirectToRoute('app_forgot_password');
            }
            // Générer un code à 6 chiffres
            $code = random_int(100000, 999999);
            $user->setResetCode((string)$code);
            $user->setResetRequestedAt(new \DateTime());
            $dm->flush();

            // Envoyer le code par email via Messenger
            $bus->dispatch(new SendEmailMessage(
                $email,
                "Votre code de réinitialisation est : $code",
                'Code de réinitialisation'
            ));

            $this->addFlash('success', 'Un code de réinitialisation a été envoyé par email.');
            // Redirige vers la page de saisie du code
            return $this->redirectToRoute('app_check_code', ['email' => $email]);
        }

        return $this->render('security/forgot_password.html.twig');
    }

    /**
     * Vérifie le code de réinitialisation envoyé par email.
     * Bloque si le code est invalide ou expiré, sinon conserve l'email en session pour la suite.
     */
    #[Route('/check-code', name: 'app_check_code', methods: ['GET', 'POST'])]
    public function checkCode(Request $request, DocumentManager $dm): Response
    {
        $email = $request->query->get('email');
        if ($request->isMethod('POST')) {
            $code = $request->request->get('code');
            $user = $dm->getRepository(Client::class)->findOneBy(['email' => $email]);

            if (
                !$user ||
                !$user->getResetCode() ||
                $user->getResetCode() !== $code ||
                !$user->getResetRequestedAt()
            ) {
                $this->addFlash('error', 'Code invalide ou expiré.');
                return $this->redirectToRoute('app_forgot_password');
            }

            $expiresAt = (clone $user->getResetRequestedAt())->modify('+1 hour');
            if ($expiresAt < new \DateTimeImmutable()) {
                $this->addFlash('error', 'Code invalide ou expiré.');
                return $this->redirectToRoute('app_forgot_password');
            }

            // Stocke l'email en session pour la suite
            $request->getSession()->set('reset_email', $email);

            // Redirige vers la page de réinitialisation du mot de passe
            return $this->redirectToRoute('app_reset_password_code');
        }

        return $this->render('security/check_code.html.twig', ['email' => $email]);
    }

    /**
     * Permet à l'utilisateur de saisir un nouveau mot de passe après validation du code.
     * Efface le code en base une fois le mot de passe mis à jour.
     */
    #[Route('/reset-password-code', name: 'app_reset_password_code', methods: ['GET', 'POST'])]
    public function resetPasswordCode(
        Request $request,
        DocumentManager $dm,
        UserPasswordHasherInterface $passwordHasher
    ): Response {
        $email = $request->getSession()->get('reset_email');
        $user = $dm->getRepository(Client::class)->findOneBy(['email' => $email]);

        if (!$user) {
            $this->addFlash('error', 'Utilisateur introuvable.');
            return $this->redirectToRoute('app_forgot_password');
        }

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password');
            $confirmPassword = $request->request->get('confirm_password');
            if ($password !== $confirmPassword) {
                $this->addFlash('error', 'Les mots de passe ne correspondent pas.');
                return $this->redirectToRoute('app_reset_password_code');
            }
            $user->setPassword($passwordHasher->hashPassword($user, $password));
            $user->setResetCode(null);
            $user->setResetRequestedAt(null);
            $dm->flush();

            $this->addFlash('success', 'Mot de passe réinitialisé. Vous pouvez vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/reset_password.html.twig');
    }
}
