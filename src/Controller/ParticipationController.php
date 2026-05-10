<?php

namespace App\Controller;

use App\Entity\Participation;
use App\Form\ParticipationType;
use App\Repository\ParticipationRepository;
use App\Service\EmailService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/participation')]
final class ParticipationController extends AbstractController
{
    #[Route(name: 'app_participation_index', methods: ['GET'])]
    public function index(Request $request, ParticipationRepository $participationRepository): Response
    {
        $q = $request->query->get('q', '');
        $statut = $request->query->get('statut', '');
        $sort = $request->query->get('sort', 'dateParticipation_desc');

        // Use repository method for search/filter/sort
        $participations = $participationRepository->findWithAdminFilters(
            $q ?: null,
            $statut ?: null,
            $sort
        );

        return $this->render('participation/index.html.twig', [
            'participations' => $participations,
            'active' => 'participation',
            'q' => $q,
            'statut' => $statut,
            'sort' => $sort,
        ]);
    }

    #[Route('/filter', name: 'app_participation_filter', methods: ['GET'])]
    public function filter(Request $request, ParticipationRepository $participationRepository): JsonResponse
    {
        $q = $request->query->get('q', '');
        $statut = $request->query->get('statut', '');
        $sort = $request->query->get('sort', 'dateParticipation_desc');

        $participations = $participationRepository->findWithAdminFilters(
            $q ?: null,
            $statut ?: null,
            $sort
        );

        $data = [];
        foreach ($participations as $p) {
            $data[] = [
                'id' => $p->getId(),
                'userFullName' => $p->getUser()->getFullName(),
                'userEmail' => $p->getUser()->getEmail(),
                'evenementId' => $p->getEvenement()->getId(),
                'evenementTitre' => $p->getEvenement()->getTitre(),
                'evenementVille' => $p->getEvenement()->getVille(),
                'dateParticipation' => $p->getDateParticipation()->format('d/m/Y'),
                'statut' => $p->getStatut(),
                'showUrl' => $this->generateUrl('app_participation_show', ['id' => $p->getId()]),
                'editUrl' => $this->generateUrl('app_participation_edit', ['id' => $p->getId()]),
                'evenementUrl' => $this->generateUrl('app_evenement_show', ['id' => $p->getEvenement()->getId()]),
                'confirmUrl' => $this->generateUrl('app_participation_confirm', ['id' => $p->getId()]),
                'cancelUrl' => $this->generateUrl('app_participation_cancel', ['id' => $p->getId()]),
            ];
        }

        return new JsonResponse(['ok' => true, 'count' => count($data), 'items' => $data]);
    }

    #[Route('/new', name: 'app_participation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $participation = new Participation();
        $form = $this->createForm(ParticipationType::class, $participation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($participation);
            $entityManager->flush();

            $this->addFlash('success', 'Participation créée avec succès.');
            return $this->redirectToRoute('app_participation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('participation/new.html.twig', [
            'participation' => $participation,
            'form' => $form,
            'active' => 'participation',
        ]);
    }

    #[Route('/{id}', name: 'app_participation_show', methods: ['GET'])]
    public function show(Participation $participation): Response
    {
        return $this->render('participation/show.html.twig', [
            'participation' => $participation,
            'active' => 'participation',
        ]);
    }

    #[Route('/{id}/edit', name: 'app_participation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Participation $participation, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(ParticipationType::class, $participation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Participation modifiée avec succès.');
            return $this->redirectToRoute('app_participation_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('participation/edit.html.twig', [
            'participation' => $participation,
            'form' => $form,
            'active' => 'participation',
        ]);
    }

    #[Route('/{id}', name: 'app_participation_delete', methods: ['POST'])]
    public function delete(Request $request, Participation $participation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$participation->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($participation);
            $entityManager->flush();
            $this->addFlash('success', 'Participation supprimée avec succès.');
        }

        return $this->redirectToRoute('app_participation_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/confirm', name: 'app_participation_confirm', methods: ['POST'])]
    public function confirm(Request $request, Participation $participation, EntityManagerInterface $entityManager, EmailService $emailService): Response
    {
        if ($this->isCsrfTokenValid('confirm'.$participation->getId(), $request->getPayload()->getString('_token'))) {
            $participation->confirm();
            $entityManager->flush();

            // Send confirmation email to participant
            $emailSent = $emailService->sendParticipationConfirmation($participation);
            
            if ($emailSent) {
                $this->addFlash('success', 'Participation confirmée et email de confirmation envoyé.');
            } else {
                $this->addFlash('warning', 'Participation confirmée mais l\'email n\'a pas pu être envoyé.');
            }
        }

        return $this->redirectToRoute('app_participation_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/cancel', name: 'app_participation_cancel', methods: ['POST'])]
    public function cancel(Request $request, Participation $participation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('cancel'.$participation->getId(), $request->getPayload()->getString('_token'))) {
            $participation->cancel();
            $entityManager->flush();
            $this->addFlash('success', 'Participation annulée.');
        }

        return $this->redirectToRoute('app_participation_index', [], Response::HTTP_SEE_OTHER);
    }
}