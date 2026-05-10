<?php

namespace App\Controller;

use App\Service\GeminiService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AssistantController extends AbstractController
{
    private $geminiService;

    public function __construct(GeminiService $geminiService)
    {
        $this->geminiService = $geminiService;
    }

    #[Route('/assistant/ask', name: 'app_assistant_ask', methods: ['POST'])]
    public function ask(Request $request): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $question = $data['question'] ?? '';

        if (empty($question)) {
            return $this->json(['answer' => 'Please ask a question.'], Response::HTTP_BAD_REQUEST);
        }

        // Add context that this is PawTech assistant
        $contextualQuestion = "You are the PawTech AI assistant for a pet technology website. Keep your answers helpful, friendly, and concise. Question: " . $question;
        
        $answer = $this->geminiService->askGemini($contextualQuestion);

        return $this->json(['answer' => $answer]);
    }

    #[Route('/assistant/models', name: 'app_assistant_models', methods: ['GET'])]
    public function listModels(): JsonResponse
    {
        $models = $this->geminiService->getAvailableModels();
        return $this->json([
            'status' => 'success',
            'models' => $models
        ]);
    }

    #[Route('/assistant/test', name: 'app_assistant_test', methods: ['GET'])]
    public function test(): JsonResponse
    {
        $testQuestion = "What can you help me with? Answer in one sentence.";
        $answer = $this->geminiService->askGemini($testQuestion);

        return $this->json([
            'status' => 'ok',
            'message' => 'Test completed',
            'answer' => $answer
        ]);
    }
}