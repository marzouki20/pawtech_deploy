<?php

namespace App\Controller;

use App\Entity\Guest;
use App\Form\GuestType;
use App\Repository\GuestRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/guest')]
final class GuestController extends AbstractController
{
    #[Route(name: 'app_guest_index', methods: ['GET'])]
    public function index(Request $request, GuestRepository $guestRepository): Response
    {
        $q = $request->query->get('q', '');
        $role = $request->query->get('role', '');
        $sort = $request->query->get('sort', 'nom_asc');

        // Use repository method for search/filter/sort
        $guests = $guestRepository->findWithAdminFilters(
            $q ?: null,
            $role ?: null,
            $sort
        );

        return $this->render('guest/index.html.twig', [
            'guests' => $guests,
            'active' => 'guest',
            'q' => $q,
            'role' => $role,
            'sort' => $sort,
        ]);
    }

    #[Route('/filter', name: 'app_guest_filter', methods: ['GET'])]
    public function filter(Request $request, GuestRepository $guestRepository): JsonResponse
    {
        $q = $request->query->get('q', '');
        $role = $request->query->get('role', '');
        $sort = $request->query->get('sort', 'nom_asc');

        $guests = $guestRepository->findWithAdminFilters(
            $q ?: null,
            $role ?: null,
            $sort
        );

        $data = [];
        foreach ($guests as $g) {
            $data[] = [
                'id' => $g->getId(),
                'fullName' => $g->getFullName(),
                'email' => $g->getEmail(),
                'organisation' => $g->getOrganisation(),
                'role' => $g->getRole(),
                'evenementId' => $g->getEvenement()->getId(),
                'evenementTitre' => $g->getEvenement()->getTitre(),
                'showUrl' => $this->generateUrl('app_guest_show', ['id' => $g->getId()]),
                'editUrl' => $this->generateUrl('app_guest_edit', ['id' => $g->getId()]),
                'evenementUrl' => $this->generateUrl('app_evenement_show', ['id' => $g->getEvenement()->getId()]),
            ];
        }

        return new JsonResponse(['ok' => true, 'count' => count($data), 'items' => $data]);
    }

    #[Route('/new', name: 'app_guest_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $guest = new Guest();
        $form = $this->createForm(GuestType::class, $guest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($guest);
            $entityManager->flush();

            $this->addFlash('success', 'Invité créé avec succès.');
            return $this->redirectToRoute('app_guest_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('guest/new.html.twig', [
            'guest' => $guest,
            'form' => $form,
            'active' => 'guest',
        ]);
    }

    #[Route('/{id}', name: 'app_guest_show', methods: ['GET'])]
    public function show(Guest $guest): Response
    {
        return $this->render('guest/show.html.twig', [
            'guest' => $guest,
            'active' => 'guest',
        ]);
    }

    #[Route('/{id}/edit', name: 'app_guest_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Guest $guest, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(GuestType::class, $guest);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Invité modifié avec succès.');
            return $this->redirectToRoute('app_guest_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('guest/edit.html.twig', [
            'guest' => $guest,
            'form' => $form,
            'active' => 'guest',
        ]);
    }

    #[Route('/{id}', name: 'app_guest_delete', methods: ['POST'])]
    public function delete(Request $request, Guest $guest, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$guest->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($guest);
            $entityManager->flush();
            $this->addFlash('success', 'Invité supprimé avec succès.');
        }

        return $this->redirectToRoute('app_guest_index', [], Response::HTTP_SEE_OTHER);
    }
}
