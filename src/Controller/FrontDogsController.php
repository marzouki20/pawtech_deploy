<?php

namespace App\Controller;

use App\Entity\Adoption;
use App\Entity\User;
use App\Entity\Dogs;
use App\Form\AdoptionType;
use App\Repository\DogsRepository;
use App\Repository\AdoptionRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class FrontDogsController extends AbstractController
{
    #[Route('/front_dogs/dogs', name: 'app_front_dogs', methods: ['GET'])]
    #[Route('/dogs', name: 'app_dogs', methods: ['GET'])]
    public function index(
        Request $request,
        DogsRepository $dogsRepository,
        AdoptionRepository $adoptionRepository
    ): Response
    {
        $session = $request->getSession();
        $showRecommendationModal = !$session->get('front_dogs_recommendation_modal_seen', false);
        if ($showRecommendationModal) {
            $session->set('front_dogs_recommendation_modal_seen', true);
        }

        $selectedStatus = $request->query->get('status');
        if ($selectedStatus !== 'Available') {
            $selectedStatus = null;
        }

        $dogs = $dogsRepository->findBy(['adoption_status' => 'Available'], ['name' => 'ASC']);

        $reservedDogs = $dogsRepository->findBy(['adoption_status' => 'Reserved'], ['arrival_date' => 'DESC']);
        $sessionUser = $session->get('user');
        $sessionUserId = $sessionUser instanceof User
            ? $sessionUser->getId()
            : (is_array($sessionUser) ? (int) ($sessionUser['id'] ?? 0) : 0);

        if ($sessionUserId > 0 && $reservedDogs !== []) {
            $requestedDogRows = $adoptionRepository
                ->createQueryBuilder('a')
                ->select('IDENTITY(a.dog) AS dog_id')
                ->andWhere('IDENTITY(a.user) = :userId')
                ->setParameter('userId', $sessionUserId)
                ->getQuery()
                ->getArrayResult();

            $requestedDogIds = array_values(array_unique(array_map(
                static fn (array $row): int => (int) ($row['dog_id'] ?? 0),
                $requestedDogRows
            )));

            if ($requestedDogIds !== []) {
                $reservedDogs = array_values(array_filter(
                    $reservedDogs,
                    static fn (Dogs $dog): bool => !in_array((int) $dog->getId(), $requestedDogIds, true)
                ));
            }
        }

        return $this->render('front_dogs/dogs.html.twig', [
            'dogs' => $dogs,
            'reservedDogs' => $reservedDogs,
            'selectedStatus' => $selectedStatus,
            'sessionUser' => $sessionUser,
            'showRecommendationModal' => $showRecommendationModal,
        ]);
    }

    #[Route('/front_dogs/recommended_dog', name: 'app_recommended_dog', methods: ['POST'])]
    #[Route('/front_dogs/dog_recommendation', name: 'app_dog_recommendation', methods: ['POST'])]
    public function recommendedDog(
        Request $request,
        DogsRepository $dogsRepository,
        HttpClientInterface $httpClient
    ): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Invalid JSON payload.',
            ], Response::HTTP_BAD_REQUEST);
        }

        $userProfile = $payload['user_profile'] ?? [];
        $filters = $payload['filters'] ?? [];

        if (!is_array($userProfile) || !is_array($filters)) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'user_profile and filters must be objects.',
            ], Response::HTTP_BAD_REQUEST);
        }

        unset($payload['top_n']);

        try {
            $apiResponse = $httpClient->request('POST', 'http://127.0.0.1:8002/recommend', [
                'json' => [
                    'user_profile' => $userProfile,
                    'filters' => $filters,
                ],
                'timeout' => 10,
            ]);

            $statusCode = $apiResponse->getStatusCode();
            $apiData = $apiResponse->toArray(false);

            if ($statusCode >= 400) {
                return new JsonResponse([
                    'ok' => false,
                    'message' => 'Recommendation API returned an error.',
                    'api_response' => $apiData,
                ], $statusCode);
            }

            $recommendedIds = array_values(array_filter(
                $apiData['recommendations'] ?? [],
                static fn ($id) => is_int($id) || ctype_digit((string) $id)
            ));

            $dogsById = [];
            if (!empty($recommendedIds)) {
                $dogs = $dogsRepository->findBy(['id' => $recommendedIds]);
                foreach ($dogs as $dog) {
                    $dogsById[$dog->getId()] = [
                        'id' => $dog->getId(),
                        'name' => $dog->getName(),
                        'breed' => $dog->getBreed(),
                        'age' => $dog->getAge(),
                        'gender' => $dog->getGender(),
                        'image' => $dog->getImage(),
                        'adoption_status' => $dog->getAdoptionStatus(),
                    ];
                }
            }

            $orderedDogs = [];
            foreach ($recommendedIds as $id) {
                $id = (int) $id;
                if (isset($dogsById[$id])) {
                    $orderedDogs[] = $dogsById[$id];
                }
            }

            return new JsonResponse([
                'ok' => true,
                'api_response' => $apiData,
                'recommendations' => $recommendedIds,
                'dogs' => $orderedDogs,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Failed to call recommendation API.',
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }
    }

    #[Route('/front_dogs/dogs/{id}', name: 'app_front_dogs_show', methods: ['GET'])]
    public function show(Dogs $dog, Request $request): Response
    {
        return $this->render('front_dogs/show.html.twig', [
            'dog' => $dog,
            'sessionUser' => $request->getSession()->get('user'),
        ]);
    }

    #[Route('/front_dogs/dogs/{id}/generate-description', name: 'app_front_dogs_generate_description', methods: ['POST'])]
    public function generateDescription(Dogs $dog, HttpClientInterface $httpClient): JsonResponse
    {
        $sizeMap = [
            'XS' => 'Small',
            'S' => 'Small',
            'M' => 'Medium',
            'L' => 'Large',
            'XL' => 'Large',
        ];

        $rawSize = $dog->getSize();
        $normalizedSize = $rawSize ? ($sizeMap[$rawSize] ?? $rawSize) : 'Medium';

        $rawGender = $dog->getGender();
        $normalizedGender = in_array($rawGender, ['Male', 'Female'], true) ? $rawGender : 'Male';

        $payload = [
            'name' => $dog->getName() ?? 'Unknown',
            'age' => (int) ($dog->getAge() ?? 0),
            'breed' => $dog->getBreed() ?? 'Mixed',
            'gender' => $normalizedGender,
            'size' => $normalizedSize,
            'weight' => (float) ($dog->getWeight() ?? 0),
            'vaccinated' => (bool) $dog->isVaccinated(),
            'friendly_with_kids' => (bool) $dog->isFriendlyWithKids(),
            'friendly_with_dogs' => (bool) $dog->isFriendlyWithDogs(),
            'friendly_with_cats' => (bool) $dog->isFriendlyWithCats(),
            'health_status' => $dog->getHealthStatus() ?? 'Healthy',
            'adoption_status' => $dog->getAdoptionStatus() ?? 'Available',
            'arrival_date' => $dog->getArrivalDate()?->format('Y-m-d') ?? (new \DateTime())->format('Y-m-d'),
        ];

        try {
            $apiResponse = $httpClient->request('POST', 'http://127.0.0.1:9000/generate-description', [
                'json' => $payload,
                'headers' => [
                    'Content-Type' => 'application/json',
                    'Accept' => 'application/json',
                ],
                'timeout' => 15,
            ]);

            $statusCode = $apiResponse->getStatusCode();
            $data = $apiResponse->toArray(false);

            if ($statusCode >= 400) {
                $detail = '';
                if (isset($data['detail']) && is_string($data['detail'])) {
                    $detail = $data['detail'];
                } elseif (isset($data['detail']) && is_array($data['detail'])) {
                    $detail = json_encode($data['detail'], JSON_UNESCAPED_UNICODE);
                } else {
                    $detail = json_encode($data, JSON_UNESCAPED_UNICODE);
                }

                return new JsonResponse([
                    'ok' => false,
                    'message' => 'AI API returned an error.'.($detail ? ' '.$detail : ''),
                    'api_response' => $data,
                    'payload_sent' => $payload,
                ], $statusCode);
            }

            return new JsonResponse([
                'ok' => true,
                'description' => $data['description'] ?? '',
                'api_response' => $data,
            ]);
        } catch (\Throwable $e) {
            return new JsonResponse([
                'ok' => false,
                'message' => 'Failed to call AI API.',
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }
    }

    #[Route('/front_dogs/apply_adoption/{id}', name: 'app_apply_adoption', methods: ['GET', 'POST'])]
    public function applyAdoption(
        Dogs $dog,
        Request $request,
        EntityManagerInterface $entityManager,
        AdoptionRepository $adoptionRepository
    ): Response {
        $activeUser = $this->getActiveSessionUser($request, $entityManager);
        if (!$activeUser instanceof User) {
            $this->addFlash('error', 'User not found.');
            return $this->redirectToRoute('app_signin');
        }

        if ($dog->getAdoptionStatus() === 'Adopted') {
            $this->addFlash('error', 'This dog is already adopted and not available for new applications.');
            return $this->redirectToRoute('app_front_dogs_show', ['id' => $dog->getId()]);
        }

        $existingApplication = $adoptionRepository->findOneBy([
            'dog' => $dog,
            'user' => $activeUser,
        ]);

        if ($existingApplication instanceof Adoption) {
            $this->addFlash('info', 'You already submitted an adoption application for this dog.');
            return $this->redirectToRoute('app_front_dogs_show', ['id' => $dog->getId()]);
        }

        $adoption = new Adoption();
        $adoption->setDog($dog);
        $adoption->setUser($activeUser);
        $adoption->setCreatedAt(new \DateTime());

        $form = $this->createForm(AdoptionType::class, $adoption);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $dog->setAdoptionStatus('Reserved');
            $entityManager->persist($adoption);
            $entityManager->flush();
            $this->addFlash('success', 'Adoption application submitted!');
            return $this->redirectToRoute('app_front_dogs');
        }

        if ($form->isSubmitted()) {
            return $this->render('front_dogs/apply_adoption.html.twig', [
                'dog' => $dog,
                'form' => $form->createView(),
            ], new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
        }

        return $this->render('front_dogs/apply_adoption.html.twig', [
            'dog' => $dog,
            'form' => $form->createView(),
        ]);
    }

    private function getActiveSessionUser(Request $request, EntityManagerInterface $entityManager): ?User
    {
        $sessionUser = $request->getSession()->get('user');

        if ($sessionUser instanceof User) {
            return $sessionUser;
        }

        $userId = is_array($sessionUser) ? ($sessionUser['id'] ?? null) : null;
        if (!$userId) {
            return null;
        }

        return $entityManager->getRepository(User::class)->find($userId);
    }
}
