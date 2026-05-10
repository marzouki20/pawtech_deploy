<?php

namespace App\Controller;

use App\Entity\IoTDevice;
use App\Entity\IoTData;
use App\Repository\IoTDeviceRepository;
use App\Repository\ObservationStationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Universal IoT Controller - Station-Agnostic Endpoints
 * These endpoints allow IoT devices to connect without knowing their station ID
 * The device ID is used to look up the configuration from the database
 */
#[Route('/admin/stations/iot')]
class UniversalIoTController extends AbstractController
{
    /**
     * Get device configuration by device ID - Universal endpoint
     * This is the station-agnostic way for ESP32 to fetch its configuration
     * URL: /admin/stations/iot/config/{deviceId}
     */
    #[Route('/config/{deviceId}', name: 'app_iot_config_universal', methods: ['GET'])]
    public function getConfig(
        string $deviceId,
        IoTDeviceRepository $deviceRepo
    ): JsonResponse {
        $device = $deviceRepo->findByDeviceId($deviceId);
        
        if (!$device) {
            return new JsonResponse([
                'error' => 'Device not found',
                'message' => 'No device found with ID: ' . $deviceId . '. Please create this device in the admin panel first.'
            ], 404);
        }
        
        // Return configuration in a format ESP32 can use
        return new JsonResponse($device->getConfigJson());
    }

    /**
     * Send sensor data - Universal endpoint
     * URL: /admin/stations/iot/data
     */
    #[Route('/data', name: 'app_iot_data_universal', methods: ['POST'])]
    public function receiveData(
        Request $request,
        EntityManagerInterface $entityManager,
        IoTDeviceRepository $deviceRepo,
        ObservationStationRepository $stationRepo
    ): JsonResponse {
        $data = json_decode($request->getContent(), true);
        
        if (!$data) {
            return new JsonResponse(['error' => 'Invalid data'], 400);
        }

        // Try to find device by device_id first
        $deviceId = $data['device_id'] ?? null;
        $stationCode = $data['station_code'] ?? null;
        
        $device = null;
        $station = null;
        
        // Priority 1: Find by device ID
        if ($deviceId) {
            $device = $deviceRepo->findByDeviceId($deviceId);
            if ($device) {
                $station = $device->getStation();
            }
        }
        
        // Priority 2: Find by station code
        if (!$station && $stationCode) {
            $station = $stationRepo->findOneBy(['code' => $stationCode]);
        }
        
        if (!$station) {
            return new JsonResponse(['error' => 'Station not found'], 404);
        }

        // Update device last seen
        if ($device) {
            $device->setLastSeen(new \DateTime());
            $entityManager->flush();
        }

        $iotData = new IoTData();
        $iotData->setStation($station);
        $iotData->setTemperature($data['temperature'] ?? null);
        $iotData->setHumidity($data['humidity'] ?? null);
        $iotData->setPressure($data['pressure'] ?? null);
        $iotData->setBatteryLevel($data['battery'] ?? null);
        $iotData->setSignalStrength($data['signal_strength'] ?? null);
        $iotData->setDeviceType($data['device_type'] ?? 'ESP32');
        $iotData->setDeviceId($deviceId);
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
            'station_code' => $station->getCode(),
        ]);
    }

    /**
     * Send heartbeat - Universal endpoint
     * URL: /admin/stations/iot/heartbeat/{deviceId}
     */
    #[Route('/heartbeat/{deviceId}', name: 'app_iot_heartbeat_universal', methods: ['POST'])]
    public function heartbeat(
        string $deviceId,
        Request $request,
        EntityManagerInterface $entityManager,
        IoTDeviceRepository $deviceRepo
    ): JsonResponse {
        $device = $deviceRepo->findByDeviceId($deviceId);
        
        if (!$device) {
            return new JsonResponse(['error' => 'Device not found'], 404);
        }
        
        $data = json_decode($request->getContent(), true) ?? [];
        
        $device->setLastHeartbeat(new \DateTime());
        $device->setLastSeen(new \DateTime());
        $device->setStatus($data['status'] ?? 'active');
        
        if (isset($data['firmware_version'])) {
            $device->setFirmwareVersion($data['firmware_version']);
        }
        
        $entityManager->flush();

        return new JsonResponse([
            'success' => true,
            'message' => 'Heartbeat received',
            'station_id' => $device->getStation()->getId(),
            'station_code' => $device->getStation()->getCode(),
        ]);
    }
}
