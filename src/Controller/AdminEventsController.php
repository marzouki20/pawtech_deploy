<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Repository\EvenementRepository;
use App\Repository\ParticipationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/events')]
class AdminEventsController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('', name: 'app_admin_events', methods: ['GET'])]
    public function index(EvenementRepository $evenementRepository): Response
    {
        return $this->render('events_admin/index.html.twig', [
            'events' => $evenementRepository->findAll(),
            'active' => 'events',
        ]);
    }

    #[Route('/create', name: 'app_admin_events_create', methods: ['POST'])]
    public function create(Request $request): Response
    {
        $evenement = new Evenement();
        $errors = $this->handleFormData($request, $evenement);

        if (count($errors) > 0) {
            foreach ($errors as $error) {
                $this->addFlash('error', $error);
            }
            return $this->redirectToRoute('app_admin_events');
        }

        $this->entityManager->persist($evenement);
        $this->entityManager->flush();

        $this->addFlash('success', 'Événement créé avec succès.');
        return $this->redirectToRoute('app_admin_events');
    }

    #[Route('/{id}/edit', name: 'app_admin_events_edit', methods: ['GET', 'POST'])]
    public function edit(
        Request $request, 
        Evenement $event,
        ParticipationRepository $participationRepository
    ): Response {
        if ($request->isMethod('POST')) {
            $errors = $this->handleFormData($request, $event);

            if (count($errors) > 0) {
                return $this->render('events_admin/edit.html.twig', [
                    'event' => $event,
                    'errors' => $errors,
                    'types' => Evenement::TYPES,
                    'statuts' => Evenement::STATUTS,
                    'participations' => $participationRepository->findBy(['evenement' => $event]),
                    'active' => 'events',
                ]);
            }

            $this->entityManager->flush();
            $this->addFlash('success', 'Événement modifié avec succès.');
            return $this->redirectToRoute('app_admin_events');
        }

        return $this->render('events_admin/edit.html.twig', [
            'event' => $event,
            'errors' => [],
            'types' => Evenement::TYPES,
            'statuts' => Evenement::STATUTS,
            'participations' => $participationRepository->findBy(['evenement' => $event]),
            'active' => 'events',
        ]);
    }

    #[Route('/{id}/cancel', name: 'app_admin_events_cancel', methods: ['POST'])]
    public function cancel(Evenement $event): Response
    {
        $event->setStatut('ANNULE');
        $this->entityManager->flush();

        $this->addFlash('success', 'Événement annulé avec succès.');
        return $this->redirectToRoute('app_admin_events');
    }

    #[Route('/{id}/delete', name: 'app_admin_events_delete', methods: ['POST'])]
    public function delete(Evenement $event): Response
    {
        $this->entityManager->remove($event);
        $this->entityManager->flush();

        $this->addFlash('success', 'Événement supprimé définitivement.');
        return $this->redirectToRoute('app_admin_events');
    }

    /**
     * Handle form data and validate the entity
     * @return array<string> Array of error messages
     */
    private function handleFormData(Request $request, Evenement $evenement): array
    {
        $errors = [];

        // Get form data
        $titre = trim($request->request->get('titre', ''));
        $type = $request->request->get('type', '');
        $statut = $request->request->get('statut', 'PLANIFIE');
        $dateDebutStr = $request->request->get('dateDebut', '');
        $dateFinStr = $request->request->get('dateFin', '');
        $lieu = trim($request->request->get('lieu', ''));
        $ville = trim($request->request->get('ville', ''));
        $description = trim($request->request->get('description', ''));
        $capaciteMax = $request->request->get('capaciteMax', '');

        // Manual PHP validation
        
        // Title validation
        if (empty($titre)) {
            $errors['titre'] = 'Le titre est obligatoire.';
        } elseif (strlen($titre) < 3) {
            $errors['titre'] = 'Le titre doit contenir au moins 3 caractères.';
        } elseif (strlen($titre) > 255) {
            $errors['titre'] = 'Le titre ne peut pas dépasser 255 caractères.';
        }

        // Type validation
        if (empty($type)) {
            $errors['type'] = 'Le type est obligatoire.';
        } elseif (!in_array($type, Evenement::TYPES)) {
            $errors['type'] = 'Type invalide.';
        }

        // Status validation
        if (empty($statut)) {
            $errors['statut'] = 'Le statut est obligatoire.';
        } elseif (!in_array($statut, Evenement::STATUTS)) {
            $errors['statut'] = 'Statut invalide.';
        }

        // Start date validation
        $dateDebut = null;
        if (empty($dateDebutStr)) {
            $errors['dateDebut'] = 'La date de début est obligatoire.';
        } else {
            try {
                $dateDebut = new \DateTime($dateDebutStr);
            } catch (\Exception $e) {
                $errors['dateDebut'] = 'Format de date invalide.';
            }
        }

        // End date validation
        $dateFin = null;
        if (!empty($dateFinStr)) {
            try {
                $dateFin = new \DateTime($dateFinStr);
                if ($dateDebut && $dateFin < $dateDebut) {
                    $errors['dateFin'] = 'La date de fin doit être après la date de début.';
                }
            } catch (\Exception $e) {
                $errors['dateFin'] = 'Format de date invalide.';
            }
        }

        // Location validation
        if (empty($lieu)) {
            $errors['lieu'] = 'Le lieu est obligatoire.';
        } elseif (strlen($lieu) > 255) {
            $errors['lieu'] = 'Le lieu ne peut pas dépasser 255 caractères.';
        }

        // City validation
        if (empty($ville)) {
            $errors['ville'] = 'La ville est obligatoire.';
        } elseif (strlen($ville) > 100) {
            $errors['ville'] = 'La ville ne peut pas dépasser 100 caractères.';
        }

        // Capacity validation
        $capaciteMaxInt = null;
        if (!empty($capaciteMax)) {
            if (!is_numeric($capaciteMax) || (int)$capaciteMax < 1) {
                $errors['capaciteMax'] = 'La capacité doit être un nombre positif.';
            } else {
                $capaciteMaxInt = (int)$capaciteMax;
            }
        }

        // If no errors, populate the entity
        if (empty($errors)) {
            $evenement->setTitre($titre);
            $evenement->setType($type);
            $evenement->setStatut($statut);
            $evenement->setDateDebut($dateDebut);
            $evenement->setDateFin($dateFin);
            $evenement->setLieu($lieu);
            $evenement->setVille($ville);
            $evenement->setDescription($description ?: null);
            $evenement->setCapaciteMax($capaciteMaxInt);
        }

        return $errors;
    }
}
