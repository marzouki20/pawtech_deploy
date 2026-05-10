<?php

namespace App\Controller;

use App\Entity\Evenement;
use App\Entity\Participation;
use App\Entity\User;
use App\Repository\EvenementRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * PublicEventController - Public-facing event pages
 * Part of the Events module (Nesrine)
 */
#[Route('/events')]
final class PublicEventController extends AbstractController
{
    #[Route('', name: 'app_events')]
    public function index(Request $request, EvenementRepository $evenementRepository): Response
    {
        $type = $request->query->get('type', '');
        $ville = $request->query->get('ville', '');
        $q = $request->query->get('q', '');

        return $this->render('pages/events.html.twig', [
            'events' => $evenementRepository->findActiveWithFilters($type ?: null, $ville ?: null, $q ?: null),
            'villes' => $evenementRepository->findActiveCities(),
            'currentType' => $type,
            'currentVille' => $ville,
            'currentQ' => $q,
        ]);
    }

    /**
     * AJAX endpoint for filtering events
     */
    #[Route('/filter', name: 'app_events_filter', methods: ['GET'])]
    public function filterEvents(Request $request, EvenementRepository $evenementRepository): JsonResponse
    {
        $type = $request->query->get('type', '');
        $ville = $request->query->get('ville', '');
        $q = $request->query->get('q', '');

        $events = $evenementRepository->findActiveWithFilters(
            $type ?: null, 
            $ville ?: null, 
            $q ?: null
        );

        // Default images by type
        $typeImages = [
            'VACCINATION' => 'https://images.unsplash.com/photo-1587300003388-59208cc962cb?w=400&h=225&fit=crop',
            'ADOPTION' => 'https://images.unsplash.com/photo-1601758228041-f3b2795255f1?w=400&h=225&fit=crop',
            'SENSIBILISATION' => 'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=400&h=225&fit=crop',
            'COLLECTE_DONS' => 'https://images.unsplash.com/photo-1450778869180-41d0601e046e?w=400&h=225&fit=crop',
        ];
        $defaultImage = 'https://images.unsplash.com/photo-1534361960057-19889db9621e?w=400&h=225&fit=crop';

        $eventsData = [];
        foreach ($events as $event) {
            $eventsData[] = [
                'id' => $event->getId(),
                'titre' => $event->getTitre(),
                'type' => $event->getType(),
                'ville' => $event->getVille(),
                'lieu' => $event->getLieu(),
                'date_debut' => $event->getDateDebut()->format('M d, Y'),
                'description' => substr($event->getDescription() ?? '', 0, 100) . '...',
                'image' => $event->getImage() ?: ($typeImages[$event->getType()] ?? $defaultImage),
                'url' => $this->generateUrl('app_event_detail', ['id' => $event->getId()]),
            ];
        }

        return new JsonResponse([
            'ok' => true,
            'count' => count($eventsData),
            'events' => $eventsData,
        ]);
    }

    #[Route('/{id}', name: 'app_event_detail', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function detail(
        Request $request,
        Evenement $evenement, 
        EvenementRepository $evenementRepository,
        EntityManagerInterface $entityManager
    ): Response {
        if (!$evenementRepository->isEventActive($evenement)) {
            throw $this->createNotFoundException('Event not found');
        }

        $errors = [];
        $formData = [
            'prenom' => '',
            'nom' => '',
            'email' => '',
            'telephone' => '',
        ];

        // Check if event has ended (use dateFin if available, otherwise dateDebut)
        $endDate = $evenement->getDateFin() ?? $evenement->getDateDebut();
        $eventPassed = $endDate < new \DateTime();

        // Handle POST request (form submission)
        if ($request->isMethod('POST')) {
            // Prevent registration if event has ended
            if ($eventPassed) {
                $this->addFlash('error', 'Registration is closed. This event has already ended.');
                return $this->redirectToRoute('app_event_detail', ['id' => $evenement->getId()]);
            }

            // Get form data
            $formData['prenom'] = trim($request->request->get('prenom', ''));
            $formData['nom'] = trim($request->request->get('nom', ''));
            $formData['email'] = trim($request->request->get('email', ''));
            $formData['telephone'] = trim($request->request->get('telephone', ''));

            // PHP Validation
            if (empty($formData['prenom'])) {
                $errors['prenom'] = 'First name is required';
            } elseif (strlen($formData['prenom']) < 2) {
                $errors['prenom'] = 'First name must be at least 2 characters';
            } elseif (!preg_match('/^[a-zA-ZÀ-ÿ\s\-]+$/', $formData['prenom'])) {
                $errors['prenom'] = 'First name must contain only letters';
            }

            if (empty($formData['nom'])) {
                $errors['nom'] = 'Last name is required';
            } elseif (strlen($formData['nom']) < 2) {
                $errors['nom'] = 'Last name must be at least 2 characters';
            } elseif (!preg_match('/^[a-zA-ZÀ-ÿ\s\-]+$/', $formData['nom'])) {
                $errors['nom'] = 'Last name must contain only letters';
            }

            if (empty($formData['email'])) {
                $errors['email'] = 'Email is required';
            } elseif (!filter_var($formData['email'], FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'Please enter a valid email address';
            }

            if (!empty($formData['telephone']) && !preg_match('/^[0-9]{8}$/', $formData['telephone'])) {
                $errors['telephone'] = 'Phone number must be exactly 8 digits';
            }

            // Check capacity
            if ($evenement->getCapaciteMax() !== null) {
                $currentParticipants = $evenement->getParticipations()->count();
                if ($currentParticipants >= $evenement->getCapaciteMax()) {
                    $this->addFlash('error', 'Sorry, this event is full.');
                    return $this->redirectToRoute('app_event_detail', ['id' => $evenement->getId()]);
                }
            }

            // If no errors, process registration
            if (empty($errors)) {
                // Find or create user
                $userRepo = $entityManager->getRepository(User::class);
                $user = $userRepo->findOneBy(['email' => $formData['email']]);

                if (!$user) {
                    // Create new user
                    $user = new User();
                    $user->setEmail($formData['email']);
                    $user->setRole('ROLE_USER');
                    $user->setStatus('active');
                    $user->setPassword('temp_password');
                    $user->setUserImage('default.png');
                    $entityManager->persist($user);
                }
                
                // Always update name from form (in case user enters different name)
                $user->setNom($formData['nom']);
                $user->setPrenom($formData['prenom']);
                if (!empty($formData['telephone'])) {
                    $user->setPhone($formData['telephone']);
                }

                // Check if already registered
                $existingParticipation = $entityManager->getRepository(Participation::class)->findOneBy([
                    'user' => $user,
                    'evenement' => $evenement,
                ]);

                if ($existingParticipation) {
                    $this->addFlash('warning', 'You are already registered for this event.');
                    return $this->redirectToRoute('app_event_detail', ['id' => $evenement->getId()]);
                }

                // Create participation
                $participation = new Participation();
                $participation->setUser($user);
                $participation->setEvenement($evenement);
                $participation->setDateParticipation(new \DateTime());
                $participation->setStatut('EN_ATTENTE');

                $entityManager->persist($participation);
                $entityManager->flush();

                $this->addFlash('success', 'Your registration has been recorded! You will receive a confirmation email.');
                return $this->redirectToRoute('app_event_detail', ['id' => $evenement->getId()]);
            }
        }

        return $this->render('pages/event_detail.html.twig', [
            'event' => $evenement,
            'errors' => $errors,
            'formData' => $formData,
            'eventPassed' => $eventPassed,
        ]);
    }

    /**
     * Event Recommendation Endpoint - Uses KNN Algorithm via Python API
     * Part of "Métier Avancé" - AI-powered event recommendations
     */
    #[Route('/recommend', name: 'app_event_recommend', methods: ['POST'])]
    public function recommendEvents(
        Request $request,
        EvenementRepository $evenementRepository,
        HttpClientInterface $httpClient
    ): JsonResponse {
        $jsonResponse = static function (array $data, int $status = Response::HTTP_OK): JsonResponse {
            $response = new JsonResponse(null, $status);
            $response->setEncodingOptions(
                $response->getEncodingOptions() | JSON_INVALID_UTF8_SUBSTITUTE | JSON_UNESCAPED_UNICODE
            );
            $response->setData($data);

            return $response;
        };

        $clean = static function (?string $value): string {
            $value = $value ?? '';
            $cleaned = @iconv('UTF-8', 'UTF-8//IGNORE', $value);

            return $cleaned !== false ? $cleaned : $value;
        };

        $payload = json_decode($request->getContent(), true);
        
        if (!is_array($payload)) {
            return $jsonResponse([
                'ok' => false,
                'message' => 'Invalid JSON payload'
            ], Response::HTTP_BAD_REQUEST);
        }

        $userPreferences = $payload['user_preferences'] ?? [];

        // Get all active upcoming events
        $events = $evenementRepository->findActiveWithFilters(null, null, null);
        
        if (empty($events)) {
            return $jsonResponse([
                'ok' => true,
                'recommendations' => [],
                'events' => [],
                'message' => 'No upcoming events available'
            ]);
        }

        // Prepare events data for the Python API
        $eventsData = [];
        foreach ($events as $event) {
            $eventsData[] = [
                'id' => $event->getId(),
                'type' => $event->getType(),
                'ville' => $event->getVille(),
                'date_debut' => $event->getDateDebut()->format('Y-m-d'),
                'capacite_max' => $event->getCapaciteMax(),
                'current_participants' => $event->getParticipations()->count(),
            ];
        }

        try {
            // Call Python KNN API
            $apiResponse = $httpClient->request('POST', 'http://127.0.0.1:8003/recommend', [
                'json' => [
                    'user_preferences' => $userPreferences,
                    'events' => $eventsData,
                    'top_n' => 6,
                ],
                'timeout' => 10,
            ]);

            $statusCode = $apiResponse->getStatusCode();
            $rawContent = $apiResponse->getContent(false);
            $apiData = json_decode($rawContent, true);

            if (!is_array($apiData)) {
                return $jsonResponse([
                    'ok' => false,
                    'message' => 'Recommendation API returned invalid JSON: ' . json_last_error_msg(),
                    'status_code' => $statusCode,
                    'api_response' => substr($rawContent, 0, 500),
                ], Response::HTTP_BAD_GATEWAY);
            }

            if ($statusCode >= 400 || !($apiData['ok'] ?? false)) {
                return $jsonResponse([
                    'ok' => false,
                    'message' => 'Recommendation API error',
                    'api_response' => $apiData,
                ], $statusCode >= 400 ? $statusCode : Response::HTTP_BAD_GATEWAY);
            }

            $recommendedIds = $apiData['recommendations'] ?? [];
            $scores = $apiData['scores'] ?? [];

            // Fetch recommended events in order
            $recommendedEvents = [];
            foreach ($recommendedIds as $index => $id) {
                foreach ($events as $event) {
                    if ($event->getId() === (int)$id) {
                        $recommendedEvents[] = [
                            'id' => $event->getId(),
                            'titre' => $clean($event->getTitre()),
                            'type' => $clean($event->getType()),
                            'ville' => $clean($event->getVille()),
                            'lieu' => $clean($event->getLieu()),
                            'date_debut' => $event->getDateDebut()->format('M d, Y'),
                            'date_debut_raw' => $event->getDateDebut()->format('Y-m-d'),
                            'description' => substr($clean($event->getDescription()), 0, 100) . '...',
                            'image' => $clean($event->getImage()),
                            'capacite_max' => $event->getCapaciteMax(),
                            'current_participants' => $event->getParticipations()->count(),
                            'score' => $scores[$index] ?? 0,
                        ];
                        break;
                    }
                }
            }

            return $jsonResponse([
                'ok' => true,
                'recommendations' => $recommendedIds,
                'events' => $recommendedEvents,
                'algorithm' => 'KNN',
                'scores' => $scores,
            ]);

        } catch (TransportExceptionInterface $e) {
            return $jsonResponse([
                'ok' => false,
                'message' => 'Failed to connect to recommendation API. Make sure the Python server is running.',
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        } catch (\Throwable $e) {
            return $jsonResponse([
                'ok' => false,
                'message' => 'Recommendation API request failed: ' . $e->getMessage(),
                'error' => $e->getMessage(),
            ], Response::HTTP_BAD_GATEWAY);
        }
    }
}
