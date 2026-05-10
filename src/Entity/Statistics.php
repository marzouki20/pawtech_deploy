<?php

namespace App\Entity;

use App\Repository\StatisticsRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StatisticsRepository::class)]
#[ORM\Table(name: 'statistics')]
#[ORM\Index(name: 'idx_stats_date', columns: ['date'])]
#[ORM\Index(name: 'idx_stats_type', columns: ['type'])]
class Statistics
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    private ?string $type = null;

    #[ORM\Column(type: Types::DATE_MUTABLE)]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: Types::JSON)]
    private array $data = [];

    #[ORM\Column]
    private ?int $count = 0;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $average = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $min = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 10, scale: 2, nullable: true)]
    private ?string $max = null;

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    #[ORM\Column]
    private ?\DateTime $updatedAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->date = new \DateTime();
    }

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

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(\DateTimeInterface $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function getData(): array
    {
        return $this->data;
    }

    public function setData(array $data): static
    {
        $this->data = $data;
        return $this;
    }

    public function getCount(): ?int
    {
        return $this->count;
    }

    public function setCount(int $count): static
    {
        $this->count = $count;
        return $this;
    }

    public function getAverage(): ?string
    {
        return $this->average;
    }

    public function setAverage(?string $average): static
    {
        $this->average = $average;
        return $this;
    }

    public function getMin(): ?string
    {
        return $this->min;
    }

    public function setMin(?string $min): static
    {
        $this->min = $min;
        return $this;
    }

    public function getMax(): ?string
    {
        return $this->max;
    }

    public function setMax(?string $max): static
    {
        $this->max = $max;
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

    public function getUpdatedAt(): ?\DateTime
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(\DateTime $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    /**
     * Get available statistic types
     */
    public static function getTypes(): array
    {
        return [
            'iot_temperature' => 'IoT Temperature',
            'iot_humidity' => 'IoT Humidity',
            'iot_pressure' => 'IoT Pressure',
            'camera_activity' => 'Camera Activity',
            'dog_detections' => 'Dog Detections',
            'health_alerts' => 'Health Alerts',
            'station_online' => 'Station Online',
            'station_offline' => 'Station Offline',
            'total_alerts' => 'Total Alerts'
        ];
    }
}
