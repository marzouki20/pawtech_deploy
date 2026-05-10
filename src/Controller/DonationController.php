<?php

namespace App\Controller;

use App\Entity\Donation;
use App\Form\DonationType;
use App\Repository\DonationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/dashboard/donations')]
class DonationController extends AbstractController
{
    public function __construct(
        private readonly DonationRepository $repository,
        private readonly EntityManagerInterface $em
    ) {
    }
    
    #[Route('', name: 'app_donation_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $search = $request->query->get('search', '');
        $statusFilter = $request->query->get('status', '');
        $page = (int) $request->query->get('page', 1);
        $perPage = 20;
        
        // Construire la requête en fonction des filtres
        $queryBuilder = $this->repository->createQueryBuilder('d');
        
        if ($search) {
            $queryBuilder->andWhere('LOWER(d.donateur) LIKE :search OR LOWER(d.email) LIKE :search OR LOWER(d.reference) LIKE :search')
                ->setParameter('search', '%' . strtolower($search) . '%');
        }
        
        if ($statusFilter === 'validated') {
            $queryBuilder->andWhere('d.statut = :status')
                ->setParameter('status', true);
        } elseif ($statusFilter === 'pending') {
            $queryBuilder->andWhere('d.statut = :status')
                ->setParameter('status', false);
        }
        
        // Compter le total
        $totalQueryBuilder = clone $queryBuilder;
        $totalRecords = $totalQueryBuilder->select('COUNT(d.id)')->getQuery()->getSingleScalarResult();
        
        // Pagination
        $totalPages = ceil($totalRecords / $perPage);
        $page = max(1, min($page, $totalPages));
        
        // Récupérer les résultats paginés
        $offset = ($page - 1) * $perPage;
        $donations = $queryBuilder
            ->orderBy('d.date', 'DESC')
            ->addOrderBy('d.id', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
        
        $columns = ['ID', 'Amount', 'Date', 'Donor', 'Email', 'Reference', 'Status', 'Actions'];
        $rows = [];
        foreach ($donations as $d) {
            $rows[] = [
                $d->getId(),
                number_format((float) $d->getMontant(), 2, ',', ' ') . ' TND',
                $d->getDate()?->format('d/m/Y') ?? '-',
                $d->getDonateur() ?? '-',
                $d->getEmail() ?? '-',
                $d->getReference() ?? '-',
                $d->getStatut(),
                $this->renderView('donation/_actions.html.twig', ['donation' => $d]),
            ];
        }
        
        return $this->render('donation/index.html.twig', [
            'active' => 'donations',
            'page_title' => 'Donations',
            'entity_name' => 'Donations',
            'columns' => $columns,
            'rows' => $rows,
            'donations' => $donations,
            'search' => $search,
            'status_filter' => $statusFilter,
            'total_records' => $totalRecords,
            'per_page' => $perPage,
            'page' => $page,
            'total_pages' => $totalPages,
        ]);
    }

    #[Route('/new', name: 'app_donation_new', methods: ['GET','POST'])]
    public function new(Request $request): Response
    {
        $donation = new Donation();
        // Set default date if not provided
        if (!$donation->getDate()) {
            $donation->setDate(new \DateTime());
        }
        $form = $this->createForm(DonationType::class, $donation);
        $form->handleRequest($request);

        // Pour AJAX/Modal
        if ($request->isXmlHttpRequest()) {
            if ($form->isSubmitted()) {
                // Validation personnalisée pour l'email optionnel
                $email = $form->get('email')->getData();
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $form->get('email')->addError(new \Symfony\Component\Form\FormError('Email is not valid'));
                }
                
                if ($form->isValid()) {
                    try {
                        if ($donation->getStatut() === null) {
                            $donation->setStatut(false);
                        }
                        
                        $this->em->persist($donation);
                        $this->em->flush();
                        
                        return $this->json([
                            'success' => true,
                            'message' => 'Donation created successfully!',
                            'redirect' => $this->generateUrl('app_donation_index')
                        ]);
                    } catch (\Exception $e) {
                        return $this->json([
                            'success' => false,
                            'message' => 'Error saving donation: ' . $e->getMessage()
                        ], 500);
                    }
                } else {
                    return $this->render('donation/_form_content.html.twig', [
                        'page_title' => 'New donation',
                        'donation' => $donation,
                        'form' => $form->createView(),
                        'form_action' => $this->generateUrl('app_donation_new'),
                        'submit_label' => 'Add',
                    ]);
                }
            } else {
                return $this->render('donation/_form_content.html.twig', [
                    'page_title' => 'New donation',
                    'donation' => $donation,
                    'form' => $form->createView(),
                    'form_action' => $this->generateUrl('app_donation_new'),
                    'submit_label' => 'Add',
                ]);
            }
        }

        if ($form->isSubmitted() && $form->isValid()) {
            try {
                if ($donation->getStatut() === null) {
                    $donation->setStatut(false);
                }
                
                $this->em->persist($donation);
                $this->em->flush();
                $this->addFlash('success', 'Donation created successfully.');
                return $this->redirectToRoute('app_donation_index');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error creating donation: ' . $e->getMessage());
            }
        }

        return $this->render('donation/form.html.twig', [
            'active' => 'donations',
            'page_title' => 'New donation',
            'donation' => $donation,
            'form' => $form->createView(),
            'submit_label' => 'Add',
        ]);
    }

    #[Route('/{id}', name: 'app_donation_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function show(Request $request, Donation $donation): Response
    {
        if ($request->isXmlHttpRequest()) {
            return $this->render('donation/_show_content.html.twig', [
                'page_title' => 'Donation #' . $donation->getId(),
                'donation' => $donation,
            ]);
        }

        return $this->render('donation/show.html.twig', [
            'active' => 'donations',
            'page_title' => 'Donation #' . $donation->getId(),
            'donation' => $donation,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_donation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Donation $donation): Response
    {
        $form = $this->createForm(DonationType::class, $donation);
        $form->handleRequest($request);

        if ($request->isXmlHttpRequest()) {
            if ($form->isSubmitted()) {
                $email = $form->get('email')->getData();
                if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    $form->get('email')->addError(new \Symfony\Component\Form\FormError('Email is not valid'));
                }
                
                if ($form->isValid()) {
                    try {
                        $this->em->flush();
                        return $this->json([
                            'success' => true, 
                            'message' => 'Donation updated successfully.', 
                            'redirect' => $this->generateUrl('app_donation_index')
                        ]);
                    } catch (\Exception $e) {
                        return $this->json([
                            'success' => false, 
                            'message' => 'Error: ' . $e->getMessage()
                        ], 500);
                    }
                } else {
                    return $this->render('donation/_form_content.html.twig', [
                        'page_title' => 'Modify donation',
                        'donation' => $donation,
                        'form' => $form->createView(),
                        'form_action' => $this->generateUrl('app_donation_edit', ['id' => $donation->getId()]),
                        'submit_label' => 'Save',
                    ]);
                }
            } else {
                return $this->render('donation/_form_content.html.twig', [
                    'page_title' => 'Modify donation',
                    'donation' => $donation,
                    'form' => $form->createView(),
                    'form_action' => $this->generateUrl('app_donation_edit', ['id' => $donation->getId()]),
                    'submit_label' => 'Save',
                ]);
            }
        }

        if ($form->isSubmitted()) {
            $email = $form->get('email')->getData();
            if (!empty($email) && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $form->get('email')->addError(new \Symfony\Component\Form\FormError('Email is not valid'));
            }
            
            if ($form->isValid()) {
                try {
                    $this->em->flush();
                    $this->addFlash('success', 'Donation updated successfully.');
                    return $this->redirectToRoute('app_donation_index');
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Error updating donation: ' . $e->getMessage());
                }
            }
        }

        return $this->render('donation/form.html.twig', [
            'active' => 'donations',
            'page_title' => 'Modify donation',
            'donation' => $donation,
            'form' => $form->createView(),
            'submit_label' => 'Save',
        ]);
    }

    #[Route('/{id}/delete', name: 'app_donation_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, Donation $donation): Response
    {
        if ($this->isCsrfTokenValid('delete' . $donation->getId(), (string) $request->request->get('_token'))) {
            try {
                $this->em->remove($donation);
                $this->em->flush();
                $this->addFlash('success', 'Donation deleted successfully.');
            } catch (\Exception $e) {
                $this->addFlash('error', 'Error deleting donation: ' . $e->getMessage());
            }
        }
        return $this->redirectToRoute('app_donation_index');
    }
}
