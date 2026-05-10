<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;

class GeminiService
{
    private $client;
    private $apiKey;
    private $logger;

    public function __construct(HttpClientInterface $client, string $apiKey, LoggerInterface $logger = null)
    {
        $this->client = $client;
        $this->apiKey = $apiKey;
        $this->logger = $logger;
    }

    public function askGemini(string $question): ?string
    {
        // Use the models that are actually available from your debug output
        $models = [
            'gemini-2.5-flash',      // Fast and efficient
            'gemini-2.5-pro',         // Most capable
            'gemini-2.0-flash',       // Good fallback
            'gemini-2.5-flash-lite'   // Lightweight option
        ];

        $lastError = null;

        foreach ($models as $model) {
            try {
                $answer = $this->callModel($model, $question);
                if ($answer !== null) {
                    if ($this->logger) {
                        $this->logger->info('Successfully used model', ['model' => $model]);
                    }
                    return $answer;
                }
            } catch (\Exception $e) {
                $lastError = $e;
                if ($this->logger) {
                    $this->logger->warning('Model failed', ['model' => $model, 'error' => $e->getMessage()]);
                }
                continue;
            }
        }

        // Return a user-friendly message
        return "I'm having trouble connecting to my AI services right now. Please try again later.";
    }

    private function callModel(string $model, string $question): ?string
    {
        // Use v1 API (stable)
        $url = 'https://generativelanguage.googleapis.com/v1/models/' . $model . ':generateContent?key=' . $this->apiKey;
        
        $body = [
            'contents' => [
                [
                    'parts' => [
                        ['text' => $question]
                    ]
                ]
            ],
            'generationConfig' => [
                'temperature' => 0.7,
                'maxOutputTokens' => 800,
                'topP' => 0.95,
                'topK' => 40
            ],
            'safetySettings' => [
                [
                    'category' => 'HARM_CATEGORY_HARASSMENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_HATE_SPEECH',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_SEXUALLY_EXPLICIT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ],
                [
                    'category' => 'HARM_CATEGORY_DANGEROUS_CONTENT',
                    'threshold' => 'BLOCK_MEDIUM_AND_ABOVE'
                ]
            ]
        ];

        try {
            $response = $this->client->request('POST', $url, [
                'json' => $body,
                'timeout' => 15,
            ]);

            $statusCode = $response->getStatusCode();
            
            if ($statusCode !== 200) {
                return null;
            }

            $data = $response->toArray();

            if (isset($data['candidates'][0]['content']['parts'][0]['text'])) {
                return $data['candidates'][0]['content']['parts'][0]['text'];
            }

            return null;

        } catch (\Exception $e) {
            throw $e;
        }
    }

    /**
     * Get list of available models
     */
    public function getAvailableModels(): array
    {
        $url = 'https://generativelanguage.googleapis.com/v1/models?key=' . $this->apiKey;

        try {
            $response = $this->client->request('GET', $url, [
                'timeout' => 5,
            ]);

            $data = $response->toArray();
            
            $models = [];
            if (isset($data['models'])) {
                foreach ($data['models'] as $model) {
                    $name = str_replace('models/', '', $model['name']);
                    $models[] = [
                        'name' => $name,
                        'displayName' => $model['displayName'] ?? $name,
                    ];
                }
            }
            
            return $models;

        } catch (\Exception $e) {
            return [];
        }
    }
}