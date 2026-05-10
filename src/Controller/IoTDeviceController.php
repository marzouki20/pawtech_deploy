<?php

namespace App\Controller;

use App\Entity\IoTDevice;
use App\Entity\ObservationStation;
use App\Repository\IoTDeviceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Serializer\SerializerInterface;

#[Route('/admin/stations/{stationId}/iot')]
class IoTDeviceController extends AbstractController
{
    /**
     * List all IoT devices for a station
     */
    #[Route('/devices', name: 'app_admin_station_iot_devices', methods: ['GET'])]
    public function listDevices(
        int $stationId,
        IoTDeviceRepository $deviceRepo
    ): JsonResponse {
        $devices = $deviceRepo->findByStationId($stationId);
        
        $data = array_map(function($device) {
            return [
                'id' => $device->getId(),
                'name' => $device->getName(),
                'deviceType' => $device->getDeviceType(),
                'deviceId' => $device->getDeviceId(),
                'status' => $device->getStatus(),
                'firmwareVersion' => $device->getFirmwareVersion(),
                'reportingInterval' => $device->getReportingInterval(),
                'heartbeatInterval' => $device->getHeartbeatInterval(),
                'lastSeen' => $device->getLastSeen()?->format('c'),
                'lastHeartbeat' => $device->getLastHeartbeat()?->format('c'),
                'createdAt' => $device->getCreatedAt()?->format('c'),
            ];
        }, $devices);
        
        return new JsonResponse(['devices' => $data]);
    }

    /**
     * Create a new IoT device
     */
    #[Route('/devices', name: 'app_admin_station_iot_device_create', methods: ['POST'])]
    public function createDevice(
        int $stationId,
        Request $request,
        EntityManagerInterface $entityManager,
        IoTDeviceRepository $deviceRepo
    ): JsonResponse {
        $station = $entityManager->getRepository(ObservationStation::class)->find($stationId);
        
        if (!$station) {
            return new JsonResponse(['error' => 'Station not found'], 404);
        }
        
        $data = json_decode($request->getContent(), true);
        
        $device = new IoTDevice();
        $device->setStation($station);
        $device->setName($data['name'] ?? 'New Device');
        $device->setDeviceType($data['deviceType'] ?? 'ESP32');
        $device->setDeviceId($data['deviceId'] ?? uniqid('ESP32_'));
        $device->setStatus($data['status'] ?? 'inactive');
        $device->setFirmwareVersion($data['firmwareVersion'] ?? '1.0.0');
        $device->setWifiSsid($data['wifiSsid'] ?? null);
        $device->setWifiPassword($data['wifiPassword'] ?? null);
        $device->setApiServerUrl($data['apiServerUrl'] ?? null);
        $device->setApiEndpoint($data['apiEndpoint'] ?? '/admin/stations/api/iot/data');
        $device->setReportingInterval($data['reportingInterval'] ?? 60);
        $device->setHeartbeatInterval($data['heartbeatInterval'] ?? 300);
        $device->setSensorConfig($data['sensorConfig'] ?? null);
        
        $entityManager->persist($device);
        $entityManager->flush();
        
        return new JsonResponse([
            'success' => true,
            'device' => [
                'id' => $device->getId(),
                'name' => $device->getName(),
                'deviceType' => $device->getDeviceType(),
                'deviceId' => $device->getDeviceId(),
            ]
        ], 201);
    }

    /**
     * Get a single IoT device
     */
    #[Route('/devices/{id}', name: 'app_admin_station_iot_device_show', methods: ['GET'])]
    public function showDevice(
        int $stationId,
        int $id,
        IoTDeviceRepository $deviceRepo
    ): JsonResponse {
        $device = $deviceRepo->find($id);
        
        if (!$device || $device->getStation()->getId() != $stationId) {
            return new JsonResponse(['error' => 'Device not found'], 404);
        }
        
        return new JsonResponse([
            'id' => $device->getId(),
            'name' => $device->getName(),
            'deviceType' => $device->getDeviceType(),
            'deviceId' => $device->getDeviceId(),
            'status' => $device->getStatus(),
            'firmwareVersion' => $device->getFirmwareVersion(),
            'wifiSsid' => $device->getWifiSsid(),
            'apiServerUrl' => $device->getApiServerUrl(),
            'apiEndpoint' => $device->getApiEndpoint(),
            'reportingInterval' => $device->getReportingInterval(),
            'heartbeatInterval' => $device->getHeartbeatInterval(),
            'sensorConfig' => $device->getSensorConfig(),
            'lastSeen' => $device->getLastSeen()?->format('c'),
            'lastHeartbeat' => $device->getLastHeartbeat()?->format('c'),
        ]);
    }

    /**
     * Update an IoT device
     */
    #[Route('/devices/{id}', name: 'app_admin_station_iot_device_update', methods: ['PUT', 'PATCH'])]
    public function updateDevice(
        int $stationId,
        int $id,
        Request $request,
        EntityManagerInterface $entityManager,
        IoTDeviceRepository $deviceRepo
    ): JsonResponse {
        $device = $deviceRepo->find($id);
        
        if (!$device || $device->getStation()->getId() != $stationId) {
            return new JsonResponse(['error' => 'Device not found'], 404);
        }
        
        $data = json_decode($request->getContent(), true);
        
        if (isset($data['name'])) {
            $device->setName($data['name']);
        }
        if (isset($data['deviceType'])) {
            $device->setDeviceType($data['deviceType']);
        }
        if (isset($data['deviceId'])) {
            $device->setDeviceId($data['deviceId']);
        }
        if (isset($data['status'])) {
            $device->setStatus($data['status']);
        }
        if (isset($data['firmwareVersion'])) {
            $device->setFirmwareVersion($data['firmwareVersion']);
        }
        if (isset($data['wifiSsid'])) {
            $device->setWifiSsid($data['wifiSsid']);
        }
        if (isset($data['wifiPassword'])) {
            $device->setWifiPassword($data['wifiPassword']);
        }
        if (isset($data['apiServerUrl'])) {
            $device->setApiServerUrl($data['apiServerUrl']);
        }
        if (isset($data['apiEndpoint'])) {
            $device->setApiEndpoint($data['apiEndpoint']);
        }
        if (isset($data['reportingInterval'])) {
            $device->setReportingInterval($data['reportingInterval']);
        }
        if (isset($data['heartbeatInterval'])) {
            $device->setHeartbeatInterval($data['heartbeatInterval']);
        }
        if (isset($data['sensorConfig'])) {
            $device->setSensorConfig($data['sensorConfig']);
        }
        
        $device->setUpdatedAt(new \DateTime());
        $entityManager->flush();
        
        return new JsonResponse(['success' => true, 'device' => [
            'id' => $device->getId(),
            'name' => $device->getName(),
            'deviceType' => $device->getDeviceType(),
            'deviceId' => $device->getDeviceId(),
            'status' => $device->getStatus(),
        ]]);
    }

    /**
     * Delete an IoT device
     */
    #[Route('/devices/{id}', name: 'app_admin_station_iot_device_delete', methods: ['DELETE'])]
    public function deleteDevice(
        int $stationId,
        int $id,
        EntityManagerInterface $entityManager,
        IoTDeviceRepository $deviceRepo
    ): JsonResponse {
        $device = $deviceRepo->find($id);
        
        if (!$device || $device->getStation()->getId() != $stationId) {
            return new JsonResponse(['error' => 'Device not found'], 404);
        }
        
        $entityManager->remove($device);
        $entityManager->flush();
        
        return new JsonResponse(['success' => true]);
    }

    /**
     * Get configuration for ESP32 (for dynamic configuration)
     * This endpoint allows ESP32 to fetch its configuration from the server
     */
    #[Route('/config/{deviceId}', name: 'app_iot_device_config', methods: ['GET'])]
    public function getDeviceConfig(
        string $deviceId,
        IoTDeviceRepository $deviceRepo
    ): JsonResponse {
        $device = $deviceRepo->findByDeviceId($deviceId);
        
        if (!$device) {
            return new JsonResponse(['error' => 'Device not found'], 404);
        }
        
        // Return configuration in a format ESP32 can use
        return new JsonResponse($device->getConfigJson());
    }

    /**
     * Register heartbeat from ESP32
     */
    #[Route('/heartbeat/{deviceId}', name: 'app_iot_device_heartbeat', methods: ['POST'])]
    public function registerHeartbeat(
        string $deviceId,
        EntityManagerInterface $entityManager,
        IoTDeviceRepository $deviceRepo
    ): JsonResponse {
        $device = $deviceRepo->findByDeviceId($deviceId);
        
        if (!$device) {
            return new JsonResponse(['error' => 'Device not found'], 404);
        }
        
        $now = new \DateTime();
        $device->setLastHeartbeat($now);
        $device->setLastSeen($now);
        $device->setStatus('active');
        $entityManager->flush();
        
        return new JsonResponse([
            'success' => true,
            'status' => 'online',
            'timestamp' => $now->format('c'),
        ]);
    }
}
