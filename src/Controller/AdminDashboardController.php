<?php

namespace App\Controller;

use App\Entity\IpCamera;
use App\Entity\IoTData;
use App\Entity\DogDetection;
use App\Entity\ObservationStation;
use App\Entity\Statistics;
use App\Entity\Alert;
use App\Repository\IpCameraRepository;
use App\Repository\IoTDataRepository;
use App\Repository\DogDetectionRepository;
use App\Repository\ObservationStationRepository;
use App\Repository\StatisticsRepository;
use App\Repository\AlertRepository;
use App\Service\PTZControlService;
use App\Service\StreamTranscoderService;
use App\Service\DirectRtspService;
use App\Service\MercureIoTPublisher;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/admin')]
class AdminDashboardController extends AbstractController
{
    #[Route('', name: 'app_admin_dashboard', methods: ['GET'])]
    public function index(
        ObservationStationRepository $stationRepo,
        IpCameraRepository $cameraRepo,
        AlertRepository $alertRepo,
        DogDetectionRepository $detectionRepo
    ): Response {
        $stations = $stationRepo->findAll();
        $cameras = $cameraRepo->findAll();
        $activeAlerts = $alertRepo->findBy(['statut' => 'unread']);
        $recentDetections = $detectionRepo->findRecentDetections(10);
        $healthAlerts = $detectionRepo->findHealthAlerts();

        return $this->render('admin/dashboard.html.twig', [
            'stations' => $stations,
            'cameras' => $cameras,
            'activeAlerts' => $activeAlerts,
            'recentDetections' => $recentDetections,
            'healthAlerts' => $healthAlerts,
            'page_title' => 'Admin Dashboard',
        ]);
    }

    #[Route('/dashboard/data', name: 'app_admin_dashboard_data', methods: ['GET'])]
    public function getDashboardData(
        ObservationStationRepository $stationRepo,
        IpCameraRepository $cameraRepo,
        AlertRepository $alertRepo,
        DogDetectionRepository $detectionRepo,
        IoTDataRepository $iotRepo
    ): JsonResponse {
        $stations = $stationRepo->findAll();
        $activeStations = array_filter($stations, fn($s) => $s->getStatut() === 'active');
        
        $cameras = $cameraRepo->findAll();
        $activeCameras = array_filter($cameras, fn($c) => $c->getStatus() === 'active');
        
        $alerts = $alertRepo->findAll();
        $unreadAlerts = array_filter($alerts, fn($a) => $a->getStatut() === 'unread');
        
        $detections = $detectionRepo->findRecentDetections(100);
        $seriousDetections = array_filter($detections, fn($d) => $d->getSeverity() === 'serious' || $d->getSeverity() === 'critical');
        
        // Get latest IoT data
        $latestIotData = [];
        foreach ($stations as $station) {
            $data = $iotRepo->findLatestByStation($station, 1);
            if (!empty($data)) {
                $latestIotData[$station->getCode()] = [
                    'temperature' => $data[0]->getTemperature(),
                    'humidity' => $data[0]->getHumidity(),
                    'pressure' => $data[0]->getPressure(),
                    'battery' => $data[0]->getBatteryLevel(),
                    'lastSeen' => $data[0]->getLastSeen()?->format('Y-m-d H:i:s'),
                ];
            }
        }

        return new JsonResponse([
            'stations' => [
                'total' => count($stations),
                'active' => count($activeStations),
                'inactive' => count(array_filter($stations, fn($s) => $s->getStatut() === 'inactive')),
                'maintenance' => count(array_filter($stations, fn($s) => $s->getStatut() === 'maintenance')),
            ],
            'cameras' => [
                'total' => count($cameras),
                'active' => count($activeCameras),
                'inactive' => count(array_filter($cameras, fn($c) => $c->getStatus() === 'inactive')),
            ],
            'alerts' => [
                'unread' => count($unreadAlerts),
                'total' => count($alerts),
            ],
            'detections' => [
                'total' => count($detections),
                'serious' => count($seriousDetections),
                'today' => $detectionRepo->countTodayDetections(),
            ],
            'iotData' => $latestIotData,
        ]);
    }

    #[Route('/map', name: 'app_admin_map', methods: ['GET'])]
    public function map(ObservationStationRepository $stationRepo, IpCameraRepository $cameraRepo): Response
    {
        $stations = $stationRepo->findAll();
        $cameras = $cameraRepo->findAll();

        return $this->render('admin/map.html.twig', [
            'stations' => $stations,
            'cameras' => $cameras,
            'page_title' => 'IoT Station Map',
        ]);
    }

    #[Route('/map/data', name: 'app_admin_map_data', methods: ['GET'])]
    public function getMapData(ObservationStationRepository $stationRepo): JsonResponse
    {
        $stations = $stationRepo->findAll();
        $mapData = [];

        foreach ($stations as $station) {
            $localization = $station->getLocalisation();
            if ($localization && strpos($localization, ',') !== false) {
                $parts = explode(',', $localization);
                if (count($parts) === 2) {
                    $mapData[] = [
                        'id' => $station->getId(),
                        'code' => $station->getCode(),
                        'zone' => $station->getZone(),
                        'status' => $station->getStatut(),
                        'lat' => trim($parts[0]),
                        'lng' => trim($parts[1]),
                    ];
                }
            }
        }

        return new JsonResponse($mapData);
    }

    #[Route('/cameras', name: 'app_admin_cameras', methods: ['GET'])]
    public function cameras(IpCameraRepository $cameraRepo): Response
    {
        $cameras = $cameraRepo->findAll();

        return $this->render('admin/cameras.html.twig', [
            'cameras' => $cameras,
            'page_title' => 'IP Cameras',
        ]);
    }

    #[Route('/cameras/{id}', name: 'app_admin_camera_view', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function viewCamera(IpCamera $camera): Response
    {
        return $this->render('admin/camera_view.html.twig', [
            'camera' => $camera,
            'page_title' => 'Camera: ' . $camera->getName(),
        ]);
    }

    #[Route('/cameras/{id}/stream', name: 'app_admin_camera_stream', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getCameraStream(IpCamera $camera): JsonResponse
    {
        return new JsonResponse([
            'streamUrl' => $camera->getFullStreamUrl(),
            'snapshotUrl' => $camera->getFullSnapshotUrl(),
            'name' => $camera->getName(),
            'status' => $camera->getStatus(),
            'ptzCapabilities' => $camera->getPtzCapabilities(),
        ]);
    }

    #[Route('/cameras/{id}/control', name: 'app_admin_camera_control', methods: ['POST'])]
    public function controlCamera(
        Request $request,
        IpCamera $camera,
        PTZControlService $ptzService,
        StreamTranscoderService $streamTranscoder,
        DirectRtspService $directRtspService
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        $action = $data['action'] ?? '';
        
        // Get PTZ capabilities or use defaults if not set
        $ptzCapabilities = $camera->getPtzCapabilities();
        if (empty($ptzCapabilities)) {
            // Auto-enable PTZ for cameras with IP configured
            if ($camera->getIpAddress()) {
                $ptzCapabilities = ['ptz', 'zoom', 'presets'];
            } else {
                $ptzCapabilities = [];
            }
        }
        
        $response = [
            'success' => false,
            'message' => 'Unknown action',
            'action' => $action,
        ];

        // Handle PTZ actions - always try to execute regardless of capabilities check
        switch ($action) {
            case 'ptz_up':
            case 'ptz_down':
            case 'ptz_left':
            case 'ptz_right':
            case 'zoom_in':
            case 'zoom_out':
            case 'ptz_stop':
            case 'ptz_home':
                // Get timeout from request for hold-to-move functionality (default 2 seconds)
                $timeout = isset($data['timeout']) ? max(1, min(10, (int)$data['timeout'])) : 2;
                
                // Always try to send command - the service will handle errors gracefully
                $result = $ptzService->sendPTZCommand($camera, $action, $timeout);
                $response = [
                    'success' => $result['success'] ?? false,
                    'message' => $result['message'] ?? 'PTZ command executed',
                    'action' => $action,
                    'protocol' => $result['protocol'] ?? 'unknown',
                    'camera_brand' => $result['brand'] ?? 'unknown',
                    'camera_ip' => $camera->getIpAddress() ?? 'not configured',
                    'camera_port' => $camera->getPort() ?? 80,
                    'camera_username' => $camera->getUsername() ?? 'not set',
                    'demo' => $result['demo'] ?? false,
                    'error' => $result['error'] ?? null,
                    'status_code' => $result['status_code'] ?? null,
                    'timeout' => $timeout, // Echo back the timeout used
                ];
                break;
            case 'preset':
                $preset = $data['preset'] ?? 1;
                $result = $ptzService->gotoPreset($camera, $preset);
                $response = [
                    'success' => $result['success'] ?? false,
                    'message' => $result['message'] ?? "Moving to preset $preset",
                    'action' => 'preset',
                    'preset' => $preset,
                    'demo' => $result['demo'] ?? false,
                ];
                break;
            case 'snapshot':
                $response = [
                    'success' => true,
                    'message' => 'Taking snapshot',
                    'snapshotUrl' => $camera->getFullSnapshotUrl() . '?t=' . time(),
                ];
                break;
            case 'stream_start':
                try {
                    $success = $streamTranscoder->startTranscoding($camera);
                    $response = [
                        'success' => $success,
                        'message' => $success ? 'Stream started successfully' : 'Failed to start stream',
                        'action' => 'stream_start',
                    ];
                } catch (\Exception $e) {
                    $response = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'message' => 'Error starting stream',
                        'action' => 'stream_start',
                    ];
                }
                break;
            case 'stream_stop':
                try {
                    $success = $streamTranscoder->stopTranscoding($camera->getId());
                    $response = [
                        'success' => $success,
                        'message' => $success ? 'Stream stopped successfully' : 'Failed to stop stream',
                        'action' => 'stream_stop',
                    ];
                } catch (\Exception $e) {
                    $response = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'message' => 'Error stopping stream',
                        'action' => 'stream_stop',
                    ];
                }
                break;
            case 'stream_restart':
                try {
                    // Stop first, then start
                    $streamTranscoder->stopTranscoding($camera->getId());
                    sleep(1);
                    $success = $streamTranscoder->startTranscoding($camera);
                    $response = [
                        'success' => $success,
                        'message' => $success ? 'Stream restarted successfully' : 'Failed to restart stream',
                        'action' => 'stream_restart',
                    ];
                } catch (\Exception $e) {
                    $response = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'message' => 'Error restarting stream',
                        'action' => 'stream_restart',
                    ];
                }
                break;
            case 'stream_refresh':
                // For refresh, we just return success - the client will handle reloading the stream
                $response = [
                    'success' => true,
                    'message' => 'Stream refresh requested',
                    'action' => 'stream_refresh',
                    'streamUrl' => $camera->getHlsStreamUrl() ?: $camera->getFullStreamUrl(),
                ];
                break;
            case 'vlc_start':
                try {
                    $success = $streamTranscoder->startVlcTranscoding($camera);
                    $vlcUrl = $streamTranscoder->getVlcMjpegUrl($camera);
                    $response = [
                        'success' => $success,
                        'message' => $success ? 'VLC stream started successfully' : 'Failed to start VLC stream',
                        'action' => 'vlc_start',
                        'vlcUrl' => $vlcUrl,
                    ];
                } catch (\Exception $e) {
                    $response = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'message' => 'Error starting VLC stream',
                        'action' => 'vlc_start',
                    ];
                }
                break;
            case 'vlc_stop':
                try {
                    $success = $streamTranscoder->stopVlcTranscoding($camera->getId());
                    $response = [
                        'success' => $success,
                        'message' => $success ? 'VLC stream stopped successfully' : 'Failed to stop VLC stream',
                        'action' => 'vlc_stop',
                    ];
                } catch (\Exception $e) {
                    $response = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'message' => 'Error stopping VLC stream',
                        'action' => 'vlc_stop',
                    ];
                }
                break;
            case 'cv_stream_start':
                try {
                    $success = $streamTranscoder->startCvStream($camera);
                    $response = [
                        'success' => $success,
                        'message' => $success ? 'CV stream started successfully' : 'Failed to start CV stream',
                        'action' => 'cv_stream_start',
                    ];
                } catch (\Exception $e) {
                    $response = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'message' => 'Error starting CV stream',
                        'action' => 'cv_stream_start',
                    ];
                }
                break;
            case 'cv_stream_stop':
                try {
                    $success = $streamTranscoder->stopCvStream($camera->getId());
                    $response = [
                        'success' => $success,
                        'message' => $success ? 'CV stream stopped successfully' : 'Failed to stop CV stream',
                        'action' => 'cv_stream_stop',
                    ];
                } catch (\Exception $e) {
                    $response = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'message' => 'Error stopping CV stream',
                        'action' => 'cv_stream_stop',
                    ];
                }
                break;
            case 'get_stream_status':
                // Return full stream status including VLC and CV
                $status = $streamTranscoder->getFullStreamStatus($camera->getId());
                $rtspStatus = $directRtspService->getStreamStatus($camera);
                $response = [
                    'success' => true,
                    'message' => 'Stream status retrieved',
                    'action' => 'get_stream_status',
                    'status' => $status,
                    'rtspStatus' => $rtspStatus,
                    'hlsUrl' => $camera->getHlsStreamUrl(),
                    'vlcUrl' => $streamTranscoder->getVlcMjpegUrl($camera),
                    'directRtspUrl' => $rtspStatus['mjpegUrl'],
                ];
                break;
            case 'rtsp_start':
                // Start direct RTSP stream
                try {
                    $success = $directRtspService->startDirectRtspStream($camera);
                    $status = $directRtspService->getStreamStatus($camera);
                    $response = [
                        'success' => $success,
                        'message' => $success ? 'Direct RTSP stream started' : 'Failed to start RTSP stream',
                        'action' => 'rtsp_start',
                        'streamUrl' => $status['mjpegUrl'],
                        'status' => $status,
                    ];
                } catch (\Exception $e) {
                    $response = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'message' => 'Error starting RTSP stream',
                        'action' => 'rtsp_start',
                    ];
                }
                break;
            case 'rtsp_stop':
                // Stop direct RTSP stream
                try {
                    $success = $directRtspService->stopDirectRtspStream($camera->getId());
                    $response = [
                        'success' => $success,
                        'message' => $success ? 'Direct RTSP stream stopped' : 'Failed to stop RTSP stream',
                        'action' => 'rtsp_stop',
                    ];
                } catch (\Exception $e) {
                    $response = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'message' => 'Error stopping RTSP stream',
                        'action' => 'rtsp_stop',
                    ];
                }
                break;
            case 'rtsp_restart':
                // Restart direct RTSP stream - completely reconnects
                try {
                    $success = $directRtspService->restartDirectRtspStream($camera);
                    $status = $directRtspService->getStreamStatus($camera);
                    $response = [
                        'success' => $success,
                        'message' => $success ? 'Direct RTSP stream restarted - reconnected to live feed' : 'Failed to restart RTSP stream',
                        'action' => 'rtsp_restart',
                        'streamUrl' => $status['mjpegUrl'],
                        'status' => $status,
                    ];
                } catch (\Exception $e) {
                    $response = [
                        'success' => false,
                        'error' => $e->getMessage(),
                        'message' => 'Error restarting RTSP stream',
                        'action' => 'rtsp_restart',
                    ];
                }
                break;
            case 'rtsp_status':
                // Get direct RTSP stream status
                $status = $directRtspService->getStreamStatus($camera);
                $response = [
                    'success' => true,
                    'message' => 'RTSP stream status',
                    'action' => 'rtsp_status',
                    'status' => $status,
                    'online' => $status['running'] && $status['fileExists'],
                ];
                break;
            default:
                $response['message'] = 'Invalid action';
        }

        return new JsonResponse($response);
    }

    #[Route('/stations/{id}/data', name: 'app_admin_station_data', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function getStationData(
        ObservationStation $station,
        IoTDataRepository $iotRepo,
        Request $request
    ): JsonResponse {
        // Support hours and limit parameters
        $hours = $request->query->getInt('hours', 1);
        $limit = $request->query->getInt('limit', 50);
        
        // Cap limits
        $hours = min(max($hours, 1), 168); // 1 hour to 7 days
        $limit = min(max($limit, 1), 1000);
        
        $latestData = $iotRepo->findLatestByStation($station, $limit, $hours * 3600);
        
        $data = [];
        foreach ($latestData as $item) {
            $data[] = [
                'id' => $item->getId(),
                'temperature' => $item->getTemperature(),
                'humidity' => $item->getHumidity(),
                'pressure' => $item->getPressure(),
                'batteryLevel' => $item->getBatteryLevel(),
                'signalStrength' => $item->getSignalStrength(),
                'distance' => $item->getDistance(),
                'dogDetected' => $item->isDogDetected(),
                'foodDispensed' => $item->isFoodDispensed(),
                'deviceType' => $item->getDeviceType(),
                'deviceId' => $item->getDeviceId(),
                'firmwareVersion' => $item->getFirmwareVersion(),
                'lastSeen' => $item->getLastSeen()?->format('Y-m-d H:i:s'),
                'lastSeenIso' => $item->getLastSeen()?->format(DATE_ATOM),
                'createdAt' => $item->getCreatedAt()->format('Y-m-d H:i:s'),
                'createdAtIso' => $item->getCreatedAt()->format(DATE_ATOM),
            ];
        }

        return new JsonResponse([
            'station' => [
                'id' => $station->getId(),
                'code' => $station->getCode(),
                'zone' => $station->getZone(),
                'status' => $station->getStatut(),
            ],
            'data' => $data,
        ]);
    }

    #[Route('/detections', name: 'app_admin_detections', methods: ['GET'])]
    public function detections(
        DogDetectionRepository $detectionRepo,
        IpCameraRepository $cameraRepo,
        Request $request
    ): Response {
        $severity = $request->query->get('severity');
        $cameraId = $request->query->get('camera');
        
        if ($severity) {
            $detections = $detectionRepo->findBy([], ['detectedAt' => 'DESC']);
            $detections = array_filter($detections, fn($d) => $d->getSeverity() === $severity);
        } elseif ($cameraId) {
            $camera = $cameraRepo->find((int) $cameraId);
            $detections = $camera ? $detectionRepo->findByCamera($camera) : [];
        } else {
            $detections = $detectionRepo->findRecentDetections(100);
        }

        return $this->render('admin/detections.html.twig', [
            'detections' => $detections,
            'page_title' => 'Dog Detections',
        ]);
    }

    #[Route('/detections/recent', name: 'app_admin_detections_recent', methods: ['GET'])]
    public function getRecentDetections(DogDetectionRepository $detectionRepo): JsonResponse
    {
        $detections = $detectionRepo->findRecentDetections(50);
        
        $data = [];
        foreach ($detections as $detection) {
            $data[] = [
                'id' => $detection->getId(),
                'behaviorType' => $detection->getBehaviorType(),
                'confidence' => $detection->getConfidence(),
                'healthCondition' => $detection->getHealthCondition(),
                'severity' => $detection->getSeverity(),
                'description' => $detection->getDescription(),
                'cameraName' => $detection->getCamera()?->getName(),
                'detectedAt' => $detection->getDetectedAt()->format('Y-m-d H:i:s'),
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/statistics', name: 'app_admin_statistics', methods: ['GET'])]
    public function statistics(
        ObservationStationRepository $stationRepo,
        IpCameraRepository $cameraRepo,
        DogDetectionRepository $detectionRepo,
        IoTDataRepository $iotRepo,
        StatisticsRepository $statsRepo
    ): Response {
        $stations = $stationRepo->findAll();
        $cameras = $cameraRepo->findAll();
        $detections = $detectionRepo->findRecentDetections(100);
        
        // Calculate statistics
        $behaviorStats = $detectionRepo->countByBehaviorType();
        $healthStats = $detectionRepo->countByHealthCondition();
        
        // Get temperature trends (last 7 days)
        $startDate = new \DateTime('-7 days');
        $endDate = new \DateTime();
        $avgTemp = $iotRepo->getAverageTemperature($startDate, $endDate);
        $avgHumidity = $iotRepo->getAverageHumidity($startDate, $endDate);

        return $this->render('admin/statistics.html.twig', [
            'stations' => $stations,
            'cameras' => $cameras,
            'detections' => $detections,
            'behaviorStats' => $behaviorStats,
            'healthStats' => $healthStats,
            'avgTemperature' => $avgTemp,
            'avgHumidity' => $avgHumidity,
            'page_title' => 'Statistics',
        ]);
    }

    #[Route('/statistics/data', name: 'app_admin_statistics_data', methods: ['GET'])]
    public function getStatisticsData(
        Request $request,
        ObservationStationRepository $stationRepo,
        IpCameraRepository $cameraRepo,
        DogDetectionRepository $detectionRepo,
        IoTDataRepository $iotRepo
    ): JsonResponse {
        $period = $request->query->get('period', '7'); // days
        
        $startDate = new \DateTime('-'.$period.' days');
        $endDate = new \DateTime();
        
        $stations = $stationRepo->findAll();
        $cameras = $cameraRepo->findAll();
        
        // Get IoT data for the period
        $iotData = [];
        foreach ($stations as $station) {
            $data = $iotRepo->findByDateRange($startDate, $endDate, $station);
            if (!empty($data)) {
                $temps = array_filter(array_map(fn($d) => $d->getTemperature(), $data));
                $humidities = array_filter(array_map(fn($d) => $d->getHumidity(), $data));
                
                $iotData[$station->getCode()] = [
                    'avgTemp' => !empty($temps) ? array_sum($temps) / count($temps) : null,
                    'avgHumidity' => !empty($humidities) ? array_sum($humidities) / count($humidities) : null,
                    'readings' => count($data),
                ];
            }
        }

        // Get detection stats
        $detections = $detectionRepo->findByDateRange($startDate, $endDate);
        $behaviorCounts = [];
        $healthCounts = [];
        $severityCounts = ['normal' => 0, 'low' => 0, 'medium' => 0, 'serious' => 0, 'critical' => 0];
        
        foreach ($detections as $detection) {
            $behavior = $detection->getBehaviorType() ?? 'unknown';
            $behaviorCounts[$behavior] = ($behaviorCounts[$behavior] ?? 0) + 1;
            
            if ($detection->getHealthCondition()) {
                $health = $detection->getHealthCondition();
                $healthCounts[$health] = ($healthCounts[$health] ?? 0) + 1;
            }
            
            $severity = $detection->getSeverity() ?? 'normal';
            if (isset($severityCounts[$severity])) {
                $severityCounts[$severity]++;
            }
        }

        return new JsonResponse([
            'period' => $period,
            'stations' => [
                'total' => count($stations),
                'active' => count(array_filter($stations, fn($s) => $s->getStatut() === 'active')),
            ],
            'cameras' => [
                'total' => count($cameras),
                'active' => count(array_filter($cameras, fn($c) => $c->getStatus() === 'active')),
            ],
            'iotData' => $iotData,
            'detections' => [
                'total' => count($detections),
                'byBehavior' => $behaviorCounts,
                'byHealth' => $healthCounts,
                'bySeverity' => $severityCounts,
            ],
        ]);
    }

    #[Route('/system-alerts', name: 'app_admin_system_alerts', methods: ['GET'])]
    public function alerts(AlertRepository $alertRepo): Response
    {
        $alerts = $alertRepo->findAll();

        return $this->render('admin/alerts.html.twig', [
            'alerts' => $alerts,
            'page_title' => 'Alerts',
        ]);
    }

    #[Route('/health-alerts', name: 'app_admin_health_alerts', methods: ['GET'])]
    public function healthAlerts(DogDetectionRepository $detectionRepo): Response
    {
        $healthAlerts = $detectionRepo->findSeriousDetections();

        return $this->render('admin/health_alerts.html.twig', [
            'healthAlerts' => $healthAlerts,
            'page_title' => 'Health Alerts',
        ]);
    }

    // API endpoints for ESP32 IoT devices
    #[Route('/api/iot/data', name: 'app_api_iot_data', methods: ['POST'])]
    public function receiveIoTData(
        Request $request,
        EntityManagerInterface $entityManager,
        ObservationStationRepository $stationRepo,
        MercureIoTPublisher $mercurePublisher
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return new JsonResponse(['error' => 'Invalid data'], 400);
        }

        $stationCode = $data['station_code'] ?? null;
        $station = $stationCode ? $stationRepo->findOneBy(['code' => $stationCode]) : null;

        if (!$station) {
            return new JsonResponse(['error' => 'Station not found'], 404);
        }

        $iotData = new IoTData();
        $iotData->setStation($station);
        $iotData->setTemperature($data['temperature'] ?? null);
        $iotData->setHumidity($data['humidity'] ?? null);
        $iotData->setPressure($data['pressure'] ?? null);
        $iotData->setBatteryLevel($data['battery'] ?? null);
        $iotData->setSignalStrength($data['signal_strength'] ?? null);
        $iotData->setDeviceType($data['device_type'] ?? 'ESP32');
        $iotData->setDeviceId($data['device_id'] ?? null);
        $iotData->setFirmwareVersion($data['firmware_version'] ?? null);
        $iotData->setAdditionalSensors($data['additional_sensors'] ?? null);
        
        // Dog feeder specific fields
        if (isset($data['distance'])) {
            $iotData->setDistance((string)$data['distance']);
        }
        if (isset($data['dog_detected'])) {
            $iotData->setDogDetected((bool)$data['dog_detected']);
        }
        if (isset($data['food_dispensed'])) {
            $iotData->setFoodDispensed((bool)$data['food_dispensed']);
        }
        
        $iotData->setLastSeen(new \DateTime());
        $iotData->setCreatedAt(new \DateTime());

        $entityManager->persist($iotData);
        $entityManager->flush();

        // Publish real-time update via Mercure
        $mercurePublisher->publishIoTData($iotData);

        return new JsonResponse([
            'success' => true,
            'message' => 'Data received',
            'id' => $iotData->getId(),
        ]);
    }

    // API endpoint for receiving detection data from CV model
    #[Route('/api/detection', name: 'app_api_detection', methods: ['POST'])]
    public function receiveDetection(
        Request $request,
        EntityManagerInterface $entityManager,
        IpCameraRepository $cameraRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return new JsonResponse(['error' => 'Invalid data'], 400);
        }

        $cameraId = $data['camera_id'] ?? null;
        $camera = $cameraId ? $cameraRepo->find($cameraId) : null;
        $alertCreated = false;
        $healthConditionRaw = trim((string) ($data['health_condition'] ?? ''));
        $healthCondition = strtolower($healthConditionRaw);
        $severity = strtolower(trim((string) ($data['severity'] ?? 'normal')));
        $metadata = isset($data['metadata']) && is_array($data['metadata']) ? $data['metadata'] : null;

        $detection = new DogDetection();
        
        if ($camera) {
            $detection->setCamera($camera);
        }
        
        $detection->setBehaviorType($data['behavior_type'] ?? 'unknown');
        $detection->setConfidence($data['confidence'] ?? 0);
        $detection->setBoundingBox($data['bounding_box'] ?? null);
        $detection->setDetectedObject($data['detected_object'] ?? null);
        $detection->setHealthCondition($healthConditionRaw !== '' ? $healthConditionRaw : null);
        $detection->setHealthSymptoms($data['health_symptoms'] ?? null);
        $detection->setSeverity($data['severity'] ?? 'normal');
        $detection->setDescription($data['description'] ?? null);
        $detection->setImagePath($data['image_path'] ?? null);
        $detection->setMetadata($metadata);
        $detection->setDetectedAt(new \DateTime());
        $detection->setCreatedAt(new \DateTime());

        $entityManager->persist($detection);

        // Keep /admin/api/detection consistent with /admin/stations/api/detection:
        // create HEALTH_ALERT rows for ill detections so they appear in /admin/alerts.
        $isIll = $healthCondition !== '' && !in_array($healthCondition, ['healthy', 'normal', 'ok', 'unknown'], true);
        if ($camera && $camera->getStation() && $isIll) {
            $station = $camera->getStation();
            $cooldownSeconds = isset($data['alert_cooldown_seconds'])
                ? max(5, min(3600, (int) $data['alert_cooldown_seconds']))
                : 60;

            $latestAlert = $entityManager->getRepository(Alert::class)->findOneBy(
                ['station' => $station, 'type' => 'HEALTH_ALERT'],
                ['date' => 'DESC']
            );

            $shouldCreateAlert = true;
            if ($latestAlert && $latestAlert->getDate()) {
                $elapsed = time() - $latestAlert->getDate()->getTimestamp();
                if ($elapsed < $cooldownSeconds) {
                    $shouldCreateAlert = false;
                }
            }

            if ($shouldCreateAlert) {
                $symptoms = $data['health_symptoms'] ?? [];
                if (!is_array($symptoms)) {
                    $symptoms = [];
                }
                $symptoms = array_values(array_filter(array_map('strval', $symptoms), static fn(string $v): bool => $v !== ''));
                $symptomSnippet = '';
                if (!empty($symptoms)) {
                    $symptomSnippet = ' Symptoms: ' . implode(', ', array_slice($symptoms, 0, 4));
                }

                $priority = match ($severity) {
                    'critical' => 3,
                    'serious' => 3,
                    default => 2,
                };

                $alert = new Alert();
                $alert->setType('HEALTH_ALERT');
                $alert->setMessage(sprintf(
                    'Ill dog detected by camera %s at station %s.%s',
                    $camera->getName() ?: ('#' . $camera->getId()),
                    $station->getCode() ?: ('#' . $station->getId()),
                    $symptomSnippet
                ));
                $alert->setPrioritee($priority);
                $alert->setStatut('unread');
                $alert->setDate(new \DateTime());
                $alert->setStation($station);
                $alert->setUserId(null);

                $entityManager->persist($alert);
                $alertCreated = true;
            }
        }

        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Detection received',
            'id' => $detection->getId(),
            'alertCreated' => $alertCreated,
        ]);
    }

    // MJPEG Stream endpoint for direct RTSP playback
    #[Route('/cameras/{id}/mjpeg', name: 'app_admin_camera_mjpeg', methods: ['GET'])]
    public function getMjpegStream(IpCamera $camera): StreamedResponse
    {
        $cameraId = $camera->getId();
        $mjpegFile = '/tmp/rtsp_stream_' . $cameraId . '.mjpg';
        
        return new StreamedResponse(function() use ($mjpegFile) {
            $lastSize = 0;
            
            header('Content-Type: multipart/x-mixed-replace; boundary=myboundary');
            header('Cache-Control: no-cache, no-store, must-revalidate');
            header('Connection: close');
            
            set_time_limit(0);
            
            while (!connection_aborted()) {
                if (file_exists($mjpegFile)) {
                    $currentSize = filesize($mjpegFile);
                    
                    if ($currentSize > $lastSize || $currentSize < $lastSize) {
                        $fp = fopen($mjpegFile, 'rb');
                        if ($fp) {
                            if ($currentSize < $lastSize) {
                                fseek($fp, 0);
                            } else {
                                fseek($fp, $lastSize);
                            }
                            
                            $data = fread($fp, $currentSize - $lastSize);
                            fclose($fp);
                            
                            if ($data) {
                                echo "--myboundary\r\n";
                                echo "Content-Type: image/jpeg\r\n";
                                echo "Content-Length: " . strlen($data) . "\r\n\r\n";
                                echo $data;
                                flush();
                            }
                            
                            $lastSize = $currentSize;
                        }
                    }
                }
                
                usleep(50000); // 20fps
            }
        });
    }

    // Single JPEG frame endpoint for polling-based streaming
    #[Route('/cameras/{id}/frame.jpg', name: 'app_admin_camera_frame', methods: ['GET'])]
    public function getCameraFrame(IpCamera $camera): Response
    {
        $cameraId = $camera->getId();
        $frameFile = '/tmp/rtsp_frame_' . $cameraId . '.jpg';
        $legacyMjpegFile = $this->resolveLegacyMjpegSource((int) $cameraId);
        
        if (!file_exists($frameFile)) {
            $legacyFrame = $legacyMjpegFile ? $this->extractLatestJpegFromMjpeg($legacyMjpegFile) : null;
            if ($legacyFrame === null) {
                return new Response('No frame available', 503, ['Content-Type' => 'text/plain']);
            }

            return new Response($legacyFrame, 200, [
                'Content-Type' => 'image/jpeg',
                'Content-Length' => strlen($legacyFrame),
                'Cache-Control' => 'no-store, no-cache, must-revalidate, private, max-age=0',
                'Pragma' => 'no-cache',
                'Expires' => '0',
                'Last-Modified' => gmdate('D, d M Y H:i:s') . ' GMT',
                'Access-Control-Allow-Origin' => '*',
                'X-Frame-Source' => 'legacy-mjpeg',
            ]);
        }
        
        // Clear stat cache to get fresh file info
        clearstatcache(true, $frameFile);

        $lastModified = @filemtime($frameFile);
        if ($lastModified !== false) {
            $frameAge = time() - $lastModified;
            if ($frameAge > 12) {
                $legacyFrame = $legacyMjpegFile ? $this->extractLatestJpegFromMjpeg($legacyMjpegFile) : null;
                if ($legacyFrame === null) {
                    return new Response('Frame stale', 503, ['Content-Type' => 'text/plain']);
                }

                return new Response($legacyFrame, 200, [
                    'Content-Type' => 'image/jpeg',
                    'Content-Length' => strlen($legacyFrame),
                    'Cache-Control' => 'no-store, no-cache, must-revalidate, private, max-age=0',
                    'Pragma' => 'no-cache',
                    'Expires' => '0',
                    'Last-Modified' => gmdate('D, d M Y H:i:s') . ' GMT',
                    'Access-Control-Allow-Origin' => '*',
                    'X-Frame-Source' => 'legacy-mjpeg',
                    'X-Frame-Age-Seconds' => (string) $frameAge,
                ]);
            }
        } else {
            $frameAge = null;
        }
        
        // Read a valid JPEG frame (retry to avoid partial reads while FFmpeg writes).
        $content = null;
        for ($attempt = 0; $attempt < 2; $attempt++) {
            $candidate = @file_get_contents($frameFile);
            if ($candidate !== false && strlen($candidate) > 4) {
                $hasJpegMarkers = substr($candidate, 0, 2) === "\xFF\xD8"
                    && substr($candidate, -2) === "\xFF\xD9";
                if ($hasJpegMarkers) {
                    $content = $candidate;
                    break;
                }
            }
            usleep(8000);
        }

        if ($content === null) {
            $legacyFrame = $legacyMjpegFile ? $this->extractLatestJpegFromMjpeg($legacyMjpegFile) : null;
            if ($legacyFrame === null) {
                return new Response('Frame unavailable', 503, ['Content-Type' => 'text/plain']);
            }
            $content = $legacyFrame;
        }
        
        // Set aggressive cache-busting headers
        $response = new Response($content, 200, [
            'Content-Type' => 'image/jpeg',
            'Content-Length' => strlen($content),
            'Cache-Control' => 'no-store, no-cache, must-revalidate, private, max-age=0',
            'Pragma' => 'no-cache',
            'Expires' => '0',
            'Last-Modified' => gmdate('D, d M Y H:i:s') . ' GMT',
            'Access-Control-Allow-Origin' => '*',
            'X-Frame-Age-Seconds' => $frameAge !== null ? (string) $frameAge : 'unknown',
        ]);
        
        return $response;
    }

    private function resolveLegacyMjpegSource(int $cameraId): ?string
    {
        $candidates = [
            $this->getParameter('kernel.project_dir') . '/public/streams/camera_' . $cameraId . '/stream.mjpg',
            '/tmp/rtsp_stream_' . $cameraId . '.mjpg',
        ];

        foreach ($candidates as $candidate) {
            if (is_readable($candidate) && (@filesize($candidate) ?: 0) > 0) {
                return $candidate;
            }
        }

        return null;
    }

    private function extractLatestJpegFromMjpeg(string $mjpegFile): ?string
    {
        if (!is_readable($mjpegFile)) {
            return null;
        }

        $size = @filesize($mjpegFile);
        if ($size === false || $size <= 0) {
            return null;
        }

        $readWindow = 2 * 1024 * 1024; // Last 2MB is enough to find the latest complete frame.
        $offset = max(0, $size - $readWindow);

        $handle = @fopen($mjpegFile, 'rb');
        if ($handle === false) {
            return null;
        }

        if ($offset > 0) {
            @fseek($handle, $offset);
        }
        $chunk = @stream_get_contents($handle);
        @fclose($handle);

        if (!is_string($chunk) || $chunk === '') {
            return null;
        }

        $eoi = strrpos($chunk, "\xFF\xD9");
        if ($eoi === false) {
            return null;
        }

        $prefix = substr($chunk, 0, $eoi + 2);
        $soi = strrpos($prefix, "\xFF\xD8");
        if ($soi === false) {
            return null;
        }

        $jpeg = substr($prefix, $soi);
        if ($jpeg === '' || strlen($jpeg) < 200) {
            return null;
        }

        return $jpeg;
    }
}
