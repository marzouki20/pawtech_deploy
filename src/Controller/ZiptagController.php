<?php

namespace App\Controller;

use App\Entity\Ziptag;
use App\Form\ZiptagType;
use App\Repository\ZiptagRepository;
use App\Repository\DogsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/ziptag')]
final class ZiptagController extends AbstractController
{
#[Route('/search_json', name: 'app_ziptag_search_json', methods: ['GET'])]
public function searchJson(Request $request, NormalizerInterface $normalizer, ZiptagRepository $ziptagRepository): JsonResponse
{

    $searchValue = $request->query->get('searchValue', '');
    
    $ziptags = $ziptagRepository->searchZiptags($searchValue);
    
    $data = $normalizer->normalize($ziptags, 'json', ['groups' => 'ziptags']);
    return new JsonResponse($data);
}


    #[Route(name: 'app_ziptag_index', methods: ['GET'])]
 public function index(Request $request, ZiptagRepository $ziptagRepository): Response
{
    // Get filter parameters
    $firmwareFilter = $request->query->get('firmware');
    $assignmentFilter = $request->query->get('assignment');
    
    // Convert assignment filter to boolean for repository
    $hasDog = null;
    if ($assignmentFilter === 'assigned') {
        $hasDog = true;
    } elseif ($assignmentFilter === 'unassigned') {
        $hasDog = false;
    }
    
    // Get unique firmware versions for dropdown
    $firmwareVersions = $ziptagRepository->findDistinctFirmwareVersions();
    
    // Get filtered results or all
    if ($firmwareFilter || $hasDog !== null) {
        $ziptags = $ziptagRepository->findByFilters($firmwareFilter, $hasDog);
    } else {
        $ziptags = $ziptagRepository->findAll();
    }
    
    return $this->render('ziptag/index.html.twig', [
        'ziptags' => $ziptags,
        'firmware_versions' => $firmwareVersions,
        'current_firmware' => $firmwareFilter,
        'current_assignment' => $assignmentFilter,
    ]);
}

    
    ////////////////////////////////newwww///////////////////////////////////////////////////
    #[Route('/new', name: 'app_ziptag_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, DogsRepository $dogsRepository): Response
    {
        $ziptag = new Ziptag();
        $dogChoices = $dogsRepository->findUnassignedOrCurrent();
        $form = $this->createForm(ZiptagType::class, $ziptag, [
            'dog_choices' => $dogChoices,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($ziptag);
            $entityManager->flush();

            $this->addFlash('success', 'Ziptag created successfully.');

            return $this->redirectToRoute('app_ziptag_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('ziptag/new.html.twig', [
            'ziptag' => $ziptag,
            'form' => $form,
        ]);
    }


    /////////////////////////////show///////////////////////////////////////////////////

    #[Route('/{id}', name: 'app_ziptag_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Ziptag $ziptag): Response
    {
        return $this->render('ziptag/show.html.twig', [
            'ziptag' => $ziptag,
        ]);
    }
////////////////////////////////edit/////////////////////////////////////////////////////
    #[Route('/{id}/edit', name: 'app_ziptag_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Ziptag $ziptag, EntityManagerInterface $entityManager, DogsRepository $dogsRepository): Response
    {
        $currentDogId = $ziptag->getDog() ? $ziptag->getDog()->getId() : null;
        $dogChoices = $dogsRepository->findUnassignedOrCurrent($currentDogId);
        $form = $this->createForm(ZiptagType::class, $ziptag, [
            'dog_choices' => $dogChoices,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            $this->addFlash('success', 'Ziptag updated successfully.');

            return $this->redirectToRoute('app_ziptag_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('ziptag/edit.html.twig', [
            'ziptag' => $ziptag,
            'form' => $form,
        ]);
    }
////////////////////////////////////delete//////////////////////////////////////////////////////
    #[Route('/{id}', name: 'app_ziptag_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Ziptag $ziptag, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$ziptag->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($ziptag);
            $entityManager->flush();

            $this->addFlash('success', 'Ziptag deleted successfully.');
        }

        return $this->redirectToRoute('app_ziptag_index', [], Response::HTTP_SEE_OTHER);
    }


}
