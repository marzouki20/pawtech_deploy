<?php

namespace App\Controller;

use App\Repository\UserRepository;
use App\Service\DonorPredictionService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/participation/donor-prediction')]
class DonorPredictionController extends AbstractController
{
    #[Route('', name: 'app_donor_prediction_index', methods: ['GET'])]
    public function index(
        UserRepository $userRepository,
        DonorPredictionService $donorService
    ): Response {
        $users = $userRepository->findAll();
        $predictions = $donorService->predictForAllUsers($users);
        $modelInfo = $donorService->getModelInfo();

        $summary = [
            'total' => count($predictions),
            'high_potential' => count(array_filter($predictions, fn($p) => $p['category'] === 'High Potential')),
            'medium' => count(array_filter($predictions, fn($p) => $p['category'] === 'Medium')),
            'low' => count(array_filter($predictions, fn($p) => $p['category'] === 'Low'))
        ];

        return $this->render('donor_prediction/index.html.twig', [
            'predictions' => $predictions,
            'model_info' => $modelInfo,
            'summary' => $summary
        ]);
    }

    #[Route('/predict/{id}', name: 'app_donor_prediction_single', methods: ['GET'])]
    public function predictSingle(
        int $id,
        UserRepository $userRepository,
        DonorPredictionService $donorService
    ): JsonResponse {
        $user = $userRepository->find($id);
        
        if (!$user) {
            return $this->json(['ok' => false, 'message' => 'User not found'], 404);
        }

        $prediction = $donorService->predictForUser($user);
        
        if (!$prediction) {
            return $this->json(['ok' => false, 'message' => 'Prediction failed - is the AI service running?'], 500);
        }

        return $this->json(['ok' => true, 'prediction' => $prediction]);
    }

    #[Route('/api/all', name: 'app_donor_prediction_all', methods: ['GET'])]
    public function predictAll(
        UserRepository $userRepository,
        DonorPredictionService $donorService
    ): JsonResponse {
        $users = $userRepository->findAll();
        $predictions = $donorService->predictForAllUsers($users);

        return $this->json([
            'ok' => true,
            'total' => count($predictions),
            'predictions' => $predictions
        ]);
    }
}
