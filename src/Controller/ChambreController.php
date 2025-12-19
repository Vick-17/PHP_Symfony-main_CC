<?php
namespace App\Controller;

use App\Document\Chambre;
use App\Document\Hotel;
use Doctrine\ODM\MongoDB\DocumentManager;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use App\Document\Comment;
use App\Document\Reservation;

/**
 * Contrôleur de gestion des chambres d'hôtel.
 * Permet d'ajouter, modifier, afficher et supprimer des chambres.
 */
#[Route('/chambre')]
class ChambreController extends AbstractController
{
    /**
     * Ajoute une nouvelle chambre à un hôtel donné.
     * Vérifie l'existence de l'hôtel et l'unicité du numéro de chambre pour cet hôtel.
     * Enregistre ensuite la chambre et redirige vers la fiche de l'hôtel.
     *
     * @param string $hotelId L'identifiant de l'hôtel
     * @param Request $request La requête HTTP
     * @param DocumentManager $dm Le gestionnaire de documents MongoDB
     * @return Response
     */
    #[Route('/{hotelId}/new', name: 'chambre_new', methods: ['GET', 'POST'])]
    public function addChambre(string $hotelId, Request $request, DocumentManager $dm): Response
    {
        try {
            if (!$this->isGranted('ROLE_ADMIN')) {
                throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette page.');
            }
            // Recherche de l'hôtel par son identifiant
            $hotel = $dm->getRepository(Hotel::class)->find($hotelId);

            if (!$hotel) {
                // Si l'hôtel n'existe pas, on lève une exception 404
                throw $this->createNotFoundException('Hôtel non trouvé.');
            }

            if ($request->isMethod('POST')) {
                $numero = $request->request->get('numero');

                // Vérifie que le numéro de chambre est renseigné
                if (empty($numero)) {
                    $this->addFlash('error', 'Le numéro de chambre est obligatoire.');
                    return $this->render('chambre/chambre_new.html.twig', [
                        'hotel' => $hotel,
                    ]);
                }

                // Vérifie l'unicité du numéro de chambre pour cet hôtel
                $existingChambre = $dm->getRepository(Chambre::class)->findOneBy([
                    'hotel' => $hotel,
                    'numero' => $numero,
                ]);

                if ($existingChambre) {
                    $this->addFlash('error', 'Une chambre avec ce numéro existe déjà pour cet hôtel.');
                    return $this->render('chambre/chambre_new.html.twig', [
                        'hotel' => $hotel,
                    ]);
                }

                // Création et sauvegarde de la nouvelle chambre
                $chambre = new Chambre();
                $chambre->setNumero($numero);
                $chambre->setType($request->request->get('type'));
                $chambre->setPrix($request->request->get('prix'));
                $chambre->setCapacite((int) $request->request->get('capacite'));
                $chambre->setHotel($hotel);

                $dm->persist($chambre);
                $dm->flush();

                // Redirection vers la page de l'hôtel après ajout
                return $this->redirectToRoute('hotel_show', ['id' => $hotel->getId()]);
            }

            // Affiche le formulaire d'ajout si ce n'est pas un POST
            return $this->render('chambre/chambre_new.html.twig', [
                'hotel' => $hotel,
            ]);
        } catch (\Exception $e) {
            // Gestion des erreurs inattendues
            $this->addFlash('error', 'Une erreur est survenue : ' . $e->getMessage());
            return $this->redirectToRoute('home');
        }
    }

    /**
     * Modifie une chambre existante.
     * Vérifie l'unicité du numéro de chambre pour l'hôtel lors de la modification.
     * Met à jour le type, le prix et la capacité avant de sauvegarder.
     *
     * @param string $id L'identifiant de la chambre à modifier
     * @param Request $request La requête HTTP
     * @param DocumentManager $dm Le gestionnaire de documents MongoDB
     * @return Response
     */
    #[Route('/{id}/edit', name: 'chambre_edit', methods: ['GET', 'POST'])]
    public function editChambre(string $id, Request $request, DocumentManager $dm): Response
    {
        try {
            if (!$this->isGranted('ROLE_ADMIN')) {
                throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette page.');
            }
            // Recherche de la chambre à modifier
            $chambre = $dm->getRepository(Chambre::class)->find($id);

            if (!$chambre) {
                throw $this->createNotFoundException('Chambre non trouvée.');
            }

            if ($request->isMethod('POST')) {
                $numero = $request->request->get('numero');
                $hotel = $chambre->getHotel();

                // Vérifie l'unicité du numéro de chambre (hors la chambre courante)
                $existingChambre = $dm->getRepository(Chambre::class)->findOneBy([
                    'hotel' => $hotel,
                    'numero' => $numero,
                ]);

                if ($existingChambre && $existingChambre->getId() !== $chambre->getId()) {
                    $this->addFlash('error', 'Une chambre avec ce numéro existe déjà pour cet hôtel.');
                    return $this->redirectToRoute('chambre_edit', ['id' => $chambre->getId()]);
                }

                // Mise à jour des informations de la chambre
                $chambre->setNumero($numero);
                $chambre->setType($request->request->get('type'));
                $chambre->setPrix($request->request->get('prix'));
                $chambre->setCapacite((int) $request->request->get('capacite'));

                $dm->flush();

                // Redirection vers la page de l'hôtel après modification
                return $this->redirectToRoute('hotel_show', ['id' => $hotel->getId()]);
            }

            // Affiche le formulaire de modification
            return $this->render('chambre/chambre_edit.html.twig', [
                'chambre' => $chambre,
            ]);
        } catch (\Exception $e) {
            // Gestion des erreurs inattendues
            $this->addFlash('error', 'Une erreur est survenue : ' . $e->getMessage());
            return $this->redirectToRoute('home');
        }
    }

    /**
     * Affiche les commentaires d'une chambre.
     * Charge d'abord les réservations qui utilisent la chambre, puis les avis liés.
     *
     * @param string $id L'identifiant de la chambre
     * @param DocumentManager $dm Le gestionnaire de documents MongoDB
     * @return Response
     */
    #[Route('/chambre/{id}/comments', name: 'chambre_comments', methods: ['GET'])]
    public function comments(string $id, DocumentManager $dm): Response
    {
        try {
            // Recherche de la chambre
            $chambre = $dm->getRepository(Chambre::class)->find($id);

            if (!$chambre) {
                throw $this->createNotFoundException('Chambre non trouvée.');
            }

            $reservations = $dm->createQueryBuilder(Reservation::class)
                ->field('chambres')->equals($chambre)
                ->getQuery()
                ->execute();

            $comments = $dm->createQueryBuilder(Comment::class)
                ->field('reservation')->in(iterator_to_array($reservations))
                ->getQuery()
                ->execute();

            // Affiche la page des commentaires
            return $this->render('chambre/chambre_comments.html.twig', [
                'chambre' => $chambre,
                'comments' => $comments,
            ]);
        } catch (\Exception $e) {
            // Gestion des erreurs inattendues
            $this->addFlash('error', 'Une erreur est survenue : ' . $e->getMessage());
            return $this->redirectToRoute('home');
        }
    }

    /**
     * Supprime une chambre.
     * Après suppression, redirige vers la fiche de l'hôtel parent.
     *
     * @param string $id L'identifiant de la chambre à supprimer
     * @param DocumentManager $dm Le gestionnaire de documents MongoDB
     * @return Response
     */
    #[Route('/{id}/delete', name: 'chambre_delete', methods: ['POST'])]
    public function delete(string $id, DocumentManager $dm): Response
    {
        try {
            if (!$this->isGranted('ROLE_ADMIN')) {
                throw $this->createAccessDeniedException('Vous n\'avez pas accès à cette page.');
            }
            // Recherche de la chambre à supprimer
            $chambre = $dm->getRepository(Chambre::class)->find($id);

            if ($chambre) {
                // Récupère l'identifiant de l'hôtel pour la redirection
                $hotelId = $chambre->getHotel()->getId();
                $dm->remove($chambre);
                $dm->flush();
                return $this->redirectToRoute('hotel_show', ['id' => $hotelId]);
            } else {
                throw $this->createNotFoundException('Chambre non trouvée.');
            }
        } catch (\Exception $e) {
            // Gestion des erreurs inattendues
            $this->addFlash('error', 'Une erreur est survenue : ' . $e->getMessage());
            return $this->redirectToRoute('home');
        }
    }
}
