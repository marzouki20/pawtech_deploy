<?php

namespace App\Controller;

use App\Entity\LigneCommande;
use App\Form\LigneCommandeType;
use App\Repository\LigneCommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard/eshop/lignes-commande')]
class LigneCommandeController extends AbstractController
{
    #[Route('', name: 'app_eshop_ligne_commande_index', methods: ['GET'])]
    public function index(LigneCommandeRepository $repository): Response
    {
        $lignes = $repository->findBy([], ['id' => 'DESC']);

        $columns = ['ID', 'Product', 'Order', 'Quantity', 'Unit price', 'Subtotal', 'Actions'];
        $rows = [];
        foreach ($lignes as $l) {
            $rows[] = [
                $l->getId(),
                $l->getProduit()?->getNom() ?? '-',
                '#' . ($l->getCommande()?->getId() ?? '-'),
                $l->getQuantite(),
                number_format((float) $l->getPrixUnitaire(), 2, ',', ' ') . ' TND',
                number_format($l->calculerSousTotal(), 2, ',', ' ') . ' TND',
                $this->renderView('ligne_commande/_actions.html.twig', ['ligne' => $l]),
            ];
        }
        return $this->render('ligne_commande/index.html.twig', [
            'active' => 'eshop_lignes',
            'page_title' => 'Eshop - Order lines',
            'entity_name' => 'Order line',
            'columns' => $columns,
            'rows' => $rows,
            'lignes_commande' => $lignes,
            'total_records' => count($lignes),
            'per_page' => 50,
            'page' => 1,
            'total_pages' => 1,
        ]);
    }

    #[Route('/new', name: 'app_eshop_ligne_commande_new', methods: ['GET','POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $ligne = new LigneCommande();
        $form = $this->createForm(LigneCommandeType::class, $ligne);
        $form->handleRequest($request);

        // Pour AJAX/Modal
        if ($request->isXmlHttpRequest()) {
            if ($form->isSubmitted()) {
                if ($form->isValid()) {
                    try {
                        $em->persist($ligne);
                        $em->flush();
                        
                        return $this->json([
                            'success' => true, 
                            'message' => 'Order line created successfully!',
                            'redirect' => $this->generateUrl('app_eshop_ligne_commande_index')
                        ]);
                    } catch (\Exception $e) {
                        return $this->json([
                            'success' => false, 
                            'message' => 'Error saving order line: ' . $e->getMessage()
                        ], 500);
                    }
                } else {
                    // Retourner le formulaire avec les erreurs Symfony
                    return $this->render('ligne_commande/_form_content.html.twig', [
                        'page_title' => 'New order line',
                        'ligne' => $ligne,
                        'form' => $form->createView(),
                        'form_action' => $this->generateUrl('app_eshop_ligne_commande_new'),
                        'submit_label' => 'Add',
                    ]);
                }
            } else {
                // GET request pour la modal
                return $this->render('ligne_commande/_form_content.html.twig', [
                    'page_title' => 'New order line',
                    'ligne' => $ligne,
                    'form' => $form->createView(),
                    'form_action' => $this->generateUrl('app_eshop_ligne_commande_new'),
                    'submit_label' => 'Add',
                ]);
            }
        }

        // Pour les requêtes normales (non-AJAX)
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $em->persist($ligne);
                $em->flush();
                $this->addFlash('success', 'Order line created successfully.');
                return $this->redirectToRoute('app_eshop_ligne_commande_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error creating order line: ' . $e->getMessage());
            }
        }

        return $this->render('ligne_commande/form.html.twig', [
            'active' => 'eshop_lignes_commande',
            'page_title' => 'New order line',
            'ligne' => $ligne,
            'form' => $form->createView(),
            'submit_label' => 'Add',
        ]);
    }

    #[Route('/{id}', name: 'app_eshop_ligne_commande_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Request $request, LigneCommande $ligne): Response
    {
        if ($request->isXmlHttpRequest()) {
            return $this->render('ligne_commande/_show_content.html.twig', [
                'page_title' => 'Order line #' . $ligne->getId(),
                'ligne' => $ligne,
            ]);
        }

        return $this->render('ligne_commande/show.html.twig', [
            'active' => 'eshop_lignes',
            'page_title' => 'Order line #' . $ligne->getId(),
            'ligne' => $ligne,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_eshop_ligne_commande_edit', methods: ['GET','POST'])]
    public function edit(Request $request, LigneCommande $ligneCommande, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(LigneCommandeType::class, $ligneCommande);
        $form->handleRequest($request);

        // Pour AJAX/Modal
        if ($request->isXmlHttpRequest()) {
            if ($form->isSubmitted()) {
                if ($form->isValid()) {
                    try {
                        $em->flush();
                        return $this->json([
                            'success' => true, 
                            'message' => 'Order line updated successfully.', 
                            'redirect' => $this->generateUrl('app_eshop_ligne_commande_index')
                        ]);
                    } catch (\Exception $e) {
                        return $this->json([
                            'success' => false, 
                            'message' => 'Error: ' . $e->getMessage()
                        ], 500);
                    }
                } else {
                    // Retourner le formulaire avec les erreurs Symfony
                    return $this->render('ligne_commande/_form_content.html.twig', [
                        'page_title' => 'Modify order line',
                        'ligne' => $ligneCommande,
                        'form' => $form->createView(),
                        'form_action' => $this->generateUrl('app_eshop_ligne_commande_edit', ['id' => $ligneCommande->getId()]),
                        'submit_label' => 'Save',
                    ]);
                }
            } else {
                // GET request pour la modal
                return $this->render('ligne_commande/_form_content.html.twig', [
                    'page_title' => 'Modify order line',
                    'ligne' => $ligneCommande,
                    'form' => $form->createView(),
                    'form_action' => $this->generateUrl('app_eshop_ligne_commande_edit', ['id' => $ligneCommande->getId()]),
                    'submit_label' => 'Save',
                ]);
            }
        }

        // Pour les requêtes normales (non-AJAX)
        if ($form->isSubmitted() && $form->isValid()) {
            try {
                $em->flush();
                $this->addFlash('success', 'Order line updated successfully.');
                return $this->redirectToRoute('app_eshop_ligne_commande_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error updating order line: ' . $e->getMessage());
            }
        }

        return $this->render('ligne_commande/form.html.twig', [
            'active' => 'eshop_lignes_commande',
            'page_title' => 'Modify order line',
            'ligne' => $ligneCommande,
            'form' => $form->createView(),
            'submit_label' => 'Save',
        ]);
    }

    #[Route('/{id}/delete', name: 'app_eshop_ligne_commande_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, LigneCommande $ligne, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $ligne->getId(), (string) $request->request->get('_token'))) {
            try {
                $em->remove($ligne);
                $em->flush();
                $this->addFlash('success', 'Order line deleted successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error deleting order line: ' . $e->getMessage());
            }
        }
        return $this->redirectToRoute('app_eshop_ligne_commande_index');
    }
}