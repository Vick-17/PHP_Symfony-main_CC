<?php
namespace App\Controller;

use App\Document\Client;
use App\Document\Hotel;
use App\Document\Reservation;
use App\Document\Chambre;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use App\Document\Comment;

/**
 * Contrôleur de gestion des réservations.
 * Permet d'afficher, commenter, annuler et supprimer des réservations.
 */
class ReservationController extends AbstractController
{
    private CsrfTokenManagerInterface $csrfTokenManager;

    public function __construct(CsrfTokenManagerInterface $csrfTokenManager)
    {
        $this->csrfTokenManager = $csrfTokenManager;
    }

    /**
     * Affiche les réservations de l'utilisateur connecté.
     * Renvoie vers la page de connexion si personne n'est authentifié.
     */
    #[Route('/mes-reservations', name: 'user_reservations', methods: ['GET'])]
    public function userReservations(DocumentManager $dm): Response
    {
        $user = $this->getUser();

        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour accéder à vos réservations.');
            return $this->redirectToRoute('app_login');
        }

        $reservations = $dm->getRepository(Reservation::class)->findBy(['client' => $user]);

        return $this->render('reservation/index.html.twig', [
            'reservations' => $reservations,
        ]);
    }

    /**
     * Affiche toutes les réservations pour l'administration avec vérification des clients.
     * Ajoute une pagination simple et une recherche par numéro.
     */
    #[Route('/admin/reservations', name: 'admin_reservations', methods: ['GET'])]
    public function adminReservations(Request $request, DocumentManager $dm): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette page.');
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $numReservation = trim((string) $request->query->get('numReservation', ''));

        $qb = $dm->createQueryBuilder(Reservation::class);
        if ($numReservation !== '') {
            $qb->field('numeroReservation')->equals(new \MongoDB\BSON\Regex($numReservation, 'i'));
        }

        $countQb = clone $qb;
        $total = $countQb->getQuery()->count();

        $reservations = $qb->skip($offset)->limit($limit)->getQuery()->execute();
        $totalPages = (int) ceil($total / $limit);

        foreach ($reservations as $reservation) {
            if (!$reservation->getClient()) {
                $this->addFlash('warning', 'Une réservation sans client a été trouvée.');
            }
        }

        return $this->render('reservation/admin_index.html.twig', [
            'reservations' => $reservations,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'numReservation' => $numReservation,
        ]);
    }

    /**
     * Détail d'une réservation (administration).
     * Lève une 404 si l'identifiant n'existe pas.
     */
    #[Route('/admin/reservations/{id}', name: 'admin_reservation_show', methods: ['GET'])]
    public function adminReservationShow(string $id, DocumentManager $dm): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette page.');
        }

        $reservation = $dm->getRepository(Reservation::class)->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException('Réservation non trouvée.');
        }

        return $this->render('reservation/admin_show.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    /**
     * Création d'une réservation côté administrateur.
     * Vérifie la cohérence des dates, le rattachement des chambres à l'hôtel choisi et la disponibilité.
     */
    #[Route('/admin/reservations/new', name: 'admin_reservation_new', methods: ['GET', 'POST'])]
    public function adminReservationNew(Request $request, DocumentManager $dm): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette page.');
        }

        $clients = $dm->getRepository(Client::class)->findAll();
        $hotels = $dm->getRepository(Hotel::class)->findAll();
        $chambres = $dm->getRepository(Chambre::class)->findAll();

        if ($request->isMethod('POST')) {
            $client = $dm->getRepository(Client::class)->find($request->request->get('client_id'));
            $hotel = $dm->getRepository(Hotel::class)->find($request->request->get('hotel_id'));
            $selectedChambreIds = (array) $request->request->all('chambre_ids');
            $dateDebut = new \DateTime($request->request->get('date_debut'));
            $dateFin = new \DateTime($request->request->get('date_fin'));

            if (!$client || !$hotel) {
                $this->addFlash('error', 'Client ou hôtel invalide.');
                return $this->redirectToRoute('admin_reservation_new');
            }

            if ($dateFin <= $dateDebut) {
                $this->addFlash('error', 'La date de fin doit être postérieure à la date de début.');
                return $this->redirectToRoute('admin_reservation_new');
            }

            if (empty($selectedChambreIds)) {
                $this->addFlash('error', 'Veuillez sélectionner au moins une chambre.');
                return $this->redirectToRoute('admin_reservation_new');
            }

            $selectedChambres = [];
            foreach ($selectedChambreIds as $chambreId) {
                $chambre = $dm->getRepository(Chambre::class)->find($chambreId);
                if (!$chambre || $chambre->getHotel()?->getId() !== $hotel->getId()) {
                    $this->addFlash('error', 'Les chambres doivent appartenir à l’hôtel sélectionné.');
                    return $this->redirectToRoute('admin_reservation_new');
                }
                if (!$this->isChambreDisponible($chambre, $dm, $dateDebut, $dateFin)) {
                    $this->addFlash('error', sprintf('La chambre %s est déjà réservée sur cette période.', $chambre->getNumero()));
                    return $this->redirectToRoute('admin_reservation_new');
                }
                $selectedChambres[] = $chambre;
            }

            $reservation = new Reservation();
            $reservation->setHotel($hotel);
            $reservation->setClient($client);
            $reservation->setDateDebut($dateDebut);
            $reservation->setDateFin($dateFin);
            foreach ($selectedChambres as $chambre) {
                $reservation->addChambre($chambre);
            }
            $reservation->setNumeroReservation('RES-' . date('YmdHis') . '-' . random_int(100, 999));

            $dm->persist($reservation);
            $dm->flush();

            $this->addFlash('success', 'Réservation créée avec succès.');
            return $this->redirectToRoute('admin_reservations');
        }

        return $this->render('reservation/admin_form.html.twig', [
            'clients' => $clients,
            'hotels' => $hotels,
            'chambres' => $chambres,
            'reservation' => null,
        ]);
    }

    /**
     * Edition d'une réservation côté administrateur.
     * Reprend les validations de création (dates, hôtel, chambres) et remplace la liste de chambres.
     */
    #[Route('/admin/reservations/{id}/edit', name: 'admin_reservation_edit', methods: ['GET', 'POST'])]
    public function adminReservationEdit(string $id, Request $request, DocumentManager $dm): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette page.');
        }

        $reservation = $dm->getRepository(Reservation::class)->find($id);
        if (!$reservation) {
            throw $this->createNotFoundException('Réservation non trouvée.');
        }

        $clients = $dm->getRepository(Client::class)->findAll();
        $hotels = $dm->getRepository(Hotel::class)->findAll();
        $chambres = $dm->getRepository(Chambre::class)->findAll();

        if ($request->isMethod('POST')) {
            $client = $dm->getRepository(Client::class)->find($request->request->get('client_id'));
            $hotel = $dm->getRepository(Hotel::class)->find($request->request->get('hotel_id'));
            $selectedChambreIds = (array) $request->request->all('chambre_ids');
            $dateDebut = new \DateTime($request->request->get('date_debut'));
            $dateFin = new \DateTime($request->request->get('date_fin'));

            if (!$client || !$hotel) {
                $this->addFlash('error', 'Client ou hôtel invalide.');
                return $this->redirectToRoute('admin_reservation_edit', ['id' => $id]);
            }

            if ($dateFin <= $dateDebut) {
                $this->addFlash('error', 'La date de fin doit être postérieure à la date de début.');
                return $this->redirectToRoute('admin_reservation_edit', ['id' => $id]);
            }

            if (empty($selectedChambreIds)) {
                $this->addFlash('error', 'Veuillez sélectionner au moins une chambre.');
                return $this->redirectToRoute('admin_reservation_edit', ['id' => $id]);
            }

            $selectedChambres = [];
            foreach ($selectedChambreIds as $chambreId) {
                $chambre = $dm->getRepository(Chambre::class)->find($chambreId);
                if (!$chambre || $chambre->getHotel()?->getId() !== $hotel->getId()) {
                    $this->addFlash('error', 'Les chambres doivent appartenir à l’hôtel sélectionné.');
                    return $this->redirectToRoute('admin_reservation_edit', ['id' => $id]);
                }
                if (!$this->isChambreDisponible($chambre, $dm, $dateDebut, $dateFin, $reservation->getId())) {
                    $this->addFlash('error', sprintf('La chambre %s est déjà réservée sur cette période.', $chambre->getNumero()));
                    return $this->redirectToRoute('admin_reservation_edit', ['id' => $id]);
                }
                $selectedChambres[] = $chambre;
            }

            $reservation->setClient($client);
            $reservation->setHotel($hotel);
            $reservation->setDateDebut($dateDebut);
            $reservation->setDateFin($dateFin);
            $reservation->setChambres($selectedChambres);

            $dm->flush();

            $this->addFlash('success', 'Réservation mise à jour.');
            return $this->redirectToRoute('admin_reservations');
        }

        return $this->render('reservation/admin_form.html.twig', [
            'clients' => $clients,
            'hotels' => $hotels,
            'chambres' => $chambres,
            'reservation' => $reservation,
        ]);
    }

    /**
     * Affiche toutes les chambres et leurs commentaires pour l'administration.
     * Combine pagination, recherche texte et remontée des avis associés.
     */
    #[Route('/admin/chambres', name: 'admin_chambres', methods: ['GET'])]
    public function adminChambres(Request $request, DocumentManager $dm): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette page.');
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;
        $search = trim((string) $request->query->get('search', ''));

        $qb = $dm->createQueryBuilder(Chambre::class);
        if ($search !== '') {
            $regex = new \MongoDB\BSON\Regex($search, 'i');
            $qb->addOr($qb->expr()->field('numero')->equals($regex))
               ->addOr($qb->expr()->field('type')->equals($regex));
        }

        $countQb = clone $qb;
        $total = $countQb->getQuery()->count();

        $chambres = $qb->skip($offset)->limit($limit)->getQuery()->execute();
        $totalPages = (int) ceil($total / $limit);

        // Récupérer les commentaires associés à chaque chambre
        $comments = [];
        foreach ($chambres as $chambre) {
            $reservations = $dm->createQueryBuilder(Reservation::class)
                ->field('chambres')->equals($chambre)
                ->getQuery()
                ->execute();
            $comments[$chambre->getId()] = $dm->createQueryBuilder(Comment::class)
                ->field('reservation')->in(iterator_to_array($reservations))
                ->getQuery()
                ->execute();
        }

        return $this->render('admin/chambres.html.twig', [
            'chambres' => $chambres,
            'comments' => $comments,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'search' => $search,
        ]);
    }

    /**
     * Permet à un utilisateur de commenter une réservation.
     * Vérifie que le réservataire est bien l'auteur du commentaire.
     */
    #[Route('/reservation/{id}/comment', name: 'reservation_comment', methods: ['GET', 'POST'])]
    public function comment(string $id, Request $request, DocumentManager $dm): Response
    {
        // Vérifie que l'utilisateur est connecté
        $user = $this->getUser();
        if (!$user) {
            $this->addFlash('error', 'Vous devez être connecté pour commenter cette réservation.');
            return $this->redirectToRoute('app_login');
        }

        $reservation = $dm->getRepository(Reservation::class)->find($id);

        if (!$reservation) {
            throw $this->createNotFoundException('Réservation non trouvée.');
        }

        // Vérifie que l'utilisateur connecté est bien le client associé à la réservation
        if ($reservation->getClient() !== $user) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à commenter cette réservation.');
            return $this->redirectToRoute('user_reservations');
        }

        if ($request->isMethod('POST')) {
            $content = $request->request->get('content');

            if (!$content) {
                $this->addFlash('error', 'Le commentaire ne peut pas être vide.');
                return $this->redirectToRoute('reservation_comment', ['id' => $id]);
            }

            $comment = new Comment();
            $comment->setContent($content);
            $comment->setCreatedAt(new \DateTime());
            $comment->setReservation($reservation);
            $comment->setAuthor($user);

            $dm->persist($comment);
            $dm->flush();

            $this->addFlash('success', 'Votre commentaire a été ajouté avec succès.');
            return $this->redirectToRoute('user_reservations');
        }

        // Affiche le formulaire de commentaire
        return $this->render('reservation/comment.html.twig', [
            'reservation' => $reservation,
        ]);
    }

    /**
     * Supprime une réservation (administration).
     * Affiche un message clair si la réservation est introuvable.
     */
    #[Route('/reservations/{id}/delete', name: 'reservation_delete', methods: ['POST'])]
    public function delete(string $id, DocumentManager $dm): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette page.');
        }

        $reservation = $dm->getRepository(Reservation::class)->find($id);

        if (!$reservation) {
            throw $this->createNotFoundException('Réservation non trouvée.');
        }

        $dm->remove($reservation);
        $dm->flush();

        $this->addFlash('success', 'Réservation supprimée avec succès.');
        return $this->redirectToRoute('admin_reservations');
    }

    /**
     * Permet à un utilisateur d'annuler sa propre réservation avec vérification CSRF.
     * Compare l'utilisateur courant avec le client de la réservation avant suppression.
     */
    #[Route('/reservations/{id}/cancel', name: 'reservation_cancel', methods: ['POST'])]
    public function cancelReservation(string $id, Request $request, DocumentManager $dm): Response
    {
        $reservation = $dm->getRepository(Reservation::class)->find($id);

        if (!$reservation) {
            $this->addFlash('error', 'Réservation non trouvée.');
            return $this->redirectToRoute('user_reservations');
        }

        // Vérifie que l'utilisateur connecté est bien le propriétaire de la réservation
        if ($reservation->getClient() !== $this->getUser()) {
            $this->addFlash('error', 'Vous n\'êtes pas autorisé à annuler cette réservation.');
            return $this->redirectToRoute('user_reservations');
        }

        // Vérifie le token CSRF
        $submittedToken = $request->request->get('_token');
        if (!$this->csrfTokenManager->isTokenValid(new CsrfToken('delete' . $reservation->getId(), $submittedToken))) {
            $this->addFlash('error', 'Action non autorisée.');
            return $this->redirectToRoute('user_reservations');
        }

        $dm->remove($reservation);
        $dm->flush();

        $this->addFlash('success', 'Réservation annulée avec succès.');
        return $this->redirectToRoute('user_reservations');
    }

    /**
     * Vérifie la disponibilité d'une chambre pour une période donnée.
     */
    private function isChambreDisponible(Chambre $chambre, DocumentManager $dm, \DateTime $dateDebut, \DateTime $dateFin, ?string $ignoreReservationId = null): bool
    {
        $qb = $dm->createQueryBuilder(Reservation::class);
        $qb->field('chambres')->equals($chambre)
           ->field('dateDebut')->lte($dateFin)
           ->field('dateFin')->gte($dateDebut);

        if ($ignoreReservationId) {
            $qb->field('id')->notEqual($ignoreReservationId);
        }

        $count = $qb->count()->getQuery()->execute();

        return $count === 0;
    }
}

