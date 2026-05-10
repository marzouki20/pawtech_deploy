<?php

namespace App\Service;

use App\Entity\Statistics;
use App\Entity\ObservationStation;
use App\Entity\IpCamera;
use App\Entity\DogDetection;
use App\Entity\IoTData;
use App\Repository\StatisticsRepository;
use App\Repository\IoTDataRepository;
use App\Repository\DogDetectionRepository;
use App\Repository\ObservationStationRepository;
use App\Repository\IpCameraRepository;
use Doctrine\ORM\EntityManagerInterface;

class StatisticsService
{
    private EntityManagerInterface $entityManager;
    private StatisticsRepository $statsRepo;
    private IoTDataRepository $iotRepo;
    private DogDetectionRepository $detectionRepo;
    private ObservationStationRepository $stationRepo;
    private IpCameraRepository $cameraRepo;

    public function __construct(
        EntityManagerInterface $entityManager,
        StatisticsRepository $statsRepo,
        IoTDataRepository $iotRepo,
        DogDetectionRepository $detectionRepo,
        ObservationStationRepository $stationRepo,
        IpCameraRepository $cameraRepo
    )
    {
        $this->entityManager = $entityManager;
        $this->statsRepo = $statsRepo;
        $this->iotRepo = $iotRepo;
        $this->detectionRepo = $detectionRepo;
        $this->stationRepo = $stationRepo;
        $this->cameraRepo = $cameraRepo;
    }

    /**
     * Generate daily statistics
     */
    public function generateDailyStatistics(): void
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);
        $tomorrow = clone $today;
        $tomorrow->modify('+1 day');

        // Station statistics
        $this->generateStationStats($today, $tomorrow);

        // Camera statistics
        $this->generateCameraStats($today, $tomorrow);

        // Detection statistics
        $this->generateDetectionStats($today, $tomorrow);

        // IoT statistics
        $this->generateIoTStats($today, $tomorrow);

        $this->entityManager->flush();
    }

    /**
     * Generate station statistics
     */
    private function generateStationStats(\DateTime $start, \DateTime $end): void
    {
        $stations = $this->stationRepo->findAll();
        
        $activeCount = count(array_filter($stations, fn($s) => $s->getStatut() === 'active'));
        $inactiveCount = count(array_filter($stations, fn($s) => $s->getStatut() === 'inactive'));

        // Active stations
        $this->updateOrCreateStat('station_online', $start, [
            'count' => $activeCount,
        ], $activeCount);

        // Inactive stations
        $this->updateOrCreateStat('station_offline', $start, [
            'count' => $inactiveCount,
        ], $inactiveCount);
    }

    /**
     * Generate camera statistics
     */
    private function generateCameraStats(\DateTime $start, \DateTime $end): void
    {
        $cameras = $this->cameraRepo->findAll();
        
        $activeCount = count(array_filter($cameras, fn($c) => $c->getStatus() === 'active'));

        $this->updateOrCreateStat('camera_activity', $start, [
            'active_cameras' => $activeCount,
            'total_cameras' => count($cameras),
        ], $activeCount);
    }

    /**
     * Generate detection statistics
     */
    private function generateDetectionStats(\DateTime $start, \DateTime $end): void
    {
        $detections = $this->detectionRepo->findByDateRange($start, $end);
        
        $totalDetections = count($detections);
        
        // Count by health condition
        $healthCounts = [];
        $behaviorCounts = [];
        $severityCounts = ['normal' => 0, 'low' => 0, 'medium' => 0, 'serious' => 0, 'critical' => 0];

        foreach ($detections as $detection) {
            $health = $detection->getHealthCondition();
            if ($health) {
                $healthCounts[$health] = ($healthCounts[$health] ?? 0) + 1;
            }

            $behavior = $detection->getBehaviorType();
            if ($behavior) {
                $behaviorCounts[$behavior] = ($behaviorCounts[$behavior] ?? 0) + 1;
            }

            $severity = $detection->getSeverity() ?? 'normal';
            if (isset($severityCounts[$severity])) {
                $severityCounts[$severity]++;
            }
        }

        // Total detections
        $this->updateOrCreateStat('dog_detections', $start, [
            'total' => $totalDetections,
            'by_health' => $healthCounts,
            'by_behavior' => $behaviorCounts,
            'by_severity' => $severityCounts,
        ], $totalDetections);

        // Health alerts (serious + critical)
        $healthAlerts = $severityCounts['serious'] + $severityCounts['critical'];
        $this->updateOrCreateStat('health_alerts', $start, [
            'serious' => $severityCounts['serious'],
            'critical' => $severityCounts['critical'],
            'total' => $healthAlerts,
        ], $healthAlerts);
    }

    /**
     * Generate IoT statistics
     */
    private function generateIoTStats(\DateTime $start, \DateTime $end): void
    {
        $stations = $this->stationRepo->findAll();
        
        $totalTemp = 0;
        $tempCount = 0;
        $totalHumidity = 0;
        $humidityCount = 0;

        foreach ($stations as $station) {
            $data = $this->iotRepo->findByDateRange($start, $end, $station);
            
            foreach ($data as $iotData) {
                $temp = $iotData->getTemperature();
                if ($temp !== null) {
                    $totalTemp += (float) $temp;
                    $tempCount++;
                }

                $humidity = $iotData->getHumidity();
                if ($humidity !== null) {
                    $totalHumidity += (float) $humidity;
                    $humidityCount++;
                }
            }
        }

        $avgTemp = $tempCount > 0 ? $totalTemp / $tempCount : null;
        $avgHumidity = $humidityCount > 0 ? $totalHumidity / $humidityCount : null;

        // Temperature
        $this->updateOrCreateStat('iot_temperature', $start, [
            'average' => $avgTemp,
            'readings' => $tempCount,
        ], $tempCount, $avgTemp);

        // Humidity
        $this->updateOrCreateStat('iot_humidity', $start, [
            'average' => $avgHumidity,
            'readings' => $humidityCount,
        ], $humidityCount, $avgHumidity);
    }

    /**
     * Update or create statistics entry
     */
    private function updateOrCreateStat(
        string $type,
        \DateTime $date,
        array $data,
        int $count,
        ?float $average = null
    ): Statistics {
        $existing = $this->statsRepo->findOneBy([
            'type' => $type,
            'date' => $date,
        ]);

        if ($existing) {
            $existing->setData($data);
            $existing->setCount($count);
            if ($average !== null) {
                $existing->setAverage((string) $average);
            }
            $existing->setUpdatedAt(new \DateTime());
            return $existing;
        }

        $stat = new Statistics();
        $stat->setType($type);
        $stat->setDate($date);
        $stat->setData($data);
        $stat->setCount($count);
        if ($average !== null) {
            $stat->setAverage((string) $average);
        }
        $stat->setCreatedAt(new \DateTime());
        $stat->setUpdatedAt(new \DateTime());

        $this->entityManager->persist($stat);

        return $stat;
    }

    /**
     * Get statistics summary for dashboard
     */
    public function getDashboardSummary(): array
    {
        $today = new \DateTime();
        $today->setTime(0, 0, 0);

        return [
            'stations' => [
                'total' => $this->stationRepo->count([]),
                'active' => $this->stationRepo->count(['statut' => 'active']),
            ],
            'cameras' => [
                'total' => $this->cameraRepo->count([]),
                'active' => $this->cameraRepo->count(['status' => 'active']),
            ],
            'detections' => [
                'today' => $this->detectionRepo->countByDateRange($today, new \DateTime()),
            ],
        ];
    }

    /**
     * Clean up old statistics data
     */
    public function cleanupOldData(int $daysToKeep = 90): int
    {
        $cutoff = new \DateTime();
        $cutoff->modify("-{$daysToKeep} days");

        return $this->entityManager->createQueryBuilder()
            ->delete(Statistics::class, 's')
            ->where('s.date < :cutoff')
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }
}
