<?php

namespace App\Controller;

use App\Entity\Dogs;
use App\Form\DogsType;
use App\Repository\DogsRepository;
use App\Repository\ZiptagRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dogs')]
final class DogsController extends AbstractController
{
    #[Route('/search_json', name: 'app_dogs_search_json', methods: ['GET'])]
    public function searchJson(Request $request, NormalizerInterface $normalizer, DogsRepository $dogsRepository): JsonResponse
    {
        $searchValue = $request->get('searchValue');
        $status = $request->get('status');
        if ($status) {
            $dogs = $dogsRepository->filterDogs($status, null, null);
            if ($searchValue) {
                $dogs = array_filter($dogs, function($dog) use ($searchValue) {
                    return stripos($dog->getName(), $searchValue) !== false ||
                           stripos($dog->getBreed(), $searchValue) !== false;
                });
            }
        } else {
            $dogs = $dogsRepository->searchDogs($searchValue);
        }
        $jsonContent = $normalizer->normalize($dogs, 'json', ['groups' => 'dogs']);
        return new JsonResponse($jsonContent);
    }

    #[Route('/filter', name: 'app_dogs_filter', methods: ['GET'])]
    #[Route(name: 'app_dogs_index', methods: ['GET'])]
    public function index(Request $request, DogsRepository $dogsRepository): Response
    {
        $adoptionStatus = $request->query->get('adoptionStatus');
        $healthStatus = $request->query->get('healthStatus');
        $gender = $request->query->get('gender');

        if ($adoptionStatus || $healthStatus || $gender) {
            $dogs = $dogsRepository->filterDogs($adoptionStatus, $healthStatus, $gender);
        } else {
            $dogs = $dogsRepository->findAll();
        }

        return $this->render('dogs/index.html.twig', [
            'dogs' => $dogs,
        ]);
    }

    #[Route('/new', name: 'app_dogs_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, ZiptagRepository $ziptagRepository): Response
    {
        $dog = new Dogs();
        $ziptagChoices = $ziptagRepository->findUnassignedOrCurrent();
        $form = $this->createForm(DogsType::class, $dog, [
            'ziptag_choices' => $ziptagChoices,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $ziptag = $form->get('ziptag')->getData();
            if (!$ziptag) {
                $dog->setZiptag(null);
            }

            if ($form->isValid()) {
                $entityManager->persist($dog);
                $entityManager->flush();

                $this->addFlash('success', 'Dog created successfully.');

                return $this->redirectToRoute('app_dogs_index', [], Response::HTTP_SEE_OTHER);
            }
        }

        return $this->render('dogs/new.html.twig', [
            'dog' => $dog,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_dogs_show', methods: ['GET'])]
    public function show(Dogs $dog, \App\Repository\UserRepository $userRepository): Response
    {
        $adopter = null;
        if ($dog->getUser()) {
            $adopter = $userRepository->findById($dog->getUser()->getId());
        }
        return $this->render('dogs/show.html.twig', [
            'dog' => $dog,
            'adopter' => $adopter,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_dogs_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Dogs $dog, EntityManagerInterface $entityManager, ZiptagRepository $ziptagRepository): Response
    {
        // Store the current ziptag before form handling
        $previousZiptag = $dog->getZiptag();
        
        $currentZiptagId = $dog->getZiptag() ? $dog->getZiptag()->getId() : null;
        $ziptagChoices = $ziptagRepository->findUnassignedOrCurrent($currentZiptagId);
        $form = $this->createForm(DogsType::class, $dog, [
            'ziptag_choices' => $ziptagChoices,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $newZiptag = $form->get('ziptag')->getData();
            $newStatus = $form->get('adoption_status')->getData();

            // If status is Available, Reserved, or Adopted, remove ziptag
            if (in_array($newStatus, ['Available', 'Reserved', 'Adopted'])) {
                if ($dog->getZiptag()) {
                    $dog->getZiptag()->setDog(null);
                }
                $dog->setZiptag(null);
            } else {
                // If ziptag changed or removed
                if ($previousZiptag !== $newZiptag) {
                    if ($previousZiptag) {
                        $previousZiptag->setDog(null);
                    }
                    $dog->setZiptag($newZiptag);
                    if ($newZiptag) {
                        $newZiptag->setDog($dog);
                    }
                }
            }

            $entityManager->flush();

            $this->addFlash('success', 'Dog updated successfully.');

            return $this->redirectToRoute('app_dogs_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('dogs/edit.html.twig', [
            'dog' => $dog,
            'form' => $form,
        ]);
    }

    #[Route('/{id}', name: 'app_dogs_delete', methods: ['POST'])]
    public function delete(Request $request, Dogs $dog, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$dog->getId(), $request->getPayload()->getString('_token'))) {
            $ziptag = $dog->getZiptag();
            if ($ziptag) {
                $ziptag->setDog(null);
                $dog->setZiptag(null);
            }
            $entityManager->remove($dog);
            $entityManager->flush();

            $this->addFlash('success', 'Dog deleted successfully.');
        }

        return $this->redirectToRoute('app_dogs_index', [], Response::HTTP_SEE_OTHER);
    }


}