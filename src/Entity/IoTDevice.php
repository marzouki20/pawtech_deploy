<?php

namespace App\Entity;

use App\Repository\IoTDeviceRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IoTDeviceRepository::class)]
#[ORM\Table(name: 'iot_device')]
class IoTDevice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 50)]
    private ?string $deviceType = 'ESP32';

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $deviceId = null;

    #[ORM\ManyToOne(inversedBy: 'iotDevices')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ObservationStation $station = null;

    #[ORM\Column(length: 50)]
    private ?string $status = 'inactive';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $firmwareVersion = null;

    // WiFi Configuration (for ESP32)
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $wifiSsid = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $wifiPassword = null;

    // API Configuration
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $apiServerUrl = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $apiEndpoint = null;

    // Sensor Configuration
    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $sensorConfig = null;

    // Reporting intervals (in seconds)
    #[ORM\Column(nullable: true)]
    private ?int $reportingInterval = 60;

    #[ORM\Column(nullable: true)]
    private ?int $heartbeatInterval = 300;

    // Last communication
    #[ORM\Column(nullable: true)]
    private ?\DateTime $lastSeen = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $lastHeartbeat = null;

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    #[ORM\Column]
    private ?\DateTime $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
    }

    // Getters and Setters
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDeviceType(): ?string
    {
        return $this->deviceType;
    }

    public function setDeviceType(?string $deviceType): self
    {
        $this->deviceType = $deviceType;
        return $this;
    }

    public function getDeviceId(): ?string
    {
        return $this->deviceId;
    }

    public function setDeviceId(?string $deviceId): self
    {
        $this->deviceId = $deviceId;
        return $this;
    }

    public function getStation(): ?ObservationStation
    {
        return $this->station;
    }

    public function setStation(?ObservationStation $station): self
    {
        $this->station = $station;
        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(?string $status): self
    {
        $this->status = $status;
        return $this;
    }

    public function getFirmwareVersion(): ?string
    {
        return $this->firmwareVersion;
    }

    public function setFirmwareVersion(?string $firmwareVersion): self
    {
        $this->firmwareVersion = $firmwareVersion;
        return $this;
    }

    public function getWifiSsid(): ?string
    {
        return $this->wifiSsid;
    }

    public function setWifiSsid(?string $wifiSsid): self
    {
        $this->wifiSsid = $wifiSsid;
        return $this;
    }

    public function getWifiPassword(): ?string
    {
        return $this->wifiPassword;
    }

    public function setWifiPassword(?string $wifiPassword): self
    {
        $this->wifiPassword = $wifiPassword;
        return $this;
    }

    public function getApiServerUrl(): ?string
    {
        return $this->apiServerUrl;
    }

    public function setApiServerUrl(?string $apiServerUrl): self
    {
        $this->apiServerUrl = $apiServerUrl;
        return $this;
    }

    public function getApiEndpoint(): ?string
    {
        return $this->apiEndpoint;
    }

    public function setApiEndpoint(?string $apiEndpoint): self
    {
        $this->apiEndpoint = $apiEndpoint;
        return $this;
    }

    public function getSensorConfig(): ?array
    {
        return $this->sensorConfig;
    }

    public function setSensorConfig(?array $sensorConfig): self
    {
        $this->sensorConfig = $sensorConfig;
        return $this;
    }

    public function getReportingInterval(): ?int
    {
        return $this->reportingInterval;
    }

    public function setReportingInterval(?int $reportingInterval): self
    {
        $this->reportingInterval = $reportingInterval;
        return $this;
    }

    public function getHeartbeatInterval(): ?int
    {
        return $this->heartbeatInterval;
    }

    public function setHeartbeatInterval(?int $heartbeatInterval): self
    {
        $this->heartbeatInterval = $heartbeatInterval;
        return $this;
    }

    public function getLastSeen(): ?\DateTime
    {
        return $this->lastSeen;
    }

    public function setLastSeen(?\DateTime $lastSeen): self
    {
        $this->lastSeen = $lastSeen;
        return $this;
    }

    public function getLastHeartbeat(): ?\DateTime
    {
        return $this->lastHeartbeat;
    }

    public function setLastHeartbeat(?\DateTime $lastHeartbeat): self
    {
        $this->lastHeartbeat = $lastHeartbeat;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTime $createdAt): self
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTime $updatedAt): self
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * Get configuration as JSON for ESP32
     */
    public function getConfigJson(): array
    {
        return [
            'device_id' => $this->deviceId,
            'device_type' => $this->deviceType,
            'station_code' => $this->station?->getCode(),
            'station_id' => $this->station?->getId(),
            'api_server' => $this->apiServerUrl,
            'api_endpoint' => $this->apiEndpoint,
            'reporting_interval' => $this->reportingInterval,
            'heartbeat_interval' => $this->heartbeatInterval,
            'sensor_config' => $this->sensorConfig,
            'firmware_version' => $this->firmwareVersion,
            'wifi_ssid' => $this->wifiSsid,
            'wifi_password' => $this->wifiPassword,
        ];
    }
}
