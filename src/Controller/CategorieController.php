<?php

namespace App\Controller;

use App\Entity\Categorie;
use App\Form\CategorieType;
use App\Repository\CategorieRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard/eshop/categories')]
class CategorieController extends AbstractController
{
    #[Route('', name: 'app_eshop_categorie_index', methods: ['GET'])]
    public function index(Request $request, CategorieRepository $repository): Response
    {
        // Récupérer les paramètres de recherche et filtres
        $search = $request->query->get('search');
        $productCountFilter = $request->query->get('product_count');
        $page = $request->query->getInt('page', 1);
        $perPage = $request->query->getInt('per_page', 50);
        
        // Récupérer les catégories selon les filtres
        if ($search) {
            // Recherche par nom
            $categories = $repository->searchCategories($search);
        } else {
            // Toutes les catégories
            $categories = $repository->findAllOrdered();
        }
        
        // Appliquer le filtre par nombre de produits si spécifié
        if ($productCountFilter) {
            $categories = array_filter($categories, function($categorie) use ($productCountFilter) {
                $productCount = $categorie->getProduits()->count();
                
                switch ($productCountFilter) {
                    case 'empty':
                        return $productCount === 0;
                    case '1-5':
                        return $productCount >= 1 && $productCount <= 5;
                    case '6+':
                        return $productCount >= 6;
                    default:
                        return true;
                }
            });
            
            // Réindexer le tableau après le filtrage
            $categories = array_values($categories);
        }
        
        // Calculer la pagination
        $total_records = count($categories);
        $total_pages = ceil($total_records / $perPage);
        
        // Limiter le numéro de page aux limites
        if ($page < 1) {
            $page = 1;
        } elseif ($page > $total_pages && $total_pages > 0) {
            $page = $total_pages;
        }
        
        $offset = ($page - 1) * $perPage;
        
        // Paginer les résultats
        $paginatedCategories = array_slice($categories, $offset, $perPage);
        
        // Préparer les données pour le tableau
        $columns = ['ID', 'Name', 'Products Count', 'Actions'];
        $rows = [];
        
        foreach ($paginatedCategories as $categorie) {
            $productCount = $categorie->getProduits()->count();
            
            // Créer les actions en HTML
            $actionsHtml = $this->renderView('categorie/_actions.html.twig', [
                'categorie' => $categorie
            ]);
            
            $rows[] = [
                $categorie->getId(), // ID - pas de HTML
                $categorie->getNom(), // Nom - pas de HTML
                $productCount, // Product Count - juste le nombre, pas de HTML
                $actionsHtml, // Actions - HTML
            ];
        }

        return $this->render('categorie/index.html.twig', [
            'active' => 'eshop_categories',
            'page_title' => 'Eshop - Categories',
            'entity_name' => 'Category',
            'columns' => $columns,
            'rows' => $rows,
            'categories' => $paginatedCategories,
            'total_records' => $total_records,
            'per_page' => $perPage,
            'page' => $page,
            'total_pages' => $total_pages,
            'search' => $search,
            'product_count_filter' => $productCountFilter,
        ]);
    }

    #[Route('/new', name: 'app_eshop_categorie_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $categorie = new Categorie();
        $form = $this->createForm(CategorieType::class, $categorie);
        $form->handleRequest($request);
        $isAjax = $request->isXmlHttpRequest();

        // Ajax GET -> fragment
        if ($isAjax && !$form->isSubmitted()) {
            return $this->render('categorie/_form_content.html.twig', [
                'page_title' => 'New Category',
                'categorie' => $categorie,
                'form' => $form->createView(),
                'submit_label' => 'Add',
                'form_action' => $this->generateUrl('app_eshop_categorie_new'),
            ]);
        }

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $em->persist($categorie);
                    $em->flush();
                } catch (\Exception $e) {
                    if ($isAjax) {
                        return $this->json([
                            'success' => false,
                            'message' => 'Error: ' . $e->getMessage()
                        ], 500);
                    }
                    
                    $this->addFlash('error', 'Error creating category: ' . $e->getMessage());
                }

                if ($isAjax) {
                    return $this->json([
                        'success' => true, 
                        'message' => 'Category "' . $categorie->getNom() . '" created successfully!',
                        'redirect' => $this->generateUrl('app_eshop_categorie_index')
                    ]);
                }
                
                $this->addFlash('success', 'Category "' . $categorie->getNom() . '" created successfully!');
                return $this->redirectToRoute('app_eshop_categorie_index');
            } else {
                if ($isAjax) {
                    return $this->render('categorie/_form_content.html.twig', [
                        'page_title' => 'New Category',
                        'categorie' => $categorie,
                        'form' => $form->createView(),
                        'submit_label' => 'Add',
                        'form_action' => $this->generateUrl('app_eshop_categorie_new'),
                    ]);
                }
                
                return $this->render('categorie/form.html.twig', [
                    'active' => 'eshop_categories',
                    'page_title' => 'New Category',
                    'categorie' => $categorie,
                    'form' => $form->createView(),
                    'submit_label' => 'Add',
                ]);
            }
        }

        return $this->render('categorie/form.html.twig', [
            'active' => 'eshop_categories',
            'page_title' => 'New Category',
            'categorie' => $categorie,
            'form' => $form->createView(),
            'submit_label' => 'Add',
        ]);
    }

    #[Route('/{id}', name: 'app_eshop_categorie_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Request $request, Categorie $categorie): Response
    {
        if ($request->isXmlHttpRequest()) {
            return $this->render('categorie/_show_content.html.twig', [
                'page_title' => 'Category: ' . $categorie->getNom(),
                'categorie' => $categorie,
            ]);
        }

        return $this->render('categorie/show.html.twig', [
            'active' => 'eshop_categories',
            'page_title' => 'Category: ' . $categorie->getNom(),
            'categorie' => $categorie,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_eshop_categorie_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, Categorie $categorie, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CategorieType::class, $categorie);
        $form->handleRequest($request);
        $isAjax = $request->isXmlHttpRequest();

        if ($isAjax && !$form->isSubmitted()) {
            return $this->render('categorie/_form_content.html.twig', [
                'page_title' => 'Modify Category',
                'categorie' => $categorie,
                'form' => $form->createView(),
                'submit_label' => 'Modify',
                'form_action' => $this->generateUrl('app_eshop_categorie_edit', ['id' => $categorie->getId()]),
            ]);
        }

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $em->flush();
                } catch (\Exception $e) {
                    if ($isAjax) {
                        return $this->json([
                            'success' => false,
                            'message' => 'Error: ' . $e->getMessage()
                        ], 500);
                    }
                    
                    $this->addFlash('error', 'Error updating category: ' . $e->getMessage());
                }

                if ($isAjax) {
                    return $this->json([
                        'success' => true, 
                        'message' => 'Category "' . $categorie->getNom() . '" updated successfully!',
                        'redirect' => $this->generateUrl('app_eshop_categorie_index')
                    ]);
                }
                
                $this->addFlash('success', 'Category "' . $categorie->getNom() . '" updated successfully!');
                return $this->redirectToRoute('app_eshop_categorie_index');
            } else {
                if ($isAjax) {
                    return $this->render('categorie/_form_content.html.twig', [
                        'page_title' => 'Modify Category',
                        'categorie' => $categorie,
                        'form' => $form->createView(),
                        'submit_label' => 'Modify',
                        'form_action' => $this->generateUrl('app_eshop_categorie_edit', ['id' => $categorie->getId()]),
                    ]);
                }
                
                return $this->render('categorie/form.html.twig', [
                    'active' => 'eshop_categories',
                    'page_title' => 'Modify Category: ' . $categorie->getNom(),
                    'categorie' => $categorie,
                    'form' => $form->createView(),
                    'submit_label' => 'Modify',
                ]);
            }
        }

        return $this->render('categorie/form.html.twig', [
            'active' => 'eshop_categories',
            'page_title' => 'Modify Category: ' . $categorie->getNom(),
            'categorie' => $categorie,
            'form' => $form->createView(),
            'submit_label' => 'Modify',
        ]);
    }

    #[Route('/{id}/delete', name: 'app_eshop_categorie_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Categorie $categorie, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $categorie->getId(), (string) $request->request->get('_token'))) {
            $categoryName = $categorie->getNom();
            
            try {
                $em->remove($categorie);
                $em->flush();
                
                $this->addFlash('success', 'Category "' . $categoryName . '" deleted successfully!');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error deleting category: ' . $e->getMessage());
            }
        } else {
            $this->addFlash('error', 'Invalid CSRF token.');
        }

        return $this->redirectToRoute('app_eshop_categorie_index');
    }
}
