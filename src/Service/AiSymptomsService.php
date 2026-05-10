<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Process\Process;

class AiSymptomsService
{
    private string $projectDir;
    private ?LoggerInterface $logger;

    public function __construct(ParameterBagInterface $params, ?LoggerInterface $logger = null)
    {
        $this->projectDir = (string) $params->get('kernel.project_dir');
        $this->logger = $logger;
    }

    public function generateSymptomsForOrgan(
        int $organNumber,
        string $organName,
        string $consultationType = '',
        string $diagnostic = '',
        string $treatment = ''
    ): array
    {
        $organMap = [
            1 => 'brain',
            2 => 'lungs',
            3 => 'heart',
            4 => 'liver',
            5 => 'stomach',
            6 => 'guts',
            7 => 'kidney',
            8 => 'bladder',
        ];

        $organPart = $organMap[$organNumber] ?? strtolower(trim($organName));
        $contextSymptoms = trim($diagnostic . ' ' . $treatment);

        $result = $this->runKnn([
            'task' => 'suggest_symptoms',
            'affected_parts' => [$organPart],
            'consultation_type' => $consultationType,
            'diagnostic' => $diagnostic,
            'treatment' => $treatment,
            'symptoms' => $contextSymptoms,
        ]);

        if (!($result['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $result['error'] ?? 'KNN symptoms generation failed',
            ];
        }

        $symptoms = trim((string) ($result['symptoms'] ?? ''));
        if ($symptoms === '') {
            return [
                'success' => false,
                'error' => 'KNN returned empty symptoms',
            ];
        }

        return [
            'success' => true,
            'organ' => $organName,
            'organ_number' => $organNumber,
            'symptoms' => $symptoms,
            'source' => 'knn',
        ];
    }

    public function generateMedicalAnalysis(
        string $dogName,
        string $consultationType,
        string $diagnostic,
        string $treatment,
        array $affectedParts,
        string $symptoms
    ): array {
        $knnResult = $this->runKnn([
            'task' => 'predict',
            'affected_parts' => array_values($affectedParts),
            'consultation_type' => $consultationType,
            'diagnostic' => $diagnostic,
            'treatment' => $treatment,
            'symptoms' => $symptoms,
        ]);

        if (!($knnResult['success'] ?? false)) {
            return [
                'success' => false,
                'error' => $knnResult['error'] ?? 'KNN prediction failed',
            ];
        }

        $emergencyLevel = strtolower(trim((string) ($knnResult['emergency_level'] ?? 'low')));
        $conditionLabel = trim((string) ($knnResult['condition_label'] ?? 'unknown'));
        $neighbors = (array) ($knnResult['neighbors'] ?? []);
        $emergencyConfidence = (float) ($knnResult['emergency_confidence'] ?? 0.0);
        $conditionConfidence = (float) ($knnResult['condition_confidence'] ?? 0.0);

        $report = $this->buildKnnReport(
            $dogName,
            $consultationType,
            $diagnostic,
            $treatment,
            $affectedParts,
            $symptoms,
            $conditionLabel,
            $emergencyLevel,
            $neighbors,
            $emergencyConfidence,
            $conditionConfidence
        );

        return [
            'success' => true,
            'report' => $report,
            'emergency_level' => $emergencyLevel,
            'source' => 'knn',
        ];
    }

    private function runKnn(array $payload): array
    {
        $scriptPath = $this->projectDir . DIRECTORY_SEPARATOR . 'ml' . DIRECTORY_SEPARATOR . 'predict_knn.py';
        $modelPath = $this->projectDir . DIRECTORY_SEPARATOR . 'var' . DIRECTORY_SEPARATOR . 'ml' . DIRECTORY_SEPARATOR . 'vet_knn_model.joblib';

        if (!is_file($scriptPath)) {
            return ['success' => false, 'error' => 'KNN predictor script not found'];
        }

        if (!is_file($modelPath)) {
            return ['success' => false, 'error' => 'KNN model not trained yet. Run: py ml/train_knn.py'];
        }

        $payload['model_path'] = $modelPath;
        $jsonPayload = json_encode($payload);
        if ($jsonPayload === false) {
            return ['success' => false, 'error' => 'Failed to encode KNN payload'];
        }

        $commands = [
            ['python', $scriptPath],
            ['py', '-3', $scriptPath],
        ];

        foreach ($commands as $command) {
            $process = new Process($command, $this->projectDir);
            $process->setInput($jsonPayload);
            $process->setTimeout(15);
            $process->run();

            if (!$process->isSuccessful()) {
                $this->logger?->debug('KNN process failed', [
                    'command' => implode(' ', $command),
                    'error' => $process->getErrorOutput(),
                ]);
                continue;
            }

            $output = trim($process->getOutput());
            if ($output === '') {
                continue;
            }

            $decoded = json_decode($output, true);
            if (is_array($decoded)) {
                return $decoded;
            }
        }

        return ['success' => false, 'error' => 'Unable to execute Python KNN predictor'];
    }

    private function buildKnnReport(
        string $dogName,
        string $consultationType,
        string $diagnostic,
        string $treatment,
        array $affectedParts,
        string $symptoms,
        string $conditionLabel,
        string $emergencyLevel,
        array $neighbors,
        float $emergencyConfidence,
        float $conditionConfidence
    ): string {
        $parts = empty($affectedParts) ? 'None specified' : implode(', ', $affectedParts);

        $report = "Patient: {$dogName}\n";
        $report .= "Consultation type: {$consultationType}\n";
        $report .= "Date: " . date('Y-m-d H:i:s') . "\n\n";
        $report .= "Initial diagnosis:\n{$diagnostic}\n\n";
        $report .= "Affected body parts:\n{$parts}\n\n";
        $report .= "Symptoms:\n{$symptoms}\n\n";
        $report .= "Predicted condition: {$conditionLabel}\n";
        $report .= "Predicted emergency: {$emergencyLevel}\n";
        $report .= "Condition confidence: " . number_format($conditionConfidence * 100, 1) . "%\n";
        $report .= "Emergency confidence: " . number_format($emergencyConfidence * 100, 1) . "%\n\n";

        if (!empty($neighbors)) {
            $report .= "Closest known cases:\n";
            foreach ($neighbors as $index => $neighbor) {
                $n = $index + 1;
                $distance = isset($neighbor['distance']) ? (float) $neighbor['distance'] : 0.0;
                $nEmergency = (string) ($neighbor['emergency_level'] ?? 'unknown');
                $nCondition = (string) ($neighbor['condition_label'] ?? 'unknown');
                $nSymptoms = (string) ($neighbor['symptoms'] ?? '');
                $report .= "{$n}. [dist=" . number_format($distance, 3) . "] {$nCondition} / {$nEmergency} - {$nSymptoms}\n";
            }
            $report .= "\n";
        }

        $report .= "Recommended treatment:\n{$treatment}\n";
        $report .= "\nRecommendations:\n";
        $report .= "1. Confirm prediction with veterinary clinical exam.\n";
        $report .= "2. Monitor symptom progression in the next 24-48h.\n";
        $report .= "3. Escalate immediately if respiratory or neurological signs worsen.\n";

        return $report;
    }
}
