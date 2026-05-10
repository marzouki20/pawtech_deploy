<?php

namespace App\Entity;

use App\Repository\DogDetectionRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: DogDetectionRepository::class)]
#[ORM\Table(name: 'dog_detection')]
#[ORM\Index(name: 'idx_detection_camera', columns: ['camera_id'])]
#[ORM\Index(name: 'idx_detection_timestamp', columns: ['detected_at'])]
class DogDetection
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'detections')]
    #[ORM\JoinColumn(nullable: true)]
    private ?IpCamera $camera = null;

    #[ORM\Column(length: 50)]
    private ?string $behaviorType = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2)]
    private ?string $confidence = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $boundingBox = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $detectedObject = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $healthCondition = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $healthSymptoms = null;

    #[ORM\Column]
    private ?string $severity = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $imagePath = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $metadata = null;

    #[ORM\Column]
    private ?\DateTime $detectedAt = null;

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->detectedAt = new \DateTime();
        $this->severity = 'normal';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCamera(): ?IpCamera
    {
        return $this->camera;
    }

    public function setCamera(?IpCamera $camera): static
    {
        $this->camera = $camera;
        return $this;
    }

    public function getBehaviorType(): ?string
    {
        return $this->behaviorType;
    }

    public function setBehaviorType(string $behaviorType): static
    {
        $this->behaviorType = $behaviorType;
        return $this;
    }

    public function getConfidence(): ?string
    {
        return $this->confidence;
    }

    public function setConfidence(string $confidence): static
    {
        $this->confidence = $confidence;
        return $this;
    }

    public function getBoundingBox(): ?array
    {
        return $this->boundingBox;
    }

    public function setBoundingBox(?array $boundingBox): static
    {
        $this->boundingBox = $boundingBox;
        return $this;
    }

    public function getDetectedObject(): ?string
    {
        return $this->detectedObject;
    }

    public function setDetectedObject(?string $detectedObject): static
    {
        $this->detectedObject = $detectedObject;
        return $this;
    }

    public function getHealthCondition(): ?string
    {
        return $this->healthCondition;
    }

    public function setHealthCondition(?string $healthCondition): static
    {
        $this->healthCondition = $healthCondition;
        return $this;
    }

    public function getHealthSymptoms(): ?array
    {
        return $this->healthSymptoms;
    }

    public function setHealthSymptoms(?array $healthSymptoms): static
    {
        $this->healthSymptoms = $healthSymptoms;
        return $this;
    }

    public function getSeverity(): ?string
    {
        return $this->severity;
    }

    public function setSeverity(string $severity): static
    {
        $this->severity = $severity;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(?string $imagePath): static
    {
        $this->imagePath = $imagePath;
        return $this;
    }

    public function getMetadata(): ?array
    {
        return $this->metadata;
    }

    public function setMetadata(?array $metadata): static
    {
        $this->metadata = $metadata;
        return $this;
    }

    public function getDetectedAt(): ?\DateTime
    {
        return $this->detectedAt;
    }

    public function setDetectedAt(\DateTime $detectedAt): static
    {
        $this->detectedAt = $detectedAt;
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

    /**
     * Check if this is a serious health condition
     */
    public function isSerious(): bool
    {
        return in_array($this->severity, ['critical', 'serious']);
    }

    /**
     * Check if this is a health issue (non-healthy condition)
     */
    public function getIsHealthIssue(): bool
    {
        return $this->healthCondition !== null && 
               $this->healthCondition !== 'healthy' &&
               $this->healthCondition !== '';
    }

    /**
     * Get timestamp (alias for detectedAt)
     */
    public function getTimestamp(): ?\DateTime
    {
        return $this->detectedAt;
    }

    /**
     * Get behavior (alias for behaviorType)
     */
    public function getBehavior(): ?string
    {
        return $this->behaviorType;
    }

    /**
     * Get available behavior types
     */
    public static function getBehaviorTypes(): array
    {
        return [
            'standing',
            'sitting',
            'lying_down',
            'running',
            'walking',
            'eating',
            'drinking',
            'sleeping',
            'playing',
            'barking',
            'scratching',
            'shotheraking',
            ''
        ];
    }

    /**
     * Get available health conditions
     */
    public static function getHealthConditions(): array
    {
        return [
            'healthy' => 'Healthy',
            'red_eyes' => 'Red Eyes',
            'oral_discharge' => 'Oral Discharge',
            'excessive_drooling' => 'Excessive Drooling',
            'lethargy' => 'Lethargy',
            'limping' => 'Limping',
            'hair_loss' => 'Hair Loss',
            'skin_irritation' => 'Skin Irritation',
            'weight_loss' => 'Weight Loss',
            'appetite_loss' => 'Appetite Loss',
            'vomiting' => 'Vomiting',
            'diarrhea' => 'Diarrhea',
            'breathing_difficulty' => 'Breathing Difficulty',
            'eye_discharge' => 'Eye Discharge',
            'ear_infection' => 'Ear Infection',
            'other' => 'Other'
        ];
    }

    /**
     * Get severity levels
     */
    public static function getSeverityLevels(): array
    {
        return [
            'normal' => 'Normal',
            'low' => 'Low',
            'medium' => 'Medium',
            'serious' => 'Serious',
            'critical' => 'Critical'
        ];
    }
}
