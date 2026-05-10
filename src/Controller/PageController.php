<?php

namespace App\Controller;

use App\Form\DonationType;
use App\Entity\User;
use App\Entity\Donation;
use App\Repository\DogsRepository;
use App\Repository\EvenementRepository;
use App\Repository\UserRepository;
use App\Repository\ProduitRepository;
use App\Repository\CategorieRepository;
use App\Form\ProfileSettingsType;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\JsonResponse;

use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Symfony\Component\Security\Core\Authentication\Token\UsernamePasswordToken;

final class PageController extends AbstractController
{
    #[Route('/', name: 'app_home', methods: ['GET'])]
    #[Route('/home', name: 'app_home_alias', methods: ['GET'])]
    public function home(Request $request, DogsRepository $dogsRepository, EvenementRepository $evenementRepository, UserRepository $userRepository): Response
    {
        $this->refreshSessionUserFromDatabase($request, $userRepository);

        $dogs = array_slice($dogsRepository->filterDogs('Available', null, null), 0, 4);
        $events = $evenementRepository->findUpcomingEvents(3);

        return $this->render('pages/home.html.twig', [
            'dogs' => $dogs,
            'events' => $events,
        ]);
    }


    #[Route('/signin', name: 'app_signin', methods: ['GET', 'POST'])]
    public function signin(Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager, UserPasswordHasherInterface $passwordHasher): Response
    {
        $session = $request->getSession();
        if ($session->has('user')) {
            return $this->redirectToRoute('app_home');
        }

        $error = null;
        $lastEmail = '';

        if ($request->isMethod('POST')) {
            $email = trim((string) $request->request->get('email', ''));
            $password = (string) $request->request->get('password', '');
            $lastEmail = $email;

            if ($email === '' || $password === '') {
                $error = [
                    'messageKey' => 'Email and password are required.',
                    'messageData' => [],
                ];
                return $this->render('sign/signin.html.twig', [
                    'error' => $error,
                    'last_email' => $lastEmail,
                ]);
            }

            $user = $userRepository->findOneBy(['email' => $email]);

            if ($user) {
                if (!$passwordHasher->isPasswordValid($user, $password)) {
                    $error = [
                        'messageKey' => 'Invalid email or password.',
                        'messageData' => [],
                    ];
                } else {
                    $roles = $user->getRoles();

                    $session->set('user', [
                        'id' => $user->getId(),
                        'email' => $user->getEmail(),
                        'prenom' => $user->getPrenom(),
                        'nom' => $user->getNom(),
                        'userImage' => $user->getUserImage(),
                        'role' => $roles[0] ?? 'ROLE_USER',
                    ]);

                    return $this->redirectToRoute('app_home');
                }
            } else {
                $username = strtok($email, '@') ?: 'User';
                $firstName = ucfirst($username);

                $user = new User();
                $user->setPrenom($firstName);
                $user->setNom('User');
                $user->setEmail($email);
                $user->setTelephone(0);
                $user->setRole('Client');
                $user->setStatus('Actif');
                $user->setUserImage('uploads/users/default.png');
                $user->setPassword($passwordHasher->hashPassword($user, $password));

                $entityManager->persist($user);
                $entityManager->flush();

                $roles = $user->getRoles();
                $session->set('user', [
                    'id' => $user->getId(),
                    'email' => $user->getEmail(),
                    'prenom' => $user->getPrenom(),
                    'nom' => $user->getNom(),
                    'userImage' => $user->getUserImage(),
                    'role' => $roles[0] ?? 'ROLE_USER',
                ]);

                return $this->redirectToRoute('app_home');
            }
        }

        return $this->render('sign/signin.html.twig', [
            'error' => $error,
            'last_email' => $lastEmail,
        ]);
    }


   

    

    #[Route('/account', name: 'app_account', methods: ['GET'])]
    public function account(): Response
    {
        return $this->redirectToRoute('app_settings');
    }

    #[Route('/settings', name: 'app_settings', methods: ['GET', 'POST'])]
    public function settings(Request $request, UserRepository $userRepository, EntityManagerInterface $entityManager): Response
    {
        $sessionUser = $request->getSession()->get('user');
        $userId = is_array($sessionUser) && isset($sessionUser['id']) ? (int) $sessionUser['id'] : null;

        if (!$userId) {
            return $this->redirectToRoute('app_signin');
        }

        $user = $userRepository->find($userId);
        if (!$user) {
            $request->getSession()->remove('user');
            return $this->redirectToRoute('app_signin');
        }


        $form = $this->createForm(ProfileSettingsType::class, $user);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $submitted = $request->request->all('profile_settings');
            $submittedFace = is_array($submitted) ? (string) ($submitted['user_face'] ?? '') : '';
            if ($submittedFace !== '') {
                $user->setUserFace($submittedFace);
            }

            $entityManager->flush();


            $roles = $user->getRoles();
            $request->getSession()->set('user', [
                'id' => $user->getId(),
                'email' => $user->getEmail(),
                'prenom' => $user->getPrenom(),
                'nom' => $user->getNom(),
                'userImage' => $user->getUserFace() ?: $user->getUserImage(),
                'role' => $roles[0] ?? 'ROLE_USER',
            ]);



            $this->addFlash('success', 'Profile updated successfully.');
            return $this->redirectToRoute('app_settings');
        }

        return $this->render('accountinfo/account.html.twig', [

            'form' => $form->createView(),
            'user' => $user,

        ]);
    }

    #[Route('/profile', name: 'app_profile', methods: ['GET'])]
    public function profile(): Response
    {
        return $this->redirectToRoute('app_settings');
    }

    private function refreshSessionUserFromDatabase(Request $request, UserRepository $userRepository): void
    {
        if (!$request->hasSession()) {
            return;
        }

        $session = $request->getSession();
        $sessionUser = $session->get('user');
        if (!is_array($sessionUser)) {
            return;
        }

        $userId = isset($sessionUser['id']) ? (int) $sessionUser['id'] : 0;
        if ($userId <= 0) {
            return;
        }

        $user = $userRepository->find($userId);
        if ($user === null) {
            $session->remove('user');

            return;
        }

        $roles = $user->getRoles();
        $session->set('user', [
            'id' => $user->getId(),
            'email' => $user->getEmail(),
            'prenom' => $user->getPrenom(),
            'nom' => $user->getNom(),
            'userImage' => $user->getUserFace() ?: $user->getUserImage(),
            'role' => $roles[0] ?? 'ROLE_USER',
        ]);
    }
//ahawaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaaa
    #[Route('/api/verify-face', name: 'app_verify_face_proxy', methods: ['POST'])]
    public function verifyFaceProxy(
        Request $request,
        HttpClientInterface $httpClient,
        UserRepository $userRepository,
        TokenStorageInterface $tokenStorage
    ): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        $userFace = is_array($payload) ? (string) ($payload['user_face'] ?? '') : '';

        if ($userFace === '') {
            return $this->json([
                'message' => 'no',
                'username' => null,
                'error' => 'Missing user_face',
            ], Response::HTTP_BAD_REQUEST);
        }

        try {
            $apiResponse = $httpClient->request('POST', 'http://localhost:9010/verify-face', [
                'headers' => [
                    'Content-Type' => 'application/json',
                ],
                'json' => [
                    'user_face' => $userFace,
                ],
            ]);

            $status = $apiResponse->getStatusCode();
            $data = $apiResponse->toArray(false);

            if (($data['message'] ?? null) === 'yes') {
                $username = (string) ($data['username'] ?? '');
                $matchedUser = null;

                if ($username !== '') {
                    $matchedUser = $userRepository->findOneBy(['email' => $username])
                        ?? $userRepository->findOneBy(['prenom' => $username])
                        ?? $userRepository->findOneBy(['nom' => $username]);
                }

                if ($matchedUser) {
                    $token = new UsernamePasswordToken($matchedUser, 'main', $matchedUser->getRoles());
                    $tokenStorage->setToken($token);
                    $request->getSession()->set('_security_main', serialize($token));

                    $roles = $matchedUser->getRoles();
                    $request->getSession()->set('user', [
                        'id' => $matchedUser->getId(),
                        'email' => $matchedUser->getEmail(),
                        'prenom' => $matchedUser->getPrenom(),
                        'nom' => $matchedUser->getNom(),
                        'userImage' => $matchedUser->getUserFace() ?: $matchedUser->getUserImage(),
                        'role' => $roles[0] ?? 'ROLE_USER',
                    ]);
                }
            }

            return $this->json($data, $status);
        } catch (\Throwable $e) {
            return $this->json([
                'message' => 'no',
                'username' => null,
                'error' => 'Face verification service unavailable',
            ], Response::HTTP_BAD_GATEWAY);
        }
    }

    #[Route('/my-pets', name: 'app_my_pets', methods: ['GET'])]
    public function myPets(): Response
    {
        return $this->redirectToRoute('app_dashboard');
    }

    #[Route('/pages/about', name: 'app_about', methods: ['GET'])]
    public function about(): Response
    {
        return $this->render('pages/about.html.twig');
    }

    #[Route('/pages/contact', name: 'app_contact', methods: ['GET'])]
    public function contact(): Response
    {
        return $this->render('pages/contact.html.twig');
    }

    #[Route('/pages/dogs', name: 'app_dogs', methods: ['GET'])]
    public function dogs(): Response
    {
        return $this->render('pages/dogs.html.twig');
    }

    #[Route('/pages/events', name: 'app_events', methods: ['GET'])]
    public function events(): Response
    {
        return $this->render('pages/events.html.twig');
    }

    #[Route('/donation', name: 'app_donation')]
    public function index(Request $request, EntityManagerInterface $entityManager): Response
    {
        $donation = new Donation();
        $form = $this->createForm(DonationType::class, $donation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($donation);
            $entityManager->flush();

            $this->addFlash('donation_success', 'Thank you for your donation!');
            return $this->redirectToRoute('app_donation');
        }

        // Solution 1: Vérifier si le paramètre existe
        try {
            $stripeKey = $this->getParameter('stripe_public_key');
        } catch (\Exception $e) {
            $stripeKey = ''; // ou une clé de test
            // $stripeKey = 'pk_test_51P...'; // clé de test temporaire
        }

        // Solution 2: Utiliser directement $_ENV
        // $stripeKey = $_ENV['STRIPE_PUBLIC_KEY'] ?? '';

        // Solution 3: Désactiver Stripe si pas configuré
        // $stripeKey = null;

        return $this->render('pages/donation.html.twig', [
            'form' => $form->createView(),
            'stripe_key' => $stripeKey,
        ]);
    }

    //#[Route('/shop', name: 'app_shop', methods: ['GET'])]
    #[Route('/pages/shop', name: 'app_shop', methods: ['GET'])]
    public function shop(
        Request $request,
        ProduitRepository $produitRepository,
        CategorieRepository $categorieRepository
    ): Response
    {
        // Récupérer les paramètres de filtrage
        $search = $request->query->get('search', '');
        $categorieId = $request->query->getInt('categorie', 0); // 0 si non défini
        $minPrice = $request->query->get('min_price', '');
        $maxPrice = $request->query->get('max_price', '');
        $sort = $request->query->get('sort', 'latest');
        
        // Récupérer tous les produits (vous pouvez ajouter la méthode findFiltered plus tard)
        $produits = $produitRepository->findAll();
        
        // Appliquer les filtres manuellement si pas de méthode findFiltered
        if ($categorieId > 0) {
            $produits = array_filter($produits, function($produit) use ($categorieId) {
                return $produit->getCategorie() && $produit->getCategorie()->getId() === $categorieId;
            });
        }
        
        if ($search) {
            $produits = array_filter($produits, function($produit) use ($search) {
                return stripos($produit->getNom(), $search) !== false;
            });
        }
        
        if ($minPrice !== '') {
            $minPrice = (float) $minPrice;
            $produits = array_filter($produits, function($produit) use ($minPrice) {
                return $produit->getPrix() >= $minPrice;
            });
        }
        
        if ($maxPrice !== '') {
            $maxPrice = (float) $maxPrice;
            $produits = array_filter($produits, function($produit) use ($maxPrice) {
                return $produit->getPrix() <= $maxPrice;
            });
        }
        
        // Trier les produits
        switch ($sort) {
            case 'price_low':
                usort($produits, function($a, $b) {
                    return $a->getPrix() <=> $b->getPrix();
                });
                break;
            case 'price_high':
                usort($produits, function($a, $b) {
                    return $b->getPrix() <=> $a->getPrix();
                });
                break;
            case 'name_asc':
                usort($produits, function($a, $b) {
                    return strcmp($a->getNom(), $b->getNom());
                });
                break;
            case 'name_desc':
                usort($produits, function($a, $b) {
                    return strcmp($b->getNom(), $a->getNom());
                });
                break;
            case 'latest':
            default:
                // Garder l'ordre par défaut ou trier par ID décroissant
                usort($produits, function($a, $b) {
                    return $b->getId() <=> $a->getId();
                });
                break;
        }
        
        // Récupérer toutes les catégories
        $categories = $categorieRepository->findAll();
        
        return $this->render('pages/shop.html.twig', [
            'produits' => $produits,
            'categories' => $categories,
            'search' => $search,
            'selectedCategorie' => $categorieId, // <-- AJOUTEZ CETTE LIGNE
            'minPrice' => $minPrice,
            'maxPrice' => $maxPrice,
            'sort' => $sort,
        ]);
    }



    #[Route('/pages/veterinarian', name: 'app_veterinarian_page', methods: ['GET'])]
    public function veterinarianPage(UserRepository $userRepository): Response
    {
        $veterinarians = $userRepository->findVeterinarians();

        return $this->render('pages/veterinarian.html.twig', [
            'veterinarians' => $veterinarians,
        ]);
    }















    

    #[Route('/clients', name: 'app_clients_index', methods: ['GET'])]
    public function clients(): Response
    {
        return $this->renderEntity('clients/index.html.twig', 'Clients', 'clients', [
            'Client ID', 'Name', 'Email', 'Phone', 'Status',
        ], [], 'Add New Client', [
            ['name' => 'name', 'placeholder' => 'Client name'],
            ['name' => 'email', 'type' => 'email', 'placeholder' => 'Email address'],
            ['name' => 'phone', 'placeholder' => 'Phone number'],
        ]);
    }

    private function renderEntity(
        string $template,
        string $pageTitle,
        string $active,
        array $columns,
        array $rows,
        string $modalTitle,
        array $modalFields,
        ?string $addHref = null,
        array $extra = []
    ): Response {
        return $this->render($template, array_merge([
            'page_title' => $pageTitle,
            'active' => $active,
            'entity_name' => $pageTitle,
            'columns' => $columns,
            'rows' => $rows,
            'modal_title' => $modalTitle,
            'modal_fields' => $modalFields,
            'add_href' => $addHref,
            'total_records' => count($rows),
            'per_page' => 10,
            'page' => 1,
            'total_pages' => 1,
        ], $extra));
    }

}
