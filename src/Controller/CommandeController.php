<?php

namespace App\Controller;

use App\Entity\Commande;
use App\Form\CommandeType;
use App\Repository\CommandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard/eshop/commandes')]
class CommandeController extends AbstractController
{
    #[Route('', name: 'app_eshop_commande_index', methods: ['GET'])]
    public function index(Request $request, CommandeRepository $repository): Response
    {
        $search = $request->query->get('search', '');
        $status_filter = $request->query->get('status', '');
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 20;

        $qb = $repository->createQueryBuilder('c');

        if ($search) {
            $search = trim($search);
            if (is_numeric($search)) {
                $expr = 'c.total = :total';
                if (ctype_digit($search)) {
                    $expr = '(' . $expr . ' OR c.id = :id)';
                }
                $qb->andWhere($expr)
                   ->setParameter('total', (float) $search);
                if (ctype_digit($search)) {
                    $qb->setParameter('id', (int) $search);
                }
            } else {
                $date = \DateTime::createFromFormat('d/m/Y', $search);
                if ($date !== false) {
                    $qb->andWhere('c.date = :date')
                       ->setParameter('date', $date);
                } else {
                    $qb->andWhere('1 = 0');
                }
            }
        }

        if ($status_filter && $status_filter !== 'all') {
            if ($status_filter === 'completed') {
                $qb->andWhere('c.statut = true');
            } elseif ($status_filter === 'in_progress') {
                $qb->andWhere('c.statut = false OR c.statut IS NULL');
            }
        }

        $qb->orderBy('c.date', 'DESC')
           ->addOrderBy('c.id', 'DESC');

        $totalQuery = clone $qb;
        $total = $totalQuery->select('COUNT(c.id)')->getQuery()->getSingleScalarResult();
        
        $offset = ($page - 1) * $perPage;
        $qb->setFirstResult($offset)
           ->setMaxResults($perPage);
        
        $commandes = $qb->getQuery()->getResult();
        $totalPages = ceil($total / $perPage);

        $columns = ['ID', 'Date', 'Total', 'Status', 'Actions'];
        $rows = [];
        foreach ($commandes as $c) {
            $rows[] = [
                $c->getId(),
                $c->getDate()?->format('d/m/Y') ?? '-',
                number_format((float) $c->getTotal(), 2, ',', ' ') . ' TND',
                $c->isStatut() ? 'Completed' : 'in progress',
                $this->renderView('commande/_actions.html.twig', ['commande' => $c]),
            ];
        }
        
        return $this->render('commande/index.html.twig', [
            'active' => 'eshop_commandes',
            'page_title' => 'Eshop - Orders',
            'entity_name' => 'Order',
            'columns' => $columns,
            'rows' => $rows,
            'commandes' => $commandes,
            'total_records' => $total,
            'per_page' => $perPage,
            'page' => $page,
            'total_pages' => $totalPages,
            'search' => $search,
            'status_filter' => $status_filter,
        ]);
    }

    #[Route('/new', name: 'app_eshop_commande_new', methods: ['GET','POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $commande = new Commande();
        $form = $this->createForm(CommandeType::class, $commande);
        $form->handleRequest($request);

        // Pour AJAX/Modal
        if ($request->isXmlHttpRequest()) {
            if ($form->isSubmitted()) {
                // Récupérer les données brutes pour validation
                $formData = $request->request->all('commande');
                $dateData = $formData['date'] ?? null;
                $totalData = $formData['total'] ?? null;
                
                // Validation manuelle pour la date
                if (empty($dateData)) {
                    $form->get('date')->addError(new \Symfony\Component\Form\FormError('Date is required'));
                } else {
                    $date = \DateTime::createFromFormat('d/m/Y', $dateData);
                    if (!$date) {
                        $form->get('date')->addError(new \Symfony\Component\Form\FormError('Invalid date format. Please use dd/mm/yyyy'));
                    } elseif ($date > new \DateTime()) {
                        $form->get('date')->addError(new \Symfony\Component\Form\FormError('Date cannot be in the future'));
                    } else {
                        $commande->setDate($date);
                    }
                }
                
                // Validation manuelle pour le total
                if (empty($totalData)) {
                    $form->get('total')->addError(new \Symfony\Component\Form\FormError('Total is required'));
                } else {
                    $total = filter_var($totalData, FILTER_VALIDATE_FLOAT);
                    if ($total === false) {
                        $form->get('total')->addError(new \Symfony\Component\Form\FormError('Total must be a number'));
                    } elseif ($total < 0) {
                        $form->get('total')->addError(new \Symfony\Component\Form\FormError('Total must be positive or zero'));
                    } else {
                        $commande->setTotal($total);
                    }
                }
                
                if ($form->isValid()) {
                    try {
                        $em->persist($commande);
                        $em->flush();
                        
                        return $this->json([
                            'success' => true, 
                            'message' => 'Order created successfully.', 
                            'redirect' => $this->generateUrl('app_eshop_commande_index')
                        ]);
                    } catch (\Exception $e) {
                        return $this->json([
                            'success' => false, 
                            'message' => 'Error: ' . $e->getMessage()
                        ], 500);
                    }
                } else {
                    // Retourner le formulaire avec les erreurs
                    return $this->render('commande/_form_content.html.twig', [
                        'page_title' => 'New order',
                        'commande' => $commande,
                        'form' => $form->createView(),
                        'form_action' => $this->generateUrl('app_eshop_commande_new'),
                        'submit_label' => 'Add',
                    ]);
                }
            } else {
                // GET request pour la modal
                return $this->render('commande/_form_content.html.twig', [
                    'page_title' => 'New order',
                    'commande' => $commande,
                    'form' => $form->createView(),
                    'form_action' => $this->generateUrl('app_eshop_commande_new'),
                    'submit_label' => 'Add',
                ]);
            }
        }

        // Pour les requêtes normales (non-AJAX)
        if ($form->isSubmitted()) {
            // Récupérer les données brutes pour validation
            $formData = $request->request->all('commande');
            $dateData = $formData['date'] ?? null;
            $totalData = $formData['total'] ?? null;
            
            // Validation manuelle pour la date
            if (empty($dateData)) {
                $form->get('date')->addError(new \Symfony\Component\Form\FormError('Date is required'));
            } else {
                $date = \DateTime::createFromFormat('d/m/Y', $dateData);
                if (!$date) {
                    $form->get('date')->addError(new \Symfony\Component\Form\FormError('Invalid date format. Please use dd/mm/yyyy'));
                } elseif ($date > new \DateTime()) {
                    $form->get('date')->addError(new \Symfony\Component\Form\FormError('Date cannot be in the future'));
                } else {
                    $commande->setDate($date);
                }
            }
            
            // Validation manuelle pour le total
            if (empty($totalData)) {
                $form->get('total')->addError(new \Symfony\Component\Form\FormError('Total is required'));
            } else {
                $total = filter_var($totalData, FILTER_VALIDATE_FLOAT);
                if ($total === false) {
                    $form->get('total')->addError(new \Symfony\Component\Form\FormError('Total must be a number'));
                } elseif ($total < 0) {
                    $form->get('total')->addError(new \Symfony\Component\Form\FormError('Total must be positive or zero'));
                } else {
                    $commande->setTotal($total);
                }
            }
            
            if ($form->isValid()) {
                try {
                    $em->persist($commande);
                    $em->flush();
                    $this->addFlash('success', 'Order created successfully.');
                    return $this->redirectToRoute('app_eshop_commande_index');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Error creating order: ' . $e->getMessage());
                }
            }
        }

        return $this->render('commande/form.html.twig', [
            'active' => 'eshop_commandes',
            'page_title' => 'New order',
            'commande' => $commande,
            'form' => $form->createView(),
            'submit_label' => 'Add',
        ]);
    }

    #[Route('/{id}', name: 'app_eshop_commande_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Request $request, Commande $commande): Response
    {
        if ($request->isXmlHttpRequest()) {
            return $this->render('commande/_show_content.html.twig', [
                'page_title' => 'Order #' . $commande->getId(),
                'commande' => $commande,
            ]);
        }

        return $this->render('commande/show.html.twig', [
            'active' => 'eshop_commandes',
            'page_title' => 'Order #' . $commande->getId(),
            'commande' => $commande,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_eshop_commande_edit', methods: ['GET','POST'])]
    public function edit(Request $request, Commande $commande, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(CommandeType::class, $commande);
        $form->handleRequest($request);

        // Pour AJAX/Modal
        if ($request->isXmlHttpRequest()) {
            if ($form->isSubmitted()) {
                // Récupérer les données brutes pour validation
                $formData = $request->request->all('commande');
                $dateData = $formData['date'] ?? null;
                $totalData = $formData['total'] ?? null;
                
                // Validation manuelle pour la date
                if (empty($dateData)) {
                    $form->get('date')->addError(new \Symfony\Component\Form\FormError('Date is required'));
                } else {
                    $date = \DateTime::createFromFormat('d/m/Y', $dateData);
                    if (!$date) {
                        $form->get('date')->addError(new \Symfony\Component\Form\FormError('Invalid date format. Please use dd/mm/yyyy'));
                    } elseif ($date > new \DateTime()) {
                        $form->get('date')->addError(new \Symfony\Component\Form\FormError('Date cannot be in the future'));
                    } else {
                        $commande->setDate($date);
                    }
                }
                
                // Validation manuelle pour le total
                if (empty($totalData)) {
                    $form->get('total')->addError(new \Symfony\Component\Form\FormError('Total is required'));
                } else {
                    $total = filter_var($totalData, FILTER_VALIDATE_FLOAT);
                    if ($total === false) {
                        $form->get('total')->addError(new \Symfony\Component\Form\FormError('Total must be a number'));
                    } elseif ($total < 0) {
                        $form->get('total')->addError(new \Symfony\Component\Form\FormError('Total must be positive or zero'));
                    } else {
                        $commande->setTotal($total);
                    }
                }
                
                if ($form->isValid()) {
                    try {
                        $em->flush();
                        return $this->json([
                            'success' => true, 
                            'message' => 'Order updated successfully.', 
                            'redirect' => $this->generateUrl('app_eshop_commande_index')
                        ]);
                    } catch (\Exception $e) {
                        return $this->json([
                            'success' => false, 
                            'message' => 'Error: ' . $e->getMessage()
                        ], 500);
                    }
                } else {
                    // Retourner le formulaire avec les erreurs
                    return $this->render('commande/_form_content.html.twig', [
                        'page_title' => 'Modify order',
                        'commande' => $commande,
                        'form' => $form->createView(),
                        'form_action' => $this->generateUrl('app_eshop_commande_edit', ['id' => $commande->getId()]),
                        'submit_label' => 'Save',
                    ]);
                }
            } else {
                // GET request pour la modal
                return $this->render('commande/_form_content.html.twig', [
                    'page_title' => 'Modify order',
                    'commande' => $commande,
                    'form' => $form->createView(),
                    'form_action' => $this->generateUrl('app_eshop_commande_edit', ['id' => $commande->getId()]),
                        'submit_label' => 'Save',
                ]);
            }
        }

        // Pour les requêtes normales (non-AJAX)
        if ($form->isSubmitted()) {
            // Récupérer les données brutes pour validation
            $formData = $request->request->all('commande');
            $dateData = $formData['date'] ?? null;
            $totalData = $formData['total'] ?? null;
            
            // Validation manuelle pour la date
            if (empty($dateData)) {
                $form->get('date')->addError(new \Symfony\Component\Form\FormError('Date is required'));
            } else {
                $date = \DateTime::createFromFormat('d/m/Y', $dateData);
                if (!$date) {
                    $form->get('date')->addError(new \Symfony\Component\Form\FormError('Invalid date format. Please use dd/mm/yyyy'));
                } elseif ($date > new \DateTime()) {
                    $form->get('date')->addError(new \Symfony\Component\Form\FormError('Date cannot be in the future'));
                } else {
                    $commande->setDate($date);
                }
            }
            
            // Validation manuelle pour le total
            if (empty($totalData)) {
                $form->get('total')->addError(new \Symfony\Component\Form\FormError('Total is required'));
            } else {
                $total = filter_var($totalData, FILTER_VALIDATE_FLOAT);
                if ($total === false) {
                    $form->get('total')->addError(new \Symfony\Component\Form\FormError('Total must be a number'));
                } elseif ($total < 0) {
                    $form->get('total')->addError(new \Symfony\Component\Form\FormError('Total must be positive or zero'));
                } else {
                    $commande->setTotal($total);
                }
            }
            
            if ($form->isValid()) {
                try {
                    $em->flush();
                    $this->addFlash('success', 'Order updated successfully.');
                    return $this->redirectToRoute('app_eshop_commande_index');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Error updating order: ' . $e->getMessage());
                }
            }
        }

        return $this->render('commande/form.html.twig', [
            'active' => 'eshop_commandes',
            'page_title' => 'Modify order',
            'commande' => $commande,
            'form' => $form->createView(),
            'submit_label' => 'Save',
        ]);
    }

    #[Route('/{id}/delete', name: 'app_eshop_commande_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Commande $commande, EntityManagerInterface $em): Response
    {
        if ($this->isCsrfTokenValid('delete' . $commande->getId(), (string) $request->request->get('_token'))) {
            try {
                $em->remove($commande);
                $em->flush();
                $this->addFlash('success', 'Order deleted successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error deleting order: ' . $e->getMessage());
            }
        }
        return $this->redirectToRoute('app_eshop_commande_index');
    }
}
