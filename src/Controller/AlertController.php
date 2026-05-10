<?php

namespace App\Controller;

use App\Entity\Alert;
use App\Form\AlertType;
use App\Repository\AlertRepository;
use App\Repository\ObservationStationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/alerts')]
final class AlertController extends AbstractController
{
    #[Route('', name: 'app_admin_alerts', methods: ['GET'])]
    public function index(AlertRepository $alertRepository, ObservationStationRepository $stationRepository): Response
    {
        return $this->render('alert/index.html.twig', [
            'alerts' => $alertRepository->findBy([], ['date' => 'DESC']),
            'inactive_stations' => $stationRepository->findBy(['statut' => 'inactive']),
            'active' => 'alerts',
            'page_title' => 'Alerts',
        ]);
    }

    #[Route('/search', name: 'app_admin_alerts_search', methods: ['GET'])]
    public function search(Request $request, AlertRepository $alertRepository): Response
    {
        $searchValue = $request->query->get('searchValue', '');
        
        $queryBuilder = $alertRepository->createQueryBuilder('a');
        
        if (!empty($searchValue)) {
            $queryBuilder->andWhere('a.message LIKE :search OR a.type LIKE :search')
                         ->setParameter('search', '%' . $searchValue . '%');
        }
        
        $alerts = $queryBuilder->getQuery()->getResult();
        
        $result = [];
        foreach ($alerts as $alert) {
            $result[] = [
                'id' => $alert->getId(),
                'type' => $alert->getType(),
                'message' => $alert->getMessage(),
                'prioritee' => $alert->getPrioritee(),
                'statut' => $alert->getStatut(),
                'station_id' => $alert->getStation() ? $alert->getStation()->getId() : null,
                'date' => $alert->getDate() ? $alert->getDate()->format('d/m/Y H:i') : '',
            ];
        }
        
        return $this->json($result);
    }

    #[Route('/new', name: 'app_admin_alerts_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $alert = new Alert();
        if (!$alert->getType()) {
            $alert->setType('TECHNICAL');
        }
        if (!$alert->getDate()) {
            $alert->setDate(new \DateTime());
        }
        $form = $this->createForm(AlertType::class, $alert);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($alert);
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_alerts', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('alert/new.html.twig', [
            'alert' => $alert,
            'form' => $form,
            'active' => 'alerts',
            'page_title' => 'New Alert',
        ]);
    }

    #[Route('/{id}', name: 'app_admin_alerts_show', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function show(Alert $alert): Response
    {
        return $this->render('alert/show.html.twig', [
            'alert' => $alert,
            'active' => 'alerts',
            'page_title' => 'Alert Details',
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_alerts_edit', methods: ['GET', 'POST'], requirements: ['id' => '\\d+'])]
    public function edit(Request $request, Alert $alert, EntityManagerInterface $entityManager): Response
    {
        if (!$alert->getType()) {
            $alert->setType('TECHNICAL');
        }
        $form = $this->createForm(AlertType::class, $alert);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_alerts', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('alert/edit.html.twig', [
            'alert' => $alert,
            'form' => $form,
            'active' => 'alerts',
            'page_title' => 'Edit Alert',
        ]);
    }

    #[Route('/{id}', name: 'app_admin_alerts_delete', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function delete(Request $request, Alert $alert, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$alert->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($alert);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_admin_alerts', [], Response::HTTP_SEE_OTHER);
    }
}
