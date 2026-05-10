<?php

namespace App\Controller;

use App\Entity\Produit;
use App\Entity\Commande;
use App\Entity\LigneCommande;
use App\Form\ProduitType;
use App\Repository\ProduitRepository;
use App\Repository\CategorieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

#[Route('/dashboard/eshop/produits')]
class ProduitController extends AbstractController
{
    private const SAMPLE_PREFIXES = [
        'Deluxe',
        'Comfort',
        'Active',
        'Cozy',
        'Urban',
        'Premium',
        'Fresh',
        'Classic',
        'Smart',
        'Pure'
    ];

    private const SAMPLE_SUFFIXES = [
        'Collar',
        'Bites',
        'Harness',
        'Bowl',
        'Toy Set',
        'Carrier',
        'Treat Pack',
        'Bed',
        'Grooming Kit',
        'Snack Mix'
    ];

    private function uploadImage($imageFile, $oldImage = null)
    {
        if ($imageFile) {
            $newFilename = uniqid().'.'.$imageFile->guessExtension();
            
            $imageFile->move(
                $this->getParameter('kernel.project_dir').'/public/uploads/images',
                $newFilename
            );
            
            if ($oldImage && $oldImage !== $newFilename) {
                $oldImagePath = $this->getParameter('kernel.project_dir').'/public/uploads/images/'.$oldImage;
                if (file_exists($oldImagePath)) {
                    unlink($oldImagePath);
                }
            }
            
            return $newFilename;
        }
        
        return $oldImage;
    }

    private function deleteImage($imageName)
    {
        if ($imageName) {
            $imagePath = $this->getParameter('kernel.project_dir').'/public/uploads/images/'.$imageName;
            if (file_exists($imagePath)) {
                unlink($imagePath);
            }
        }
    }

    private function buildSampleName(): string
    {
        $prefix = self::SAMPLE_PREFIXES[array_rand(self::SAMPLE_PREFIXES)];
        $suffix = self::SAMPLE_SUFFIXES[array_rand(self::SAMPLE_SUFFIXES)];

        return sprintf('%s %s', $prefix, $suffix);
    }

    #[Route('/samples/generate', name: 'app_eshop_produit_generate_samples', methods: ['POST'])]
    public function generateSampleProducts(Request $request, EntityManagerInterface $em, CategorieRepository $categorieRepository): JsonResponse
    {
        $data = json_decode($request->getContent() ?? '', true);
        $count = max(1, min(30, (int) ($data['count'] ?? 5)));
        $categories = $categorieRepository->findAll();

        if (empty($categories)) {
            return $this->json([
                'success' => false,
                'message' => 'Please create at least one category before generating sample products.'
            ], 422);
        }

        $createdProducts = [];

        for ($i = 0; $i < $count; $i++) {
            $produit = new Produit();
            $produit->setNom($this->buildSampleName() . ' #' . ($i + 1));
            $produit->setPrix(mt_rand(2500, 25000) / 100);
            $produit->setQuantite(mt_rand(5, 50));
            $produit->setSeuilAlert(max(1, (int) floor($produit->getQuantite() / 5)));
            $produit->setImage(null);
            $produit->setCategorie($categories[array_rand($categories)]);

            $em->persist($produit);
            $createdProducts[] = [
                'name' => $produit->getNom(),
                'price' => $produit->getPrix(),
                'stock' => $produit->getQuantite()
            ];
        }

        $em->flush();

        return $this->json([
            'success' => true,
            'message' => sprintf('%d sample product(s) created.', $count),
            'created' => $createdProducts
        ]);
    }

    #[Route('', name: 'app_eshop_produit_index', methods: ['GET'])]
    public function index(Request $request, ProduitRepository $produitRepository, CategorieRepository $categorieRepository): Response
    {
        $search = $request->query->get('search', '');
        $category = $request->query->get('category', '');
        $stockStatus = $request->query->get('stock_status', '');
        $priceRange = $request->query->get('price_range', '');
        $sort = $request->query->get('sort', 'id_desc');
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 20;

        $qb = $produitRepository->createQueryBuilder('p')
            ->leftJoin('p.categorie', 'c');

        if ($search) {
            $qb->andWhere('p.nom LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($category && $category !== 'all') {
            $qb->andWhere('c.id = :categoryId')
               ->setParameter('categoryId', $category);
        }

        if ($stockStatus && $stockStatus !== 'all') {
            switch ($stockStatus) {
                case 'in_stock':
                    $qb->andWhere('p.quantite > 10');
                    break;
                case 'low_stock':
                    $qb->andWhere('p.quantite <= 10 AND p.quantite > 0');
                    break;
                case 'out_of_stock':
                    $qb->andWhere('p.quantite = 0');
                    break;
            }
        }

        if ($priceRange && $priceRange !== 'all') {
            switch ($priceRange) {
                case '0-50':
                    $qb->andWhere('p.prix BETWEEN 0 AND 50');
                    break;
                case '50-100':
                    $qb->andWhere('p.prix BETWEEN 50 AND 100');
                    break;
                case '100-200':
                    $qb->andWhere('p.prix BETWEEN 100 AND 200');
                    break;
                case '200+':
                    $qb->andWhere('p.prix >= 200');
                    break;
            }
        }

        switch ($sort) {
            case 'id_asc':
                $qb->orderBy('p.id', 'ASC');
                break;
            case 'name_asc':
                $qb->orderBy('p.nom', 'ASC');
                break;
            case 'name_desc':
                $qb->orderBy('p.nom', 'DESC');
                break;
            case 'price_asc':
                $qb->orderBy('p.prix', 'ASC');
                break;
            case 'price_desc':
                $qb->orderBy('p.prix', 'DESC');
                break;
            default:
                $qb->orderBy('p.id', 'DESC');
        }

        $totalQuery = clone $qb;
        $total = $totalQuery->select('COUNT(p.id)')->getQuery()->getSingleScalarResult();
        
        $offset = ($page - 1) * $perPage;
        $qb->setFirstResult($offset)
           ->setMaxResults($perPage);
        
        $produits = $qb->getQuery()->getResult();
        $totalPages = ceil($total / $perPage);

        $categories_for_filter = $categorieRepository->findAll();

        $columns = ['ID', 'Name', 'Price', 'Image', 'Category', 'Stock', 'Actions'];
        $rows = [];
        foreach ($produits as $p) {
            $qte = $p->getQuantite() ?? '-';
            $alerte = ($p->getSeuilAlert() !== null && $p->getSeuilAlert() > 0 && $p->getQuantite() !== null && $p->getQuantite() <= $p->getSeuilAlert()) ? ' ⚠' : '';
            
            $imageDisplay = '-';
            if ($p->getImage()) {
                $imageUrl = $this->generateUrl('app_uploads_images', ['filename' => $p->getImage()]);
                $imageDisplay = '<img src="' . $imageUrl . '" alt="' . $p->getNom() . '" class="h-8 w-8 object-cover rounded">';
            }
            
            $rows[] = [
                $p->getId(),
                $p->getNom(),
                number_format((float) $p->getPrix(), 2, ',', ' ') . ' TND',
                $imageDisplay,
                ($p->getCategorie() ? $p->getCategorie()->getNom() : '-'),
                ($qte . $alerte),
                $this->renderView('produit/_actions.html.twig', ['produit' => $p]),
            ];
        }

        return $this->render('produit/index.html.twig', [
            'active' => 'eshop_produits',
            'page_title' => 'Eshop - Products',
            'entity_name' => 'Product',
            'columns' => $columns,
            'rows' => $rows,
            'produits' => $produits,
            'categories_for_filter' => $categories_for_filter,
            'total_records' => $total,
            'per_page' => $perPage,
            'page' => $page,
            'total_pages' => $totalPages,
            'current_filters' => [
                'search' => $search,
                'category' => $category,
                'stock_status' => $stockStatus,
                'price_range' => $priceRange,
                'sort' => $sort,
            ]
        ]);
    }

    #[Route('/new', name: 'app_eshop_produit_new', methods: ['GET','POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $produit = new Produit();
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($request->isXmlHttpRequest() && !$form->isSubmitted()) {
            return $this->render('produit/_form_content.html.twig', [
                'page_title' => 'New product',
                'produit' => $produit,
                'form' => $form->createView(),
                'form_action' => $this->generateUrl('app_eshop_produit_new'),
                'submit_label' => 'Add',
            ]);
        }

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $imageFile = $form->get('image')->getData();
                    if ($imageFile) {
                        $newFilename = $this->uploadImage($imageFile);
                        $produit->setImage($newFilename);
                    } else {
                        $generatedImage = $form->get('generatedImage')->getData();
                        if ($generatedImage) {
                            $produit->setImage($generatedImage);
                        }
                    }
                    
                    $em->persist($produit);
                    $em->flush();
                    
                    if ($request->isXmlHttpRequest()) {
                        return $this->json([
                            'success' => true, 
                            'message' => 'Product "' . $produit->getNom() . '" created successfully!',
                            'redirect' => $this->generateUrl('app_eshop_produit_index')
                        ]);
                    }
                    
                    $this->addFlash('success', 'Product "' . $produit->getNom() . '" created successfully!');
                    return $this->redirectToRoute('app_eshop_produit_index');
                } catch (\Exception $e) {
                    if ($request->isXmlHttpRequest()) {
                        return $this->json([
                            'success' => false,
                            'message' => 'Error: ' . $e->getMessage()
                        ], 500);
                    }
                    
                    $this->addFlash('error', 'Error creating product: ' . $e->getMessage());
                }
            } else {
                if ($request->isXmlHttpRequest()) {
                    return $this->render('produit/_form_content.html.twig', [
                        'page_title' => 'New product',
                        'produit' => $produit,
                        'form' => $form->createView(),
                        'form_action' => $this->generateUrl('app_eshop_produit_new'),
                        'submit_label' => 'Add',
                    ]);
                }
                
                return $this->render('produit/form.html.twig', [
                    'active' => 'eshop_produits',
                    'page_title' => 'New product',
                    'produit' => $produit,
                    'form' => $form->createView(),
                    'submit_label' => 'Add',
                ]);
            }
        }
        
        return $this->render('produit/form.html.twig', [
            'active' => 'eshop_produits',
            'page_title' => 'New product',
            'produit' => $produit,
            'form' => $form->createView(),
            'submit_label' => 'Add',
        ]);
    }

    #[Route('/{id}', name: 'app_eshop_produit_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Request $request, Produit $produit): Response
    {
        if ($request->isXmlHttpRequest()) {
            return $this->render('produit/_show_content.html.twig', [
                'page_title' => 'Product: ' . $produit->getNom(),
                'produit' => $produit,
            ]);
        }

        return $this->render('produit/show.html.twig', [
            'active' => 'eshop_produits',
            'page_title' => 'Product: ' . $produit->getNom(),
            'produit' => $produit,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_eshop_produit_edit', requirements: ['id' => '\d+'], methods: ['GET','POST'])]
    public function edit(Request $request, Produit $produit, EntityManagerInterface $em): Response
    {
        $oldImage = $produit->getImage();
        
        $form = $this->createForm(ProduitType::class, $produit);
        $form->handleRequest($request);

        if ($request->isXmlHttpRequest() && !$form->isSubmitted()) {
            return $this->render('produit/_form_content.html.twig', [
                'page_title' => 'Modify product',
                'produit' => $produit,
                'form' => $form->createView(),
                'form_action' => $this->generateUrl('app_eshop_produit_edit', ['id' => $produit->getId()]),
                'submit_label' => 'Save',
            ]);
        }

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $imageFile = $form->get('image')->getData();
                    $generatedImage = $form->get('generatedImage')->getData();
                    if ($imageFile) {
                        $newFilename = $this->uploadImage($imageFile, $oldImage);
                        $produit->setImage($newFilename);
                    } elseif ($generatedImage && $generatedImage !== $oldImage) {
                        $this->deleteImage($oldImage);
                        $produit->setImage($generatedImage);
                    }
                    
                    $em->flush();
                    
                    if ($request->isXmlHttpRequest()) {
                        return $this->json([
                            'success' => true, 
                            'message' => 'Product "' . $produit->getNom() . '" updated successfully!',
                            'redirect' => $this->generateUrl('app_eshop_produit_index')
                        ]);
                    }
                    
                    $this->addFlash('success', 'Product "' . $produit->getNom() . '" updated successfully!');
                    return $this->redirectToRoute('app_eshop_produit_index');
                } catch (\Exception $e) {
                    if ($request->isXmlHttpRequest()) {
                        return $this->json([
                            'success' => false,
                            'message' => 'Error: ' . $e->getMessage()
                        ], 500);
                    }
                    
                    $this->addFlash('error', 'Error updating product: ' . $e->getMessage());
                }
            } else {
                if ($request->isXmlHttpRequest()) {
                    return $this->render('produit/_form_content.html.twig', [
                        'page_title' => 'Modify product',
                        'produit' => $produit,
                        'form' => $form->createView(),
                        'form_action' => $this->generateUrl('app_eshop_produit_edit', ['id' => $produit->getId()]),
                        'submit_label' => 'Save',
                    ]);
                }
                
                return $this->render('produit/form.html.twig', [
                    'active' => 'eshop_produits',
                    'page_title' => 'Modify product',
                    'produit' => $produit,
                    'form' => $form->createView(),
                    'submit_label' => 'Save',
                ]);
            }
        }

        return $this->render('produit/form.html.twig', [
            'active' => 'eshop_produits',
            'page_title' => 'Modify product',
            'produit' => $produit,
            'form' => $form->createView(),
            'submit_label' => 'Save',
        ]);
    }

    #[Route('/{id}/delete', name: 'app_eshop_produit_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Produit $produit, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $produit->getId(), (string) $request->request->get('_token'))) {
            try {
                $this->deleteImage($produit->getImage());
                
                $em->remove($produit);
                $em->flush();
                $this->addFlash('success', 'Product "' . $produit->getNom() . '" deleted successfully!');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error deleting product: ' . $e->getMessage());
            }
        }
        return $this->redirectToRoute('app_eshop_produit_index');
    }

    #[Route('/{id}/buy', name: 'app_eshop_produit_buy', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function buy(Request $request, Produit $produit, EntityManagerInterface $em): Response
    {
        if (!$this->isCsrfTokenValid('buy_' . $produit->getId(), $request->request->get('_token'))) {
            $this->addFlash('error', 'Invalid security token');
            return $this->redirectToRoute('app_shop');
        }

        $currentStock = $produit->getQuantite() ?? 0;
        
        if ($currentStock <= 0) {
            $this->addFlash('error', 'Sorry, this product is out of stock!');
            return $this->redirectToRoute('app_shop');
        }

        try {
            $produit->setQuantite($currentStock - 1);
            $em->flush();
            $this->addFlash('success', 'Your order has been purchased successfully!');
        } catch (\Exception $e) {
            $this->addFlash('error', 'Error processing purchase: ' . $e->getMessage());
        }

        return $this->redirectToRoute('app_shop');
    }

    #[Route('/cart/checkout', name: 'app_eshop_produit_cart_checkout', methods: ['POST'])]
    public function cartCheckout(
        Request $request,
        EntityManagerInterface $em,
        UrlGeneratorInterface $urlGenerator
    ): Response {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['items']) || empty($data['items'])) {
            return $this->json(['success' => false, 'message' => 'Cart is empty'], 400);
        }

        $paymentMethod = $data['payment_method'] ?? 'local';
        $customer = [
            'first_name' => $data['first_name'] ?? 'Client',
            'last_name' => $data['last_name'] ?? '',
            'email' => $data['email'] ?? '',
            'phone' => $data['phone'] ?? '',
        ];

        try {
            $commande = new Commande();
            $commande->setDate(new \DateTime());
            $commande->setStatut($paymentMethod !== 'clictopay');

            $total = 0;
            foreach ($data['items'] as $item) {
                $produitId = $item['id'];
                $quantite = $item['quantity'];

                $produit = $em->getRepository(Produit::class)->find($produitId);
                if (!$produit) {
                    continue;
                }

                $stockActuel = $produit->getQuantite();
                if ($stockActuel < $quantite) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Insufficient stock for product: ' . $produit->getNom()
                    ], 400);
                }

                $ligneCommande = new LigneCommande();
                $ligneCommande->setQuantite($quantite);
                $ligneCommande->setPrixUnitaire($produit->getPrix());
                $ligneCommande->setCommande($commande);
                $ligneCommande->setProduit($produit);

                $em->persist($ligneCommande);

                $produit->setQuantite($stockActuel - $quantite);
                $em->persist($produit);

                $total += $produit->getPrix() * $quantite;
            }

            $commande->setTotal($total);
            $em->persist($commande);
            $em->flush();

            $responseExtras = [];
            if ($paymentMethod === 'stripe') {
                try {
                    // For Stripe, we'll return the order info and let the frontend handle the payment
                    // The Stripe checkout session will be created client-side or via separate endpoint
                    $responseExtras['payment_type'] = 'stripe';
                    $responseExtras['order_id'] = $commande->getId();
                    $responseExtras['amount'] = $total;
                    $responseExtras['currency'] = 'usd';
                    
                    // You can integrate Stripe Checkout here or use client-side Stripe
                } catch (\RuntimeException $e) {
                    return $this->json([
                        'success' => false,
                        'message' => 'Stripe payment failed: ' . $e->getMessage(),
                    ], 502);
                }
            }

            $baseMessage = $paymentMethod === 'stripe'
                ? 'Order created. Complete payment via Stripe.'
                : 'Order created successfully!';

            return $this->json(array_merge([
                'success' => true,
                'message' => $baseMessage,
                'orderId' => $commande->getId(),
                'total' => $total,
                'flashMessage' => 'Order #' . $commande->getId() . ' created successfully for ' . $total . ' TND!'
            ], array_filter($responseExtras, static fn ($value) => $value !== null)));
        } catch (\Exception $e) {
            return $this->json(['success' => false, 'message' => $e->getMessage()], 500);
        }
    }

    #[Route('/uploads/images/{filename}', name: 'app_uploads_images')]
    public function serveImage(string $filename): Response
    {
        $projectDir = $this->getParameter('kernel.project_dir');
        $imagePath = $projectDir . '/public/uploads/images/' . $filename;
        
        if (!file_exists($imagePath)) {
            throw $this->createNotFoundException('Image not found: ' . $filename);
        }
        
        $mimeType = mime_content_type($imagePath);
        if (!$mimeType) {
            $mimeType = 'application/octet-stream';
        }
        
        $response = new BinaryFileResponse($imagePath);
        $response->headers->set('Content-Type', $mimeType);
        $response->headers->set('Content-Disposition', 'inline; filename="' . $filename . '"');
        
        return $response;
    }
}
