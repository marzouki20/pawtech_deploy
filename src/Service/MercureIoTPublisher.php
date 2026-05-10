<?php

namespace App\Service;

use App\Entity\IoTData;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;

class MercureIoTPublisher
{
    private HubInterface $hub;

    public function __construct(HubInterface $hub)
    {
        $this->hub = $hub;
    }

    /**
     * Publish IoT data update to Mercure hub
     */
    public function publishIoTData(IoTData $iotData): void
    {
        try {
            $stationId = $iotData->getStation()->getId();
            $stationCode = $iotData->getStation()->getCode();
            
            // Create the topic for this specific station
            $topic = sprintf('iot/station/%d', $stationId);
            
            // Create the update data
            $data = [
                'id' => $iotData->getId(),
                'station_id' => $stationId,
                'station_code' => $stationCode,
                'temperature' => $iotData->getTemperature(),
                'humidity' => $iotData->getHumidity(),
                'pressure' => $iotData->getPressure(),
                'distance' => $iotData->getDistance(),
                'dog_detected' => $iotData->isDogDetected(),
                'food_dispensed' => $iotData->isFoodDispensed(),
                'device_type' => $iotData->getDeviceType(),
                'device_id' => $iotData->getDeviceId(),
                'firmware_version' => $iotData->getFirmwareVersion(),
                'last_seen' => $iotData->getLastSeen()?->format('c'),
                'created_at' => $iotData->getCreatedAt()?->format('c'),
            ];

            $update = new Update(
                $topic,
                json_encode($data),
                false // not private (anyone can subscribe)
            );

            $this->hub->publish($update);
            
            // Also publish to a global topic for all stations
            $globalUpdate = new Update(
                'iot/all',
                json_encode($data),
                false
            );
            
            $this->hub->publish($globalUpdate);
        } catch (\Exception $e) {
            // Log error but don't fail - data is still saved
            error_log('Mercure publish failed: ' . $e->getMessage());
        }
    }

    /**
     * Publish a heartbeat/status update for a station
     */
    public function publishStationStatus(int $stationId, bool $online): void
    {
        $topic = sprintf('iot/station/%d/status', $stationId);
        
        $data = [
            'station_id' => $stationId,
            'online' => $online,
            'timestamp' => (new \DateTime())->format('c'),
        ];

        $update = new Update($topic, json_encode($data), false);
        $this->hub->publish($update);
    }
}
