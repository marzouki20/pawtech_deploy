<?php

namespace App\Entity;

use App\Repository\ZiptagInfoRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ZiptagInfoRepository::class)]
#[ORM\Table(name: 'ziptag_infos')]
class ZiptagInfo
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    private ?string $type = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $message = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $batteryLevel = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $heartRate = null;

    #[ORM\Column(type: 'float', nullable: true)]
    private ?float $bodyTemperature = null;

    #[ORM\Column(type: 'datetime')]
    private ?\DateTimeInterface $createdAt = null;

    #[ORM\ManyToOne(targetEntity: Ziptag::class, inversedBy: 'infos')]
    #[ORM\JoinColumn(name: 'ziptage_id', nullable: false)]
    private ?Ziptag $ziptag = null;


    public function getId(): ?int
    {
        return $this->id;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(?string $message): static
    {
        $this->message = $message;
        return $this;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): static
    {
        $this->location = $location;
        return $this;
    }

    public function getBatteryLevel(): ?float
    {
        return $this->batteryLevel;
    }

    public function setBatteryLevel(?float $batteryLevel): static
    {
        $this->batteryLevel = $batteryLevel;
        return $this;
    }

    public function getHeartRate(): ?float
    {
        return $this->heartRate;
    }

    public function setHeartRate(?float $heartRate): static
    {
        $this->heartRate = $heartRate;
        return $this;
    }

    public function getBodyTemperature(): ?float
    {
        return $this->bodyTemperature;
    }

    public function setBodyTemperature(?float $bodyTemperature): static
    {
        $this->bodyTemperature = $bodyTemperature;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    public function getZiptag(): ?Ziptag
    {
        return $this->ziptag;
    }

    public function setZiptag(?Ziptag $ziptag): static
    {
        $this->ziptag = $ziptag;
        return $this;
    }
}
