<?php

namespace App\Controller;

use App\Entity\Suivi;
use App\Form\SuiviType;
use App\Repository\SuiviRepository;
use App\Service\AiSymptomsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/suivi')]
class SuiviController extends AbstractController
{
    private EntityManagerInterface $entityManager;
    private AiSymptomsService $aiSymptomsService;
    
    public function __construct(
        EntityManagerInterface $entityManager,
        AiSymptomsService $aiSymptomsService
    ) {
        $this->entityManager = $entityManager;
        $this->aiSymptomsService = $aiSymptomsService;
    }

    #[Route('/', name: 'app_suivi_index', methods: ['GET'])]
    public function index(SuiviRepository $suiviRepository): Response
    {
        // Récupérer tous les suivis
        $suivis = $suiviRepository->findAll();
        
        // Calculer les statistiques
        $currentYearMonth = new \DateTime('first day of this month');
        
        $stats = [
            'monthly_count' => $suiviRepository->countThisMonth($currentYearMonth),
            'planned_count' => $suiviRepository->countByEtat('Planifié'),
            'completion_rate' => $suiviRepository->getCompletionRate(),
            'upcoming_count' => count($suiviRepository->findUpcoming()),
        ];

        return $this->render('suivi/index.html.twig', [
            'suivis' => $suivis,
            'stats' => $stats,
            'active' => 'suivi',
            'page_title' => 'Follow-up of Consultations'
        ]);
    }

    // Consultation detail page with 3D anatomy model
    #[Route('/consultation/{id}', name: 'app_suivi_consultation_detail', methods: ['GET'])]
    public function consultationDetail(int $id, SuiviRepository $suiviRepository): Response
    {
        // Find Suivi by consultation_id since the URL uses consultation id
        $suivi = $suiviRepository->findOneBy(['consultation' => $id]);
        
        if (!$suivi) {
            throw $this->createNotFoundException('Consultation not found');
        }
        
        $consultation = $suivi->getConsultation();
        
        // Liste des organes avec leurs mots-clés pour la détection (8 organes)
        $organs = [
            ['number' => 1, 'name' => 'Brain', 'id' => 'brain', 'keywords' => ['brain', 'cerebral', 'cranial', 'neurological', 'cerveau', 'cérébral', 'crâne', 'tête', 'head', 'neuro']],
            ['number' => 2, 'name' => 'Lungs', 'id' => 'lungs', 'keywords' => ['lung', 'lungs', 'pulmonary', 'respiratory', 'poumon', 'pulmonaire', 'respiration', 'breathing', 'chronic problem']],
            ['number' => 3, 'name' => 'Heart', 'id' => 'heart', 'keywords' => ['heart', 'cardiac', 'cardiovascular', 'cœur', 'cardiaque']],
            ['number' => 4, 'name' => 'Liver', 'id' => 'liver', 'keywords' => ['liver', 'hepatic', 'foie', 'hépatique']],
            ['number' => 5, 'name' => 'Stomach', 'id' => 'stomach', 'keywords' => ['stomach', 'gastric', 'gastro', 'estomac', 'gastrique']],
            ['number' => 6, 'name' => 'Guts', 'id' => 'guts', 'keywords' => ['gut', 'guts', 'intestine', 'intestinal', 'bowel', 'intestin', 'intestinale']],
            ['number' => 7, 'name' => 'Kidney', 'id' => 'kidney', 'keywords' => ['kidney', 'renal', 'rein', 'rénal']],
            ['number' => 8, 'name' => 'Urinary bladder', 'id' => 'bladder', 'keywords' => ['bladder', 'urinary', 'vessie', 'urinaire']]
        ];
        
        // Récupérer le diagnostic
        $diagnostic = $consultation->getDiagnostic() ?? '';
        $diagnosticLower = strtolower($diagnostic);
        
        // Détecter l'organe
        $detectedOrgan = null;
        foreach ($organs as $organ) {
            foreach ($organ['keywords'] as $keyword) {
                if (str_contains($diagnosticLower, strtolower($keyword))) {
                    $detectedOrgan = $organ;
                    break 2;
                }
            }
        }
        
        // Liste des parties du corps disponibles
        $bodyParts = [
            ['id' => 'head', 'name' => 'Head & Brain', 'number' => 1],
            ['id' => 'eyes', 'name' => 'Eyes', 'number' => 2],
            ['id' => 'ears', 'name' => 'Ears', 'number' => 3],
            ['id' => 'mouth', 'name' => 'Mouth & Teeth', 'number' => 4],
            ['id' => 'neck', 'name' => 'Neck', 'number' => 5],
            ['id' => 'chest', 'name' => 'Chest & Lungs', 'number' => 6],
            ['id' => 'heart', 'name' => 'Heart', 'number' => 7],
            ['id' => 'abdomen', 'name' => 'Abdomen', 'number' => 8],
            ['id' => 'liver', 'name' => 'Liver', 'number' => 9],
            ['id' => 'kidneys', 'name' => 'Kidneys', 'number' => 10],
            ['id' => 'spine', 'name' => 'Spine & Back', 'number' => 11],
            ['id' => 'legs_front', 'name' => 'Front Legs', 'number' => 12],
            ['id' => 'legs_back', 'name' => 'Back Legs', 'number' => 13],
            ['id' => 'paws', 'name' => 'Paws', 'number' => 14],
            ['id' => 'tail', 'name' => 'Tail', 'number' => 15],
            ['id' => 'skin', 'name' => 'Skin', 'number' => 16],
            ['id' => 'muscles', 'name' => 'Muscles', 'number' => 17]
        ];
        
        // Select a fallback analysis organ when nothing is detected from diagnostic
        $analysisOrgan = $detectedOrgan ?? $organs[array_rand($organs)];

        // Sélectionner une partie aléatoire
        $randomBodyPart = $bodyParts[array_rand($bodyParts)];
        $savedReport = (string) ($suivi->getAiAnalysisReport() ?? '');
        $savedSymptoms = $this->extractSymptomsFromReport($savedReport);
        $hasSavedAnalysis = $savedReport !== '';
        
        return $this->render('suivi/consultation_detail.html.twig', [
            'suivi' => $suivi,
            'consultation' => $consultation,
            'active' => 'suivi',
            'page_title' => 'Consultation #' . $consultation->getId() . ' - Medical Detail',
            'initial_body_part' => $randomBodyPart['id'],
            'initial_body_part_number' => $randomBodyPart['number'],
            'initial_body_part_name' => $randomBodyPart['name'],
            'body_parts' => $bodyParts,
            'detected_organ' => $detectedOrgan,
            'analysis_organ' => $analysisOrgan,
            'organs_list' => $organs,
            'saved_symptoms' => $savedSymptoms !== 'N/A' ? $savedSymptoms : '',
            'saved_analysis_report' => $savedReport,
            'has_saved_analysis' => $hasSavedAnalysis,
        ]);
    }

    #[Route('/export-pdf/{id}', name: 'app_suivi_export_pdf', methods: ['GET'])]
    public function exportPdf(int $id, SuiviRepository $suiviRepository): Response
    {
        $suivi = $suiviRepository->find($id);
        if (!$suivi) {
            throw $this->createNotFoundException('Follow-up not found');
        }

        $consultation = $suivi->getConsultation();
        if (!$consultation) {
            throw $this->createNotFoundException('Consultation not found');
        }

        $dogName = $consultation->getDog() ? (string) $consultation->getDog()->getName() : 'N/A';
        $affectedParts = $suivi->getAffectedBodyParts() ?? [];
        $analysisReport = (string) ($suivi->getAiAnalysisReport() ?? '');

        // Auto-generate AI report for PDF when missing
        if ($analysisReport === '') {
            $diagnosticText = (string) ($consultation->getDiagnostic() ?? '');
            $inferredParts = !empty($affectedParts) ? $affectedParts : $this->inferAffectedPartsFromDiagnostic($diagnosticText);
            $seedSymptoms = trim($diagnosticText) !== '' ? trim($diagnosticText) : 'general clinical monitoring';

            $generated = $this->aiSymptomsService->generateMedicalAnalysis(
                $dogName,
                (string) ($consultation->getType() ?? 'Normal'),
                $diagnosticText !== '' ? $diagnosticText : 'not specified',
                (string) ($consultation->getTraitement() ?? 'not specified'),
                $inferredParts,
                $seedSymptoms
            );

            if (($generated['success'] ?? false) === true) {
                $analysisReport = (string) ($generated['report'] ?? '');
                $emergencyLevel = (string) ($generated['emergency_level'] ?? '');
                $affectedParts = $inferredParts;

                $suivi->setAiAnalysisReport($analysisReport);
                $suivi->setAffectedBodyParts($affectedParts);
                if ($emergencyLevel !== '') {
                    $suivi->setEmergencyLevel($emergencyLevel);
                }
                $this->entityManager->flush();
            }
        }

        $affectedPartsText = empty($affectedParts) ? 'N/A' : implode(', ', array_map('strval', $affectedParts));
        $symptoms = $this->extractSymptomsFromReport($analysisReport);
        $predictedCondition = $this->extractReportField($analysisReport, 'Predicted condition');
        $predictedEmergency = $this->extractReportField($analysisReport, 'Predicted emergency');
        $consultationDate = $consultation->getDate() ? $consultation->getDate()->format('d/m/Y H:i') : 'N/A';
        $nextVisit = $suivi->getProchaineVisite() ? $suivi->getProchaineVisite()->format('d/m/Y H:i') : 'N/A';

        $logoDataUri = null;
        // PNG decoding in Dompdf requires GD (imagecreatefrompng). If GD is missing,
        // skip logo embedding so PDF export can still work.
        if (function_exists('imagecreatefrompng')) {
            $logoPath = (string) $this->getParameter('kernel.project_dir') . '/public/logo.png';
            if (is_file($logoPath)) {
                $logoBinary = @file_get_contents($logoPath);
                if ($logoBinary !== false) {
                    $logoDataUri = 'data:image/png;base64,' . base64_encode($logoBinary);
                }
            }
        }

        $html = $this->renderView('suivi/export_pdf.html.twig', [
            'generated_at' => (new \DateTime())->format('d/m/Y H:i:s'),
            'logo_data_uri' => $logoDataUri,
            'consultation' => $consultation,
            'suivi' => $suivi,
            'consultation_date' => $consultationDate,
            'dog_name' => $dogName,
            'next_visit' => $nextVisit,
            'affected_parts_text' => $affectedPartsText,
            'symptoms' => $symptoms,
            'predicted_condition' => $predictedCondition,
            'predicted_emergency' => $predictedEmergency,
            'analysis_report' => $analysisReport !== '' ? $analysisReport : 'N/A',
        ]);

        if (!class_exists(\Dompdf\Dompdf::class) || !class_exists(\Dompdf\Options::class)) {
            return new Response('PDF generation is not available. Please install dompdf/dompdf.', 500);
        }

        $options = new \Dompdf\Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isRemoteEnabled', true);
        $options->set('isHtml5ParserEnabled', true);
        $options->set('chroot', (string) $this->getParameter('kernel.project_dir') . '/public');

        $dompdf = new \Dompdf\Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();
        $pdfContent = $dompdf->output();
        $consultationId = (string) $consultation->getId();
        $filename = 'consultation_' . $consultationId . '_followup_' . (string) $suivi->getId() . '.pdf';

        return new Response($pdfContent, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"',
        ]);
    }

    private function inferAffectedPartsFromDiagnostic(string $diagnostic): array
    {
        $d = strtolower($diagnostic);
        $map = [
            'brain' => ['brain', 'neurological', 'head', 'cerebral'],
            'lungs' => ['lung', 'lungs', 'respiratory', 'breathing', 'pulmonary'],
            'heart' => ['heart', 'cardiac', 'cardio'],
            'liver' => ['liver', 'hepatic'],
            'stomach' => ['stomach', 'gastric', 'gastro'],
            'guts' => ['gut', 'guts', 'intestine', 'bowel', 'intestinal'],
            'kidney' => ['kidney', 'renal'],
            'bladder' => ['bladder', 'urinary', 'urine'],
        ];

        $parts = [];
        foreach ($map as $part => $keywords) {
            foreach ($keywords as $kw) {
                if (str_contains($d, $kw)) {
                    $parts[] = $part;
                    break;
                }
            }
        }

        return !empty($parts) ? array_values(array_unique($parts)) : ['kidney'];
    }

    // AJAX: Generate AI Analysis
    #[Route('/ai-analyze', name: 'app_suivi_ai_analyze', methods: ['POST'])]
    public function aiAnalyze(Request $request, SuiviRepository $suiviRepository): JsonResponse
    {
        try {
            $data = json_decode($request->getContent(), true);
            if (!is_array($data)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Invalid JSON payload'
                ], 400);
            }

            $suiviId = (int) ($data['suivi_id'] ?? 0);
            $affectedParts = $data['affected_parts'] ?? [];
            $symptoms = trim((string) ($data['symptoms'] ?? ''));
            $analysisMode = (string) ($data['analysis_mode'] ?? 'default');
            
            if (!$suiviId) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Suivi ID required'
                ], 400);
            }

            if (!is_array($affectedParts)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'affected_parts must be an array'
                ], 400);
            }
            
            $suivi = $suiviRepository->find($suiviId);
            if (!$suivi) {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'Suivi not found'
                ], 404);
            }
            
            $consultation = $suivi->getConsultation();
            $dogName = $consultation && $consultation->getDog() ? $consultation->getDog()->getName() : 'Unknown';
            $consultationType = $consultation ? (string) $consultation->getType() : 'Unknown';
            $diagnostic = $consultation ? (string) ($consultation->getDiagnostic() ?? 'Not specified') : 'Not specified';
            $treatment = $consultation ? (string) ($consultation->getTraitement() ?? 'Not specified') : 'Not specified';

            $analysis = $this->aiSymptomsService->generateMedicalAnalysis(
                $dogName,
                $consultationType,
                $diagnostic,
                $treatment,
                $affectedParts,
                $symptoms
            );

            if (!($analysis['success'] ?? false)) {
                return new JsonResponse([
                    'success' => false,
                    'error' => $analysis['error'] ?? 'AI analysis failed'
                ], 502);
            }

            $analysisReport = (string) ($analysis['report'] ?? '');
            $emergencyLevel = (string) ($analysis['emergency_level'] ?? 'low');
            $analysisSource = (string) ($analysis['source'] ?? 'unknown');

            if ($analysisMode === 'knn_only' && $analysisSource !== 'knn') {
                return new JsonResponse([
                    'success' => false,
                    'error' => 'KNN model unavailable. Train model first with: py ml/train_knn.py'
                ], 503);
            }
            
            // Save to database - CORRECTION ICI: utiliser $this->entityManager au lieu de $this->getDoctrine()
            $suivi->setAiAnalysisReport($analysisReport);
            $suivi->setAffectedBodyParts($affectedParts);
            $suivi->setEmergencyLevel($emergencyLevel);
            
            $this->entityManager->flush();
            
            return new JsonResponse([
                'success' => true,
                'report' => $analysisReport,
                'emergency_level' => $emergencyLevel,
                'emergency_display' => $suivi->getEmergencyLevelDisplay(),
                'source' => $analysisSource,
            ]);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'success' => false,
                'error' => $e->getMessage()
            ], 500);
        }
    }

    // AJAX: Get random body part
    #[Route('/random-body-part', name: 'app_suivi_random_body_part', methods: ['GET'])]
    public function getRandomBodyPart(): JsonResponse
    {
        $bodyParts = [
            ['id' => 'head', 'name' => 'Head & Brain', 'number' => 1],
            ['id' => 'eyes', 'name' => 'Eyes', 'number' => 2],
            ['id' => 'ears', 'name' => 'Ears', 'number' => 3],
            ['id' => 'mouth', 'name' => 'Mouth & Teeth', 'number' => 4],
            ['id' => 'neck', 'name' => 'Neck', 'number' => 5],
            ['id' => 'chest', 'name' => 'Chest & Lungs', 'number' => 6],
            ['id' => 'heart', 'name' => 'Heart', 'number' => 7],
            ['id' => 'abdomen', 'name' => 'Abdomen', 'number' => 8],
            ['id' => 'liver', 'name' => 'Liver', 'number' => 9],
            ['id' => 'kidneys', 'name' => 'Kidneys', 'number' => 10],
            ['id' => 'spine', 'name' => 'Spine & Back', 'number' => 11],
            ['id' => 'legs_front', 'name' => 'Front Legs', 'number' => 12],
            ['id' => 'legs_back', 'name' => 'Back Legs', 'number' => 13],
            ['id' => 'paws', 'name' => 'Paws', 'number' => 14],
            ['id' => 'tail', 'name' => 'Tail', 'number' => 15],
            ['id' => 'skin', 'name' => 'Skin', 'number' => 16],
            ['id' => 'muscles', 'name' => 'Muscles', 'number' => 17]
        ];
        
        $randomBodyPart = $bodyParts[array_rand($bodyParts)];
        
        return new JsonResponse($randomBodyPart);
    }

    // AJAX: Get symptoms for an organ (uses AI service)
    #[Route('/ai-symptoms/{organNumber}', name: 'app_suivi_ai_symptoms', methods: ['GET'])]
    public function getOrganSymptoms(int $organNumber, Request $request, SuiviRepository $suiviRepository): JsonResponse
    {
        // Map organ numbers to organ names
        $organNames = [
            1 => 'Brain',
            2 => 'Lungs',
            3 => 'Heart',
            4 => 'Liver',
            5 => 'Stomach',
            6 => 'Guts',
            7 => 'Kidney',
            8 => 'Urinary Bladder'
        ];

        $organName = $organNames[$organNumber] ?? 'Unknown';
        $consultationType = '';
        $diagnostic = '';
        $treatment = '';

        $suiviId = (int) $request->query->get('suivi_id', 0);
        if ($suiviId > 0) {
            $suivi = $suiviRepository->find($suiviId);
            if ($suivi && $suivi->getConsultation()) {
                $consultation = $suivi->getConsultation();
                $consultationType = (string) ($consultation->getType() ?? '');
                $diagnostic = (string) ($consultation->getDiagnostic() ?? '');
                $treatment = (string) ($consultation->getTraitement() ?? '');
            }
        }

        // Use AI service to generate symptoms
        $result = $this->aiSymptomsService->generateSymptomsForOrgan(
            $organNumber,
            $organName,
            $consultationType,
            $diagnostic,
            $treatment
        );

        if (!($result['success'] ?? false)) {
            return new JsonResponse([
                'success' => false,
                'error' => $result['error'] ?? 'AI symptoms generation failed',
                'organ_number' => $organNumber,
            ], 502);
        }

        return new JsonResponse([
            'success' => $result['success'],
            'organ' => $result['organ'],
            'symptoms' => $result['symptoms'],
            'organ_number' => $organNumber,
            'source' => $result['source'] ?? 'service'
        ]);
    }

    // AJAX: Filter by emergency level
    #[Route('/filter-by-emergency', name: 'app_suivi_filter_by_emergency', methods: ['GET'])]
    public function filterByEmergency(Request $request, SuiviRepository $suiviRepository): JsonResponse
    {
        try {
            $emergencyLevel = $request->query->get('level', '');
            
            if (empty($emergencyLevel)) {
                $suivis = $suiviRepository->findAll();
            } else {
                $suivis = $suiviRepository->findByEmergencyLevel($emergencyLevel);
            }
            
            // Sort by emergency priority
            $priorityOrder = [
                'critical' => 1,
                'high' => 2,
                'medium' => 3,
                'low' => 4
            ];
            
            usort($suivis, function($a, $b) use ($priorityOrder) {
                $levelA = $a->getEmergencyLevel() ?? 'low';
                $levelB = $b->getEmergencyLevel() ?? 'low';
                return ($priorityOrder[$levelA] ?? 5) - ($priorityOrder[$levelB] ?? 5);
            });
            
            $data = [];
            foreach ($suivis as $suivi) {
                $data[] = [
                    'id' => $suivi->getId(),
                    'etat' => $suivi->getEtat() ?? 'N/A',
                    'type' => $suivi->getType() ?? 'N/A',
                    'recommandation' => $suivi->getRecommandation() ?? '',
                    'prochaine_visite' => $suivi->getProchaineVisite() ? 
                        $suivi->getProchaineVisite()->format('d/m/Y H:i') : null,
                    'consultation_id' => $suivi->getConsultation() ? $suivi->getConsultation()->getId() : null,
                    'dog_nom' => $suivi->getConsultation() && $suivi->getConsultation()->getDog() ?
                        $suivi->getConsultation()->getDog()->getName() : 'N/A',
                    'emergency_level' => $suivi->getEmergencyLevel(),
                    'emergency_display' => $suivi->getEmergencyLevelDisplay(),
                    'ai_analysis_report' => $suivi->getAiAnalysisReport(),
                ];
            }
            
            return new JsonResponse($data);
            
        } catch (\Exception $e) {
            return new JsonResponse(['error' => $e->getMessage()], 500);
        }
    }

    // AJAX: Recherche par type OU état
    #[Route('/search-by-type', name: 'app_suivi_search_by_type', methods: ['GET'])]
    public function searchByType(Request $request, SuiviRepository $suiviRepository): JsonResponse
    {
        try {
            $searchValue = $request->query->get('searchValue', '');
            
            if (empty($searchValue)) {
                $suivis = $suiviRepository->findAll();
            } else {
                // Map des termes de recherche pour les statuts
                $etatMap = [
                    'planifié' => 'Planifié',
                    'planifiée' => 'Planifié',
                    'planifiées' => 'Planifié',
                    'en cours' => 'En cours',
                    'encours' => 'En cours',
                    'terminé' => 'Terminé',
                    'terminée' => 'Terminé',
                    'terminées' => 'Terminé',
                    'annulé' => 'Annulé',
                    'annulée' => 'Annulé',
                    'annulées' => 'Annulé',
                    'planned' => 'Planifié',
                    'ongoing' => 'En cours',
                    'completed' => 'Terminé',
                    'cancelled' => 'Annulé'
                ];
                
                $lowerSearchTerm = strtolower($searchValue);
                
                // Recherche par type OU par état
                $query = $suiviRepository->createQueryBuilder('s');
                
                if (isset($etatMap[$lowerSearchTerm])) {
                    // Recherche exacte par état
                    $query->where('s.etat = :etat')
                          ->setParameter('etat', $etatMap[$lowerSearchTerm]);
                } else {
                    // Recherche par type avec LIKE
                    $searchTermLike = '%' . $searchValue . '%';
                    $query->where('s.type LIKE :searchTerm')
                          ->setParameter('searchTerm', $searchTermLike);
                }
                
                $query->orderBy('s.prochaineVisite', 'DESC')
                      ->addOrderBy('s.id', 'DESC');
                
                $suivis = $query->getQuery()->getResult();
            }
            
            // Données formatées pour JSON
            $data = [];
            foreach ($suivis as $suivi) {
                $data[] = [
                    'id' => $suivi->getId(),
                    'etat' => $suivi->getEtat() ?? 'N/A',
                    'type' => $suivi->getType() ?? 'N/A',
                    'recommandation' => $suivi->getRecommandation() ?? '',
                    'prochaine_visite' => $suivi->getProchaineVisite() ? 
                        $suivi->getProchaineVisite()->format('d/m/Y H:i') : null,
                    'consultation_id' => $suivi->getConsultation() ? $suivi->getConsultation()->getId() : null,
                    'dog_nom' => $suivi->getConsultation() && $suivi->getConsultation()->getDog() ?
                        $suivi->getConsultation()->getDog()->getName() : 'N/A',
                    'emergency_level' => $suivi->getEmergencyLevel(),
                    'emergency_display' => $suivi->getEmergencyLevelDisplay(),
                ];
            }
            
            return new JsonResponse($data);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    
    #[Route('/search-by-etat', name: 'app_suivi_search_by_etat', methods: ['GET'])]
    public function searchByEtat(Request $request, SuiviRepository $suiviRepository): JsonResponse
    {
        try {
            $etat = $request->query->get('etat', '');
            
            if (empty($etat)) {
                $suivis = $suiviRepository->findAll();
            } else {
                $suivis = $suiviRepository->findByEtat($etat);
            }
            
            $data = [];
            foreach ($suivis as $suivi) {
                $data[] = [
                    'id' => $suivi->getId(),
                    'etat' => $suivi->getEtat() ?? 'N/A',
                    'type' => $suivi->getType() ?? 'N/A',
                    'recommandation' => $suivi->getRecommandation() ?? '',
                    'ai_analysis_report' => $suivi->getAiAnalysisReport() ?? '',
                    'prochaine_visite' => $suivi->getProchaineVisite() ? 
                        $suivi->getProchaineVisite()->format('d/m/Y H:i') : null,
                    'consultation_id' => $suivi->getConsultation() ? $suivi->getConsultation()->getId() : null,
                    'chien_nom' => $suivi->getConsultation() && $suivi->getConsultation()->getChien() ?
                        $suivi->getConsultation()->getChien()->getNom() : 'N/A',
                ];
            }
            
            return new JsonResponse($data);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

   
    #[Route('/sort-by-date', name: 'app_suivi_sort_by_date', methods: ['GET'])]
    public function sortByDate(SuiviRepository $suiviRepository): JsonResponse
    {
        try {
            // Tri simple
            $suivis = $suiviRepository->findAllOrdered();
            
            $data = [];
            foreach ($suivis as $suivi) {
                $data[] = [
                    'id' => $suivi->getId(),
                    'etat' => $suivi->getEtat() ?? 'N/A',
                    'type' => $suivi->getType() ?? 'N/A',
                    'recommandation' => $suivi->getRecommandation() ?? '',
                    'ai_analysis_report' => $suivi->getAiAnalysisReport() ?? '',
                    'prochaine_visite' => $suivi->getProchaineVisite() ? 
                        $suivi->getProchaineVisite()->format('d/m/Y H:i') : null,
                    'consultation_id' => $suivi->getConsultation() ? $suivi->getConsultation()->getId() : null,
                    'dog_nom' => $suivi->getConsultation() && $suivi->getConsultation()->getDog() ?
                        $suivi->getConsultation()->getDog()->getName() : 'N/A',
                ];
            }
            
            return new JsonResponse($data);
            
        } catch (\Exception $e) {
            return new JsonResponse([
                'error' => 'Server error',
                'message' => $e->getMessage()
            ], 500);
        }
    }

    #[Route('/new', name: 'app_suivi_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $suivi = new Suivi();
        
        $form = $this->createForm(SuiviType::class, $suivi);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->persist($suivi);
            $this->entityManager->flush();

            $this->addFlash('success', 'Follow-Up ajouté avec succès !');
            return $this->redirectToRoute('app_suivi_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('suivi/new.html.twig', [
            'suivi' => $suivi,
            'form' => $form->createView(),
            'active' => 'suivi',
            'page_title' => 'Add New Follow-Up'
        ]);
    }

    #[Route('/{id}/edit', name: 'app_suivi_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Suivi $suivi): Response
    {
        $form = $this->createForm(SuiviType::class, $suivi);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();

            $this->addFlash('success', 'Follow-Up modifié avec succès !');
            return $this->redirectToRoute('app_suivi_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('suivi/edit.html.twig', [
            'suivi' => $suivi,
            'form' => $form->createView(),
            'active' => 'suivi',
            'page_title' => 'Modifier Follow-Up #' . $suivi->getId()
        ]);
    }

    #[Route('/delete/{id}', name: 'app_suivi_delete', methods: ['DELETE'])]
    public function delete(Request $request, Suivi $suivi): JsonResponse
    {
        // Vérifier le token CSRF
        $csrfToken = $request->headers->get('X-CSRF-Token');
        if (!$this->isCsrfTokenValid('app', $csrfToken)) {
            return $this->json([
                'success' => false,
                'message' => 'Invalid CSRF token'
            ], 400);
        }

        try {
            $this->entityManager->remove($suivi);
            $this->entityManager->flush();
            
            return $this->json([
                'success' => true,
                'message' => 'Follow-up deleted successfully!'
            ]);
            
        } catch (\Exception $e) {
            return $this->json([
                'success' => false,
                'message' => 'Error deleting follow-up: ' . $e->getMessage()
            ], 500);
        }
    }

    #[Route('/test', name: 'app_suivi_test', methods: ['GET'])]
    public function test(): Response
    {
        return $this->render('suivi/test.html.twig');
    }

    private function extractSymptomsFromReport(string $report): string
    {
        if ($report === '') {
            return 'N/A';
        }

        if (preg_match('/Symptoms:\s*(.+?)(?:\n\s*\n|$)/si', $report, $matches) === 1) {
            $value = trim($matches[1]);
            return $value !== '' ? $value : 'N/A';
        }

        return 'N/A';
    }

    private function extractReportField(string $report, string $fieldName): string
    {
        if ($report === '') {
            return 'N/A';
        }

        $pattern = '/' . preg_quote($fieldName, '/') . ':\s*(.+)$/mi';
        if (preg_match($pattern, $report, $matches) === 1) {
            $value = trim($matches[1]);
            return $value !== '' ? $value : 'N/A';
        }

        return 'N/A';
    }

}
