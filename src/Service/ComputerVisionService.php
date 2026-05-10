<?php

namespace App\Service;

use App\Entity\DogDetection;
use App\Entity\IpCamera;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpClient\HttpClient;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class ComputerVisionService
{
    private HttpClientInterface $httpClient;
    private EntityManagerInterface $entityManager;
    private string $cvApiUrl;
    private string $cvApiKey;

    public function __construct(
        EntityManagerInterface $entityManager,
        HttpClientInterface $httpClient,
        string $cvApiUrl = 'http://localhost:5000',
        string $cvApiKey = ''
    ) {
        $this->entityManager = $entityManager;
        $this->httpClient = $httpClient;
        $this->cvApiUrl = $cvApiUrl;
        $this->cvApiKey = $cvApiKey;
    }

    /**
     * Process a camera frame for dog detection
     */
    public function processFrame(IpCamera $camera, string $frameData): ?DogDetection
    {
        try {
            // Send frame to CV API
            $response = $this->httpClient->request('POST', $this->cvApiUrl . '/detect', [
                'json' => [
                    'image' => base64_encode($frameData),
                    'camera_id' => $camera->getId(),
                ],
                'headers' => [
                    'Authorization' => 'Bearer ' . $this->cvApiKey,
                ],
            ]);

            $result = $response->toArray();

            if (isset($result['detections']) && !empty($result['detections'])) {
                // Process first detection
                $detection = $result['detections'][0];
                return $this->createDetectionFromResult($camera, $detection);
            }

            return null;
        } catch (\Exception $e) {
            // Log error and return null
            return null;
        }
    }

    /**
     * Process image from camera snapshot
     */
    public function analyzeSnapshot(IpCamera $camera): ?DogDetection
    {
        try {
            // Get snapshot from camera
            $snapshotUrl = $camera->getFullSnapshotUrl();
            
            $response = $this->httpClient->request('GET', $snapshotUrl);
            $imageData = $response->getContent();

            return $this->processFrame($camera, $imageData);
        } catch (\Exception $e) {
            return null;
        }
    }

    /**
     * Batch process detections for all active cameras
     */
    public function processAllCameras(): array
    {
        $cameras = $this->entityManager->getRepository(IpCamera::class)->findBy(['status' => 'active']);
        $results = [];

        foreach ($cameras as $camera) {
            $detection = $this->analyzeSnapshot($camera);
            if ($detection) {
                $this->entityManager->persist($detection);
                $results[] = $detection;
            }
        }

        if (!empty($results)) {
            $this->entityManager->flush();
        }

        return $results;
    }

    /**
     * Analyze health conditions from detected dog
     */
    public function analyzeHealthCondition(array $detectionData): array
    {
        $healthConditions = [];
        $symptoms = $detectionData['symptoms'] ?? [];

        // Check for common health conditions
        if (in_array('red_eyes', $symptoms, true) || ($detectionData['eye_color_anomaly'] ?? false)) {
            $healthConditions[] = 'red_eyes';
        }

        if (in_array('oral_discharge', $symptoms, true) || ($detectionData['mouth_discharge'] ?? false)) {
            $healthConditions[] = 'oral_discharge';
        }

        if (in_array('excessive_drooling', $symptoms, true) || ($detectionData['drooling'] ?? false)) {
            $healthConditions[] = 'excessive_drooling';
        }

        if (in_array('lethargy', $symptoms, true) || ($detectionData['low_activity'] ?? false)) {
            $healthConditions[] = 'lethargy';
        }

        if (in_array('limping', $symptoms, true) || ($detectionData['abnormal_gait'] ?? false)) {
            $healthConditions[] = 'limping';
        }

        if (in_array('hair_loss', $symptoms, true) || ($detectionData['bald_patches'] ?? false)) {
            $healthConditions[] = 'hair_loss';
        }

        if (in_array('skin_irritation', $symptoms, true) || ($detectionData['skin_lesions'] ?? false)) {
            $healthConditions[] = 'skin_irritation';
        }

        if (in_array('weight_loss', $symptoms, true) || ($detectionData['ribs_visible'] ?? false)) {
            $healthConditions[] = 'weight_loss';
        }

        if (in_array('appetite_loss', $symptoms, true) || ($detectionData['not_eating'] ?? false)) {
            $healthConditions[] = 'appetite_loss';
        }

        if (in_array('vomiting', $symptoms, true) || ($detectionData['vomiting'] ?? false)) {
            $healthConditions[] = 'vomiting';
        }

        if (in_array('diarrhea', $symptoms, true) || ($detectionData['loose_stool'] ?? false)) {
            $healthConditions[] = 'diarrhea';
        }

        if (in_array('breathing_difficulty', $symptoms, true) || ($detectionData['heavy_breathing'] ?? false)) {
            $healthConditions[] = 'breathing_difficulty';
        }

        if (in_array('eye_discharge', $symptoms, true) || ($detectionData['eye_discharge'] ?? false)) {
            $healthConditions[] = 'eye_discharge';
        }

        if (in_array('ear_infection', $symptoms, true) || ($detectionData['ear_scratching'] ?? false)) {
            $healthConditions[] = 'ear_infection';
        }

        return $healthConditions;
    }

    /**
     * Determine severity based on health conditions
     */
    public function determineSeverity(array $healthConditions): string
    {
        $criticalConditions = [
            'breathing_difficulty',
            'vomiting',
            'diarrhea',
        ];

        $seriousConditions = [
            'lethargy',
            'weight_loss',
            'appetite_loss',
        ];

        foreach ($healthConditions as $condition) {
            if (in_array($condition, $criticalConditions)) {
                return 'critical';
            }
        }

        foreach ($healthConditions as $condition) {
            if (in_array($condition, $seriousConditions)) {
                return 'serious';
            }
        }

        if (!empty($healthConditions)) {
            return 'medium';
        }

        return 'normal';
    }

    /**
     * Create detection entity from API result
     */
    private function createDetectionFromResult(IpCamera $camera, array $result): DogDetection
    {
        $detection = new DogDetection();
        $detection->setCamera($camera);
        $detection->setBehaviorType($result['behavior'] ?? 'unknown');
        $detection->setConfidence($result['confidence'] ?? 0);
        $detection->setDetectedObject($result['class'] ?? 'dog');
        
        if (isset($result['bbox'])) {
            $detection->setBoundingBox($result['bbox']);
        }

        // Analyze health conditions
        $healthConditions = $this->analyzeHealthCondition($result);
        if (!empty($healthConditions)) {
            $detection->setHealthCondition($healthConditions[0]); // Primary condition
            $detection->setHealthSymptoms($healthConditions);
            $detection->setSeverity($this->determineSeverity($healthConditions));
        } else {
            $detection->setHealthCondition('healthy');
            $detection->setSeverity('normal');
        }

        $detection->setDescription($result['description'] ?? null);
        $detection->setMetadata($result);
        $detection->setDetectedAt(new \DateTime());
        $detection->setCreatedAt(new \DateTime());

        return $detection;
    }

    /**
     * Get supported behaviors
     */
    public static function getSupportedBehaviors(): array
    {
        return [
            'standing',
            'sitting',
            'lying_down',
            'running',
            'walking',
            'eating',
            'drinking',
            'sleeping',
            'playing',
            'barking',
            'scratching',
            'shaking',
        ];
    }

    /**
     * Get supported health conditions
     */
    public static function getSupportedHealthConditions(): array
    {
        return [
            'healthy',
            'red_eyes',
            'oral_discharge',
            'excessive_drooling',
            'lethargy',
            'limping',
            'hair_loss',
            'skin_irritation',
            'weight_loss',
            'appetite_loss',
            'vomiting',
            'diarrhea',
            'breathing_difficulty',
            'eye_discharge',
            'ear_infection',
        ];
    }
}
