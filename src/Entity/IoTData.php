<?php

namespace App\Entity;

use App\Repository\IoTDataRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: IoTDataRepository::class)]
#[ORM\Table(name: 'iot_data')]
#[ORM\Index(name: 'idx_iot_data_station', columns: ['station_id'])]
#[ORM\Index(name: 'idx_iot_data_created', columns: ['created_at'])]
class IoTData
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'iotData')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ObservationStation $station = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $temperature = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $humidity = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2, nullable: true)]
    private ?string $pressure = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $batteryLevel = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $signalStrength = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 6, scale: 2, nullable: true)]
    private ?string $distance = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $dogDetected = null;

    #[ORM\Column(type: Types::BOOLEAN, nullable: true)]
    private ?bool $foodDispensed = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $additionalSensors = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $deviceType = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $deviceId = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $firmwareVersion = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $lastSeen = null;

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->lastSeen = new \DateTime();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStation(): ?ObservationStation
    {
        return $this->station;
    }

    public function setStation(?ObservationStation $station): static
    {
        $this->station = $station;
        return $this;
    }

    public function getTemperature(): ?string
    {
        return $this->temperature;
    }

    public function setTemperature(?string $temperature): static
    {
        $this->temperature = $temperature;
        return $this;
    }

    public function getHumidity(): ?string
    {
        return $this->humidity;
    }

    public function setHumidity(?string $humidity): static
    {
        $this->humidity = $humidity;
        return $this;
    }

    public function getPressure(): ?string
    {
        return $this->pressure;
    }

    public function setPressure(?string $pressure): static
    {
        $this->pressure = $pressure;
        return $this;
    }

    public function getBatteryLevel(): ?string
    {
        return $this->batteryLevel;
    }

    public function setBatteryLevel(?string $batteryLevel): static
    {
        $this->batteryLevel = $batteryLevel;
        return $this;
    }

    public function getSignalStrength(): ?string
    {
        return $this->signalStrength;
    }

    public function setSignalStrength(?string $signalStrength): static
    {
        $this->signalStrength = $signalStrength;
        return $this;
    }

    public function getDistance(): ?string
    {
        return $this->distance;
    }

    public function setDistance(?string $distance): static
    {
        $this->distance = $distance;
        return $this;
    }

    public function isDogDetected(): ?bool
    {
        return $this->dogDetected;
    }

    public function setDogDetected(?bool $dogDetected): static
    {
        $this->dogDetected = $dogDetected;
        return $this;
    }

    public function isFoodDispensed(): ?bool
    {
        return $this->foodDispensed;
    }

    public function setFoodDispensed(?bool $foodDispensed): static
    {
        $this->foodDispensed = $foodDispensed;
        return $this;
    }

    public function getAdditionalSensors(): ?array
    {
        return $this->additionalSensors;
    }

    public function setAdditionalSensors(?array $additionalSensors): static
    {
        $this->additionalSensors = $additionalSensors;
        return $this;
    }

    public function getDeviceType(): ?string
    {
        return $this->deviceType;
    }

    public function setDeviceType(?string $deviceType): static
    {
        $this->deviceType = $deviceType;
        return $this;
    }

    public function getDeviceId(): ?string
    {
        return $this->deviceId;
    }

    public function setDeviceId(?string $deviceId): static
    {
        $this->deviceId = $deviceId;
        return $this;
    }

    public function getFirmwareVersion(): ?string
    {
        return $this->firmwareVersion;
    }

    public function setFirmwareVersion(?string $firmwareVersion): static
    {
        $this->firmwareVersion = $firmwareVersion;
        return $this;
    }

    public function getLastSeen(): ?\DateTime
    {
        return $this->lastSeen;
    }

    public function setLastSeen(?\DateTime $lastSeen): static
    {
        $this->lastSeen = $lastSeen;
        return $this;
    }

    public function getCreatedAt(): ?\DateTime
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTime $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }
}
