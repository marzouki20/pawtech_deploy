<?php

namespace App\Controller;

use App\Entity\Consultation;
use App\Form\ConsultationType;
use App\Repository\ConsultationRepository;
use App\Repository\UserRepository;
use App\Service\TwilioNotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/consultation')]
class ConsultationController extends AbstractController
{
    #[Route('/', name: 'app_consultation_index', methods: ['GET'])]
    public function index(ConsultationRepository $repo): Response
    {
        return $this->render('consultation/index.html.twig', [
            'consultations' => $repo->findAll(),
            'active' => 'consultation',
            'page_title' => 'Consultations'
        ]);
    }


    #[Route('/veterinaire', name: 'app_veterinaire_index', methods: ['GET'])]
    public function indexfront(): Response
    {
            return $this->render('pages/veterinarian.html.twig');
    }



    #[Route('/appointment/new', name: 'app_frontveterinaire_new', methods: ['GET', 'POST'])]
    public function newFront(Request $request, EntityManagerInterface $em, TwilioNotificationService $twilioNotificationService): Response
    {
        $consultation = new Consultation();
        $form = $this->createForm(ConsultationType::class, $consultation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($consultation);
            $em->flush();
            $this->notifyConsultationBySms($consultation, $twilioNotificationService);

            $this->addFlash('success', 'Appointment request sent successfully! We will contact you soon.');
            return $this->redirectToRoute('app_veterinaire_index');
        }

        // Render a dedicated template for the appointment form (no sidebar)
        return $this->render('pages/newcons.html.twig', [
            'form' => $form,
            'active' => 'consultation',
            'page_title' => 'Add Consultation'
        ]);
    }





    #[Route('/search', name: 'app_consultation_search', methods: ['GET'])]
    public function search(Request $request, ConsultationRepository $repo): JsonResponse
    {
        $searchTerm = $request->query->get('searchValue', '');
        
        if (empty($searchTerm)) {
            $consultations = $repo->findAll();
        } else {
            $consultations = $repo->search($searchTerm);
        }
        
        // Format personnalisÃ© pour la compatibilitÃ© avec le template
        $data = [];
        foreach ($consultations as $consultation) {
            $user = $consultation->getUser();
            $userLastName = $user ? $user->getNom() : '';
            $userFirstName = $user ? $user->getPrenom() : '';
            
            $data[] = [
                'id' => $consultation->getId(),
                'date' => $consultation->getDate()->format('Y-m-d H:i'),
                'type' => $consultation->getType(),
                'user_lastName' => $userLastName,
                'user_firstName' => $userFirstName,
                'diagnostic' => $consultation->getDiagnostic(),
                'traitement' => $consultation->getTraitement(),
            ];
        }
        
        return $this->json($data);
    }

    #[Route('/sort-by-date', name: 'app_consultation_sort_by_date', methods: ['GET'])]
    public function sortByDate(ConsultationRepository $repo): JsonResponse
    {
        $consultations = $repo->findAllOrdered();
        
        $data = [];
        foreach ($consultations as $consultation) {
            $user = $consultation->getUser();
            $userLastName = $user ? $user->getNom() : '';
            $userFirstName = $user ? $user->getPrenom() : '';
            
            $data[] = [
                'id' => $consultation->getId(),
                'date' => $consultation->getDate()->format('Y-m-d H:i'),
                'type' => $consultation->getType(),
                'user_lastName' => $userLastName,
                'user_firstName' => $userFirstName,
                'diagnostic' => $consultation->getDiagnostic(),
                'traitement' => $consultation->getTraitement(),
            ];
        }
        
        return $this->json($data);
    }

    #[Route('/new', name: 'app_consultation_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, TwilioNotificationService $twilioNotificationService): Response
    {
        $consultation = new Consultation();
        $form = $this->createForm(ConsultationType::class, $consultation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($consultation);
            $em->flush();
            $this->notifyConsultationBySms($consultation, $twilioNotificationService);

            $this->addFlash('success', 'Appointment request sent successfully! We will contact you soon.');
            return $this->redirectToRoute('app_consultation_index');
        }

        return $this->render('consultation/new.html.twig', [
            'form' => $form,
            'active' => 'consultation',
            'page_title' => 'Add Consultation'
        ]);
    }

    #[Route('/{id}/edit', name: 'app_consultation_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Consultation $consultation, EntityManagerInterface $em): Response
    {
        $form = $this->createForm(ConsultationType::class, $consultation);
        $form->handleRequest($request);

        // VÃ‰RIFICATION EXACTEMENT COMME DANS SUIVI
        if ($form->isSubmitted()) {
            // Validation manuelle pour s'assurer que les champs ne sont pas null aprÃ¨s validation
            $type = $form->get('type')->getData();
            $diagnostic = $form->get('diagnostic')->getData();
            $date = $form->get('date')->getData();
            $user = $form->get('user')->getData();
            $chien = $form->get('chien')->getData();
            
            if (empty($type)) {
                $form->get('type')->addError(
                    new \Symfony\Component\Form\FormError('Type is required')
                );
            }
            
            if (empty($diagnostic)) {
                $form->get('diagnostic')->addError(
                    new \Symfony\Component\Form\FormError('Diagnostic is required')
                );
            }
            
            if (empty($date)) {
                $form->get('date')->addError(
                    new \Symfony\Component\Form\FormError('Date is required')
                );
            }
            
            if (empty($user)) {
                $form->get('user')->addError(
                    new \Symfony\Component\Form\FormError('User is required')
                );
            }
            
            if (empty($chien)) {
                $form->get('chien')->addError(
                    new \Symfony\Component\Form\FormError('Dog is required')
                );
            }
            
            if ($form->isValid()) {
                $em->flush();

                $this->addFlash('success', 'Consultation updated successfully');
                return $this->redirectToRoute('app_consultation_index');
            } else {
                $this->addFlash('error', 'Please correct the errors in the form.');
            }
        }

        return $this->render('consultation/edit.html.twig', [
            'consultation' => $consultation,
            'form' => $form->createView(),
            'active' => 'consultation',
            'page_title' => 'Edit Consultation'
        ]);
    }

    #[Route('/delete/{id}', name: 'app_consultation_delete', methods: ['DELETE'])]
    public function delete(Request $request, Consultation $consultation, EntityManagerInterface $em): JsonResponse
    {
        // VÃ©rifier le token CSRF
        $csrfToken = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('app', $csrfToken)) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid CSRF token'
            ], 400);
        }

        try {
            $em->remove($consultation);
            $em->flush();
            
            return $this->json([
                'success' => true,
                'message' => 'Consultation deleted successfully!'
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error deleting consultation: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/suivi', name: 'app_consultation_suivi', methods: ['GET'])]
    public function suivi(): Response
    {
        // Redirection vers le SuiviController pour Ã©viter la duplication de code
        return $this->redirectToRoute('app_suivi_index');
    }

    private function notifyConsultationBySms(Consultation $consultation, TwilioNotificationService $twilioNotificationService): void
    {
        $user = $consultation->getUser();
        if ($user === null) {
            return;
        }

        $phone = (string) ($user->getPhone() ?? '');
        if (trim($phone) === '') {
            return;
        }

        $twilioNotificationService->sendConsultationNotification(
            $phone,
            trim(($user->getPrenom() ?? '') . ' ' . ($user->getNom() ?? '')),
            $consultation->getChien()?->getName() ?? 'Unknown',
            $consultation->getType() ?? 'Normal',
            $consultation->getDate()?->format('Y-m-d H:i') ?? ''
        );
    }
}
