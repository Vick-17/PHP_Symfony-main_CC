<?php
namespace App\Controller;

use App\Document\Hotel;
use Doctrine\ODM\MongoDB\DocumentManager;
use App\Document\Chambre;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Document\Reservation;

/**
 * Contrôleur vitrine pour les hôtels.
 * Regroupe l'accueil, la recherche, la réservation et la gestion admin des hôtels.
 */
#[Route('/hotel')]
class HotelController extends AbstractController




    {
    /**
     * Page d'accueil publique avec pagination basique sur les hôtels.
     * Calcule aussi le nombre de pages pour le template.
     */
    #[Route('/accueil', name: 'home', methods: ['GET'])]
    public function home(Request $request, DocumentManager $dm): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $limit = 10;
        $offset = ($page - 1) * $limit;

        $repository = $dm->getRepository(Hotel::class);
        $hotels = $repository->findBy([], null, $limit, $offset);

        $totalHotels = $repository->createQueryBuilder()
            ->count()
            ->getQuery()
            ->execute();

        $totalPages = (int) ceil($totalHotels / $limit);
        $allHotels = $repository->findAll();
        $noms = array_map(fn($h) => $h->getNom(), $allHotels);

        return $this->render('home/index.html.twig', [
            'hotels' => $hotels,
            'currentPage' => $page,
            'totalPages' => $totalPages,
            'noms' => $noms,
            'query' => '',
            'dateDebut' => null,
            'dateFin' => null,
        ]);
    }

    /**
     * Enregistre une réservation pour un hôtel donné.
     * Vérifie l'authentification, la cohérence des dates et la dispo de chaque chambre.
     */
    #[Route('/hotel/{id}/reserve', name: 'hotel_reserve', methods: ['POST'])]
    public function reserveRoom(string $id, Request $request, DocumentManager $dm): Response
    {
        $hotel = $dm->getRepository(Hotel::class)->find($id);

        if (!$hotel) {
            throw $this->createNotFoundException('Hôtel non trouvé.');
        }

        if (!$this->isGranted('IS_AUTHENTICATED_FULLY')) {
            $this->addFlash('error', 'Vous devez être connecté pour réserver une chambre.');
            return $this->redirectToRoute('app_login');
        }

        $chambreIds = (array) $request->request->all('chambre_ids');
        if (empty($chambreIds)) {
            $this->addFlash('error', 'Veuillez sélectionner au moins une chambre.');
            return $this->redirectToRoute('hotel_show', ['id' => $hotel->getId()]);
        }

        $dateDebut = new \DateTime($request->request->get('date_debut'));
        $dateFin = new \DateTime($request->request->get('date_fin'));

        if ($dateFin <= $dateDebut) {
            $this->addFlash('error', 'La date de fin doit être postérieure à la date de début.');
            return $this->redirectToRoute('hotel_show', ['id' => $hotel->getId()]);
        }

        $selectedChambres = [];
        foreach ($chambreIds as $chambreId) {
            $chambre = $dm->getRepository(Chambre::class)->find($chambreId);
            if (!$chambre) {
                $this->addFlash('error', 'Chambre non trouvée.');
                return $this->redirectToRoute('hotel_show', ['id' => $hotel->getId()]);
            }

            if (!$this->isChambreDisponible($chambre, $dm, $dateDebut, $dateFin)) {
                $this->addFlash('error', sprintf('La chambre %s est déjà réservée sur cette période.', $chambre->getNumero()));
                return $this->redirectToRoute('hotel_show', ['id' => $hotel->getId()]);
            }
            $selectedChambres[] = $chambre;
        }

        $reservation = new Reservation();
        $reservation->setHotel($hotel);
        foreach ($selectedChambres as $chambre) {
            $reservation->addChambre($chambre);
        }
        $reservation->setClient($this->getUser());
        $reservation->setDateDebut($dateDebut);
        $reservation->setDateFin($dateFin);
        $reservation->setNumeroReservation('RES-' . date('YmdHis') . '-' . random_int(100, 999));

        $dm->persist($reservation);
        $dm->flush();

        $this->addFlash('success', 'Réservation effectuée avec succès.');
        return $this->redirectToRoute('hotel_show', ['id' => $hotel->getId()]);
    }

    /**
     * Recherche d'hôtels par nom/ville et filtrage sur une plage de dates.
     * Si des dates sont fournies, on garde seulement les hôtels avec au moins une chambre libre.
     */
    #[Route('/search', name: 'hotel_search', methods: ['GET'])]
    public function search(Request $request, DocumentManager $dm): Response
    {
        $query = trim((string) $request->query->get('q', ''));
        $dateDebut = $request->query->get('date_debut');
        $dateFin = $request->query->get('date_fin');

        $dateDebutObj = $dateDebut ? new \DateTime($dateDebut) : null;
        $dateFinObj = $dateFin ? new \DateTime($dateFin) : null;

        if ($dateDebutObj && $dateFinObj && $dateFinObj <= $dateDebutObj) {
            $this->addFlash('error', 'La date de fin doit être postérieure à la date de début.');
            return $this->redirectToRoute('home');
        }

        $qb = $dm->getRepository(Hotel::class)->createQueryBuilder();
        if ($query !== '') {
            $qb->addOr($qb->expr()->field('nom')->equals(new \MongoDB\BSON\Regex($query, 'i')))
               ->addOr($qb->expr()->field('ville')->equals(new \MongoDB\BSON\Regex($query, 'i')));
        }

        $hotels = iterator_to_array($qb->getQuery()->execute());

        if ($dateDebutObj && $dateFinObj) {
            $hotels = array_filter($hotels, function (Hotel $hotel) use ($dm, $dateDebutObj, $dateFinObj) {
                foreach ($hotel->getChambres() as $chambre) {
                    if ($this->isChambreDisponible($chambre, $dm, $dateDebutObj, $dateFinObj)) {
                        return true;
                    }
                }
                return false;
            });
        }

        $allHotels = $dm->getRepository(Hotel::class)->findAll();
        $noms = array_map(fn($h) => $h->getNom(), $allHotels);

        return $this->render('home/index.html.twig', [
            'hotels' => $hotels,
            'query' => $query,
            'noms' => $noms,
            'currentPage' => 1,
            'totalPages' => 1,
            'dateDebut' => $dateDebut,
            'dateFin' => $dateFin,
        ]);
    }

    /**
     * Petit formulaire de démo listant les noms d'hôtels (utilisé côté front).
     */
    #[Route('/formulaire', name: 'hotel_form', methods: ['GET'])]
    public function form(DocumentManager $dm): Response
    {
        $hotels = $dm->getRepository(Hotel::class)->findAll();
        $noms = array_map(fn($h) => $h->getNom(), $hotels);

        return $this->render('hotel/form.html.twig', [
            'noms' => $noms,
        ]);
    }
    /**
     * Création d'un hôtel par un administrateur.
     * Valide la catégorie et enregistre le document.
     */
    #[Route('/new', name: 'hotel_new', methods: ['GET', 'POST'])]
    public function new(Request $request, DocumentManager $dm): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette page.');
        }
    
        if ($request->isMethod('POST')) {
            $categorie = (int) $request->request->get('categorie');
            if ($categorie < 1 || $categorie > 5) {
                $this->addFlash('error', 'La catégorie doit être comprise entre 1 et 5.');
                return $this->redirectToRoute('hotel_new');
            }
    
            $hotel = new Hotel();
            $hotel->setNom($request->request->get('nom'));
            $hotel->setAdresse($request->request->get('adresse'));
            $hotel->setVille($request->request->get('ville'));
            $hotel->setTelephone($request->request->get('telephone'));
            $hotel->setCategorie($categorie);
    
            $dm->persist($hotel);
            $dm->flush();
    
            return $this->redirectToRoute('home');
        }
    
        return $this->render('hotel/hotel_new.html.twig');
    }

    /**
     * Affiche le détail d'un hôtel.
     * Permet aussi d'ajouter une chambre quand on est admin.
     */
    #[Route('/{id}', name: 'hotel_show', methods: ['GET', 'POST'])]
    public function show(string $id, Request $request, DocumentManager $dm): Response
    {
        $hotel = $dm->getRepository(Hotel::class)->find($id);
    
        if (!$hotel) {
            throw $this->createNotFoundException('Hôtel non trouvé.');
        }
    
        // Si l'utilisateur soumet le formulaire pour ajouter une chambre
        if ($request->isMethod('POST') && $this->isGranted('ROLE_ADMIN')) {
            $chambre = new Chambre();
            $chambre->setNumero($request->request->get('numero'));
            $chambre->setType($request->request->get('type'));
            $chambre->setPrix($request->request->get('prix'));
            $chambre->setHotel($hotel);
    
            $dm->persist($chambre);
            $dm->flush();
    
            return $this->redirectToRoute('hotel_show', ['id' => $hotel->getId()]);
        }
    
        return $this->render('hotel/hotel_show.html.twig', [
            'hotel' => $hotel,
        ]);
    }

    /**
     * Edition d'un hôtel (admin uniquement).
     * Revalide la catégorie avant sauvegarde.
     */
    #[Route('/{id}/edit', name: 'hotel_edit', methods: ['GET', 'POST'])]
    public function edit(string $id, Request $request, DocumentManager $dm): Response
    {
        
        $hotel = $dm->getRepository(Hotel::class)->find($id);
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette page.');
        }
        if (!$hotel) {
            throw $this->createNotFoundException('Hôtel non trouvé.');
        }

        if ($request->isMethod('POST')) {
            $categorie = (int) $request->request->get('categorie');
            if ($categorie < 1 || $categorie > 5) {
                $this->addFlash('error', 'La catégorie doit être comprise entre 1 et 5.');
                return $this->redirectToRoute('hotel_new');
 }
            $hotel->setNom($request->request->get('nom'));
            $hotel->setAdresse($request->request->get('adresse'));
            $hotel->setVille($request->request->get('ville'));
            $hotel->setTelephone($request->request->get('telephone'));
            $hotel->setCategorie($request->request->get('categorie'));

            $dm->flush();

            return $this->redirectToRoute('home');
 }

        return $this->render('hotel/hotel_edit.html.twig', [
            'hotel' => $hotel,
        ]);
    }
    /**
     * Suppression d'un hôtel (admin).
     * Enlève le document puis revient à l'accueil.
     */
    #[Route('/{id}/delete', name: 'hotel_delete', methods: ['POST'])]

public function delete(string $id, DocumentManager $dm): Response
    {
        if (!$this->isGranted('ROLE_ADMIN')) {
            throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette page.');
        }
        $hotel = $dm->getRepository(Hotel::class)->find($id);
if ($hotel) {
            $dm->remove($hotel);
            $dm->flush();



        }

        return $this->redirectToRoute('home');
    }

    /**
     * Vérifie s'il existe déjà une réservation qui chevauche la période demandée.
     */
    private function isChambreDisponible(Chambre $chambre, DocumentManager $dm, \DateTime $dateDebut, \DateTime $dateFin): bool
    {
        $qb = $dm->createQueryBuilder(Reservation::class);
        $qb->field('chambres')->equals($chambre)
           ->field('dateDebut')->lte($dateFin)
           ->field('dateFin')->gte($dateDebut);

        $count = $qb->count()->getQuery()->execute();

        return $count === 0;
    }
}
