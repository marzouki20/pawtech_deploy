<?php

namespace App\Controller;

use App\Entity\Alert;
use App\Entity\ObservationStation;
use App\Entity\IpCamera;
use App\Entity\IoTData;
use App\Entity\IoTDevice;
use App\Entity\DogDetection;
use App\Form\IoTDeviceType;
use App\Form\ObservationStationType;
use App\Form\IpCameraType;
use App\Repository\ObservationStationRepository;
use App\Repository\IpCameraRepository;
use App\Repository\IoTDataRepository;
use App\Repository\IoTDeviceRepository;
use App\Repository\DogDetectionRepository;
use App\Repository\StatisticsRepository;
use App\Service\StreamTranscoderService;
use App\Service\PTZControlService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\NormalizerInterface;

#[Route('/admin/stations')]
final class ObservationStationController extends AbstractController
{
    #[Route('/map', name: 'app_admin_stations_map', methods: ['GET'])]
    public function map(ObservationStationRepository $stationRepo): Response
    {
        $stations = $stationRepo->findAll();
        
        return $this->render('observation_station/map.html.twig', [
            'stations' => $stations,
            'active' => 'map',
            'page_title' => 'Station Map',
        ]);
    }

    #[Route('', name: 'app_admin_stations', methods: ['GET'])]
    public function index(
        ObservationStationRepository $observationStationRepository,
        IpCameraRepository $cameraRepository,
        IoTDataRepository $iotRepository,
        DogDetectionRepository $detectionRepository,
        Request $request
    ): Response
    {
        $statut = $request->query->get('statut');
        
        if ($statut) {
            $stations = $observationStationRepository->findBy(['statut' => $statut]);
        } else {
            $stations = $observationStationRepository->findAll();
        }
        
        // Get cameras
        $cameras = $cameraRepository->findAll();
        
        // Get latest IoT data for all stations
        $iotDataMap = [];
        foreach ($stations as $station) {
            $latestData = $iotRepository->findLatestByStation($station, 1);
            if (!empty($latestData)) {
                $iotDataMap[$station->getId()] = $latestData[0];
            }
        }
        
        // Get recent detections
        $recentDetections = $detectionRepository->findRecentDetections(10);
        
        return $this->render('observation_station/index.html.twig', [
            'observation_stations' => $stations,
            'cameras' => $cameras,
            'iotDataMap' => $iotDataMap,
            'recentDetections' => $recentDetections,
            'active' => 'station',
            'page_title' => 'Stations',
        ]);
    }

    #[Route('/new', name: 'app_admin_stations_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $observationStation = new ObservationStation();
        $form = $this->createForm(ObservationStationType::class, $observationStation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Also create alert if new station is created as inactive
            if ($observationStation->getStatut() === 'inactive') {
                $alert = new Alert();
                $alert->setType('TECHNICAL');
                $alert->setMessage('Station ' . $observationStation->getCode() . ' is inactive');
                $alert->setPrioritee(1);
                $alert->setStatut('unread');
                $alert->setDate(new \DateTime());
                $alert->setStation($observationStation);
                $alert->setUserId(null); // System-generated alert
                $entityManager->persist($alert);
            }

            $entityManager->persist($observationStation);
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_stations', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('observation_station/new.html.twig', [
            'observation_station' => $observationStation,
            'form' => $form,
            'active' => 'station',
            'page_title' => 'New Station',
        ]);
    }

    #[Route('/{id}', name: 'app_admin_stations_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(ObservationStation $observationStation): Response
    {
        return $this->render('observation_station/show.html.twig', [
            'observation_station' => $observationStation,
            'active' => 'station',
            'page_title' => 'Station Details',
        ]);
    }

    #[Route('/{id}/dashboard', name: 'app_admin_station_dashboard', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function dashboard(
        ObservationStation $station,
        IpCameraRepository $cameraRepo,
        IoTDataRepository $iotRepo,
        IoTDeviceRepository $iotDeviceRepo,
        DogDetectionRepository $detectionRepo
    ): Response
    {
        // Get cameras for this station
        $cameras = $cameraRepo->findByStationId($station->getId());
        
        // Get IoT devices for this station
        $iotDevices = $iotDeviceRepo->findByStationId($station->getId());
        
        // Get latest IoT data (last 20 readings)
        $latestIotData = $iotRepo->findLatestByStation($station, 20);
        
        // Get IoT data for charts (last 100 readings)
        $iotDataForCharts = $iotRepo->findLatestByStation($station, 100);
        
        // Get recent detections for this station's cameras
        $detections = [];
        foreach ($cameras as $camera) {
            $cameraDetections = $detectionRepo->findByCamera($camera, 20);
            $detections = array_merge($detections, $cameraDetections);
        }
        
        // Sort detections by timestamp descending
        usort($detections, function($a, $b) {
            return $b->getTimestamp() <=> $a->getTimestamp();
        });
        
        // Note: Statistics entity uses 'type' field instead of station relation
        // Get statistics for this station using IoT data instead
        $statistics = null;
        
        // Get health alerts (detections with health issues)
        $healthAlerts = array_filter($detections, function($d) {
            return $d->getIsHealthIssue() === true;
        });
        
        // Parse localisation to get lat/lng for map
        $localisation = $station->getLocalisation();
        $lat = null;
        $lng = null;
        if ($localisation) {
            $coords = explode(',', $localisation);
            if (count($coords) === 2) {
                $lat = trim($coords[0]);
                $lng = trim($coords[1]);
            }
        }
        
        return $this->render('observation_station/station_dashboard.html.twig', [
            'station' => $station,
            'stationLat' => $lat,
            'stationLng' => $lng,
            'cameras' => $cameras,
            'iotDevices' => $iotDevices,
            'latestIotData' => $latestIotData,
            'iotDataForCharts' => $iotDataForCharts,
            'detections' => array_slice($detections, 0, 20),
            'healthAlerts' => array_slice($healthAlerts, 0, 10),
            'active' => 'station',
            'page_title' => 'Dashboard: ' . $station->getCode(),
        ]);
    }

    // ============ STATION CAMERAS ============
    
    #[Route('/{id}/cameras', name: 'app_admin_station_cameras', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function stationCameras(ObservationStation $station, IpCameraRepository $cameraRepo): Response
    {
        $cameras = $cameraRepo->findByStationId($station->getId());
        
        return $this->render('observation_station/cameras.html.twig', [
            'station' => $station,
            'cameras' => $cameras,
            'active' => 'station',
            'page_title' => 'Cameras: ' . $station->getCode(),
        ]);
    }

    #[Route('/{stationId}/cameras/new', name: 'app_admin_station_camera_new', methods: ['GET', 'POST'], requirements: ['stationId' => '\d+'])]
    public function newStationCamera(Request $request, EntityManagerInterface $entityManager, ObservationStationRepository $stationRepo, int $stationId): Response
    {
        $station = $stationRepo->find($stationId);
        if (!$station) {
            throw $this->createNotFoundException('Station not found');
        }
        
        $camera = new IpCamera();
        $camera->setStation($station);
        $form = $this->createForm(IpCameraType::class, $camera);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $camera->setStatus('inactive');
            $entityManager->persist($camera);
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_station_cameras', ['id' => $stationId], Response::HTTP_SEE_OTHER);
        }

        return $this->render('observation_station/camera_new.html.twig', [
            'camera' => $camera,
            'station' => $station,
            'form' => $form,
            'active' => 'station',
            'page_title' => 'Add Camera to ' . $station->getCode(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_admin_stations_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, ObservationStation $observationStation, EntityManagerInterface $entityManager): Response
    {
        $oldStatut = $observationStation->getStatut();
        $form = $this->createForm(ObservationStationType::class, $observationStation);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check if station became inactive
            if ($observationStation->getStatut() === 'inactive' && $oldStatut !== 'inactive') {
                $alert = new Alert();
                $alert->setType('TECHNICAL');
                $alert->setMessage('Station ' . $observationStation->getCode() . ' is inactive');
                $alert->setPrioritee(1);
                $alert->setStatut('unread');
                $alert->setDate(new \DateTime());
                $alert->setStation($observationStation);
                $alert->setUserId(null); // System-generated alert
                
                $entityManager->persist($alert);
            }
            
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_stations', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('observation_station/edit.html.twig', [
            'observation_station' => $observationStation,
            'form' => $form,
            'active' => 'station',
            'page_title' => 'Edit Station',
        ]);
    }

    #[Route('/{id}', name: 'app_admin_stations_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, ObservationStation $observationStation, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$observationStation->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($observationStation);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_admin_stations', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/search', name: 'app_admin_stations_search', methods: ['GET'])]
    public function searchStations(
        Request $request,
        NormalizerInterface $normalizer,
        ObservationStationRepository $observationStationRepository
    ): JsonResponse {
        $searchValue = $request->get('searchValue', '');
        $stations = $observationStationRepository->findStationByCode($searchValue);
        $jsonContent = $normalizer->normalize($stations, 'json', ['groups' => 'stations']);

        return new JsonResponse($jsonContent);
    }

    // ============ CAMERA MANAGEMENT ============

    #[Route('/cameras', name: 'app_admin_stations_cameras', methods: ['GET'])]
    public function cameras(IpCameraRepository $cameraRepository): Response
    {
        $cameras = $cameraRepository->findAll();
        
        return $this->render('observation_station/cameras.html.twig', [
            'cameras' => $cameras,
            'active' => 'cameras',
            'page_title' => 'IP Cameras',
        ]);
    }

    #[Route('/cameras/new', name: 'app_admin_stations_cameras_new', methods: ['GET', 'POST'])]
    public function newCamera(Request $request, EntityManagerInterface $entityManager): Response
    {
        $camera = new IpCamera();
        $form = $this->createForm(IpCameraType::class, $camera);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $camera->setStatus('inactive');
            $entityManager->persist($camera);
            $entityManager->flush();

            return $this->redirectToRoute('app_admin_stations_cameras', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('observation_station/camera_new.html.twig', [
            'camera' => $camera,
            'form' => $form,
            'active' => 'cameras',
            'page_title' => 'Add IP Camera',
        ]);
    }

    #[Route('/cameras/{id}', name: 'app_admin_stations_cameras_view', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function viewCamera(IpCamera $camera, StreamTranscoderService $transcoder): Response
    {
        $hlsStreamUrl = $transcoder->getStreamUrl($camera->getId());
        $isTranscoding = $transcoder->isTranscoding($camera->getId());
        
        return $this->render('observation_station/camera_view.html.twig', [
            'camera' => $camera,
            'hlsStreamUrl' => $hlsStreamUrl,
            'mjpegStreamUrl' => $camera->getMjpegStreamUrl(),
            'isTranscoding' => $isTranscoding,
            'active' => 'cameras',
            'page_title' => 'Camera: ' . $camera->getName(),
        ]);
    }

    #[Route('/cameras/{id}/stream/start', name: 'app_admin_stations_cameras_stream_start', methods: ['POST'])]
    public function startStream(IpCamera $camera, StreamTranscoderService $transcoder): JsonResponse
    {
        try {
            $success = $transcoder->startTranscoding($camera);
            return new JsonResponse(['success' => $success, 'message' => $success ? 'Stream started' : 'Failed to start']);
        } catch (\Exception $e) {
            return new JsonResponse(['success' => false, 'error' => $e->getMessage()], 500);
        }
    }

    #[Route('/cameras/{id}/stream/stop', name: 'app_admin_stations_cameras_stream_stop', methods: ['POST'])]
    public function stopStream(IpCamera $camera, StreamTranscoderService $transcoder): JsonResponse
    {
        $success = $transcoder->stopTranscoding($camera->getId());
        return new JsonResponse(['success' => $success, 'message' => $success ? 'Stream stopped' : 'Failed to stop']);
    }

    #[Route('/cameras/{id}/live-detections', name: 'app_admin_stations_cameras_live_detections', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function liveDetections(IpCamera $camera): JsonResponse
    {
        $cameraId = (int) $camera->getId();
        $payload = [
            'cameraId' => $cameraId,
            'timestamp' => null,
            'dogCount' => 0,
            'ptzAction' => null,
            'detections' => [],
            'stale' => true,
            'status' => 'offline',
        ];

        $jsonPath = sprintf('/tmp/pawtech_live_detections_camera_%d.json', $cameraId);
        if (!is_readable($jsonPath)) {
            return new JsonResponse($payload);
        }

        $content = @file_get_contents($jsonPath);
        if (!is_string($content) || $content === '') {
            $payload['status'] = 'unreadable';
            return new JsonResponse($payload);
        }

        $decoded = json_decode($content, true);
        if (!is_array($decoded)) {
            $payload['status'] = 'invalid_json';
            return new JsonResponse($payload);
        }

        $timestampRaw = $decoded['timestamp'] ?? null;
        $timestamp = null;
        if (is_string($timestampRaw) && $timestampRaw !== '') {
            try {
                $timestamp = new \DateTimeImmutable($timestampRaw);
            } catch (\Throwable) {
                $timestamp = null;
            }
        }

        $dogCount = (int) ($decoded['dog_count'] ?? 0);
        $detections = isset($decoded['detections']) && is_array($decoded['detections'])
            ? $decoded['detections']
            : [];
        $stale = true;
        if ($timestamp !== null) {
            $stale = (time() - $timestamp->getTimestamp()) > 6;
        }
        if (($decoded['status'] ?? null) === 'stopped') {
            $stale = true;
        }

        $payload = [
            'cameraId' => $cameraId,
            'timestamp' => $timestamp?->format(DATE_ATOM),
            'frameWidth' => isset($decoded['frame_width']) ? (int) $decoded['frame_width'] : null,
            'frameHeight' => isset($decoded['frame_height']) ? (int) $decoded['frame_height'] : null,
            'dogCount' => max(0, $dogCount),
            'ptzAction' => $decoded['ptz_action'] ?? null,
            'detections' => $detections,
            'stale' => $stale,
            'status' => $decoded['status'] ?? ($stale ? 'stale' : 'online'),
        ];

        return new JsonResponse($payload);
    }

    #[Route('/cameras/{id}/stream', name: 'app_admin_stations_cameras_stream', methods: ['GET'], requirements: ['id' => '\\d+'])]
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

    #[Route('/cameras/{id}/control', name: 'app_admin_stations_cameras_control', methods: ['POST'], requirements: ['id' => '\\d+'])]
    public function controlCamera(
        Request $request, 
        IpCamera $camera,
        PTZControlService $ptzService
    ): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        $action = $data['action'] ?? '';
        
        $ptzCapabilities = $camera->getPtzCapabilities() ?? [];
        
        $response = [
            'success' => false,
            'message' => 'Unknown action',
            'action' => $action,
        ];

        switch ($action) {
            case 'ptz_up':
            case 'ptz_down':
            case 'ptz_left':
            case 'ptz_right':
            case 'zoom_in':
            case 'zoom_out':
            case 'ptz_stop':
                // Get timeout from request for hold-to-move functionality (default 2 seconds)
                $timeout = isset($data['timeout']) ? max(1, min(10, (int)$data['timeout'])) : 2;
                
                // Use the PTZ service to send actual command to camera
                $result = $ptzService->sendPTZCommand($camera, $action, $timeout);
                $response = [
                    'success' => $result['success'] ?? false,
                    'message' => $result['message'] ?? 'PTZ command executed',
                    'action' => $action,
                    'camera_brand' => $result['brand'] ?? 'unknown',
                    'demo' => $result['demo'] ?? false,
                    'timeout' => $timeout,
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
                $snapshotUrl = $camera->getFullSnapshotUrl();
                if (!str_starts_with($snapshotUrl, 'data:')) {
                    $snapshotUrl .= '?t=' . time();
                }
                $response = [
                    'success' => true,
                    'message' => 'Snapshot taken',
                    'snapshotUrl' => $snapshotUrl,
                ];
                break;
            case 'ptz_home':
                $result = $ptzService->sendPTZCommand($camera, 'ptz_home');
                $response = [
                    'success' => $result['success'] ?? false,
                    'message' => $result['message'] ?? 'Moving to home position',
                    'action' => 'ptz_home',
                    'demo' => $result['demo'] ?? false,
                ];
                break;
        }

        return new JsonResponse($response);
    }

    // ============ API FOR ESP32 IOT DEVICES ============

    #[Route('/api/iot/data', name: 'app_admin_stations_api_iot', methods: ['POST'])]
    public function receiveIoTData(
        Request $request,
        EntityManagerInterface $entityManager,
        ObservationStationRepository $stationRepo
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
        $iotData->setLastSeen(new \DateTime());
        $iotData->setCreatedAt(new \DateTime());

        $entityManager->persist($iotData);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Data received',
            'id' => $iotData->getId(),
        ]);
    }

    #[Route('/api/detection', name: 'app_admin_stations_api_detection', methods: ['POST'])]
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

        $detection = new \App\Entity\DogDetection();
        
        if ($camera) {
            $detection->setCamera($camera);
        }
        
        $detection->setBehaviorType($data['behavior_type'] ?? 'unknown');
        $detection->setConfidence($data['confidence'] ?? 0);
        $detection->setBoundingBox($data['bounding_box'] ?? null);
        $detection->setDetectedObject($data['detected_object'] ?? null);
        $detection->setHealthCondition($data['health_condition'] ?? null);
        $detection->setHealthSymptoms($data['health_symptoms'] ?? null);
        $detection->setSeverity($data['severity'] ?? 'normal');
        $detection->setDescription($data['description'] ?? null);
        $detection->setMetadata($data['metadata'] ?? null);
        $detection->setDetectedAt(new \DateTime());
        $detection->setCreatedAt(new \DateTime());

        $entityManager->persist($detection);
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Detection received',
            'id' => $detection->getId(),
        ]);
    }

    #[Route('/{id}/data', name: 'app_admin_stations_data', methods: ['GET'], requirements: ['id' => '\\d+'])]
    public function getStationData(
        ObservationStation $station,
        IoTDataRepository $iotRepo
    ): JsonResponse {
        $latestData = $iotRepo->findLatestByStation($station, 50);
        
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

    // API endpoint for PTZ control by IP address
    #[Route('/api/camera/ptz', name: 'app_admin_stations_api_ptz', methods: ['POST'])]
    public function ptzByIp(Request $request, IpCameraRepository $cameraRepo): JsonResponse
    {
        $ip = $request->query->get('ip');
        $action = $request->query->get('action');
        
        if (!$ip || !$action) {
            return new JsonResponse(['success' => false, 'error' => 'IP and action required'], 400);
        }
        
        $camera = $cameraRepo->findOneByIpAddress($ip);
        if (!$camera) {
            return new JsonResponse([
                'success' => true, 
                'message' => 'PTZ action simulated: ' . $action . ' for camera at ' . $ip,
                'demo' => true
            ]);
        }
        
        $ptzCapabilities = $camera->getPtzCapabilities() ?? [];
        
        $actionMap = [
            'up' => 'ptz_up',
            'down' => 'ptz_down',
            'left' => 'ptz_left',
            'right' => 'ptz_right',
            'home' => 'ptz_home',
            'zoom_in' => 'zoom_in',
            'zoom_out' => 'zoom_out',
        ];
        
        $fullAction = $actionMap[$action] ?? $action;
        
        $isPtz = in_array('ptz', $ptzCapabilities);
        $isZoom = in_array('zoom', $ptzCapabilities) && str_starts_with($fullAction, 'zoom');
        
        if (!$isPtz && !$isZoom) {
            return new JsonResponse([
                'success' => false, 
                'error' => 'Camera does not support PTZ or this action'
            ], 400);
        }
        
        return new JsonResponse([
            'success' => true, 
            'message' => 'PTZ action executed: ' . $fullAction,
            'camera' => $camera->getName(),
            'action' => $action
        ]);
    }

    // ============ IoT DEVICE MANAGEMENT ============
    
    /**
     * List all IoT devices for a station (HTML)
     */
    #[Route('/{id}/iot', name: 'app_admin_station_iot_devices', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function stationIoTDevices(
        ObservationStation $station,
        IoTDeviceRepository $deviceRepo
    ): Response {
        $devices = $deviceRepo->findByStationId($station->getId());
        
        return $this->render('observation_station/iot_devices.html.twig', [
            'station' => $station,
            'devices' => $devices,
            'active' => 'station',
            'page_title' => 'IoT Devices: ' . $station->getCode(),
        ]);
    }

    /**
     * Add new IoT device to a station
     */
    #[Route('/{id}/iot/new', name: 'app_admin_station_iot_new', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function newIoTDevice(
        Request $request,
        ObservationStation $station,
        EntityManagerInterface $entityManager
    ): Response {
        $device = new IoTDevice();
        $device->setStation($station);
        $device->setStatus('inactive');
        $device->setDeviceId(uniqid('IOT_'));
        $device->setApiEndpoint('/admin/stations/api/iot/data');
        
        $form = $this->createForm(IoTDeviceType::class, $device);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($device);
            $entityManager->flush();

            $this->addFlash('success', 'IoT device added successfully');
            return $this->redirectToRoute('app_admin_station_iot_devices', ['id' => $station->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('observation_station/iot_device_form.html.twig', [
            'device' => $device,
            'station' => $station,
            'form' => $form,
            'active' => 'station',
            'page_title' => 'Add IoT Device to ' . $station->getCode(),
        ]);
    }

    /**
     * Edit an IoT device
     */
    #[Route('/{stationId}/iot/{deviceId}/edit', name: 'app_admin_station_iot_edit', methods: ['GET', 'POST'], requirements: ['stationId' => '\d+', 'deviceId' => '\d+'])]
    public function editIoTDevice(
        Request $request,
        int $stationId,
        int $deviceId,
        EntityManagerInterface $entityManager,
        IoTDeviceRepository $deviceRepo
    ): Response {
        $device = $deviceRepo->find($deviceId);
        
        if (!$device || $device->getStation()->getId() != $stationId) {
            throw $this->createNotFoundException('IoT device not found');
        }
        
        $station = $device->getStation();
        
        $form = $this->createForm(IoTDeviceType::class, $device);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $device->setUpdatedAt(new \DateTime());
            $entityManager->flush();

            $this->addFlash('success', 'IoT device updated successfully');
            return $this->redirectToRoute('app_admin_station_iot_devices', ['id' => $stationId], Response::HTTP_SEE_OTHER);
        }

        return $this->render('observation_station/iot_device_form.html.twig', [
            'device' => $device,
            'station' => $station,
            'form' => $form,
            'active' => 'station',
            'page_title' => 'Edit IoT Device: ' . $device->getName(),
        ]);
    }

    /**
     * Delete an IoT device
     */
    #[Route('/{stationId}/iot/{deviceId}/delete', name: 'app_admin_station_iot_delete', methods: ['POST'], requirements: ['stationId' => '\d+', 'deviceId' => '\d+'])]
    public function deleteIoTDevice(
        Request $request,
        int $stationId,
        int $deviceId,
        EntityManagerInterface $entityManager,
        IoTDeviceRepository $deviceRepo
    ): Response {
        $device = $deviceRepo->find($deviceId);
        
        if (!$device || $device->getStation()->getId() != $stationId) {
            throw $this->createNotFoundException('IoT device not found');
        }
        
        if ($this->isCsrfTokenValid('delete_iot_device'.$deviceId, $request->getPayload()->getString('_token'))) {
            $entityManager->remove($device);
            $entityManager->flush();
            $this->addFlash('success', 'IoT device deleted successfully');
        }

        return $this->redirectToRoute('app_admin_station_iot_devices', ['id' => $stationId], Response::HTTP_SEE_OTHER);
    }

    // ============ CAMERA MANAGEMENT (Extended CRUD) ============

    /**
     * Edit a camera
     */
    #[Route('/{stationId}/cameras/{id}/edit', name: 'app_admin_station_camera_edit', methods: ['GET', 'POST'], requirements: ['stationId' => '\d+', 'id' => '\d+'])]
    public function editStationCamera(
        Request $request,
        int $stationId,
        IpCamera $camera,
        EntityManagerInterface $entityManager,
        ObservationStationRepository $stationRepo
    ): Response {
        $station = $stationRepo->find($stationId);
        if (!$station) {
            throw $this->createNotFoundException('Station not found');
        }
        
        $form = $this->createForm(IpCameraType::class, $camera);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $camera->setUpdatedAt(new \DateTime());
            $entityManager->flush();

            $this->addFlash('success', 'Camera updated successfully');
            return $this->redirectToRoute('app_admin_station_cameras', ['id' => $stationId], Response::HTTP_SEE_OTHER);
        }

        return $this->render('observation_station/camera_edit.html.twig', [
            'camera' => $camera,
            'station' => $station,
            'form' => $form,
            'active' => 'station',
            'page_title' => 'Edit Camera: ' . $camera->getName(),
        ]);
    }

    /**
     * Delete a camera
     */
    #[Route('/{stationId}/cameras/{id}/delete', name: 'app_admin_station_camera_delete', methods: ['POST'], requirements: ['stationId' => '\d+', 'id' => '\d+'])]
    public function deleteStationCamera(
        Request $request,
        int $stationId,
        IpCamera $camera,
        EntityManagerInterface $entityManager
    ): Response {
        if ($camera->getStation()->getId() != $stationId) {
            throw $this->createNotFoundException('Camera not found for this station');
        }
        
        if ($this->isCsrfTokenValid('delete_camera'.$camera->getId(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($camera);
            $entityManager->flush();
            $this->addFlash('success', 'Camera deleted successfully');
        }

        return $this->redirectToRoute('app_admin_station_cameras', ['id' => $stationId], Response::HTTP_SEE_OTHER);
    }
}
