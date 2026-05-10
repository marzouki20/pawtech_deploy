<?php

namespace App\Entity;

use App\Repository\IpCameraRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\DogDetection;

#[ORM\Entity(repositoryClass: IpCameraRepository::class)]
#[ORM\Table(name: 'ip_camera')]
class IpCamera
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Ce champ ne peut pas être vide')]
    #[Assert\Length(max: 100)]
    private ?string $name = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'L\'adresse IP est requise')]
    private ?string $ipAddress = null;

    #[ORM\Column(length: 10)]
    #[Assert\Range(min: 1, max: 65535)]
    private ?int $port = 80;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $username = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $password = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $streamUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $rtspUrl = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $snapshotUrl = null;

    #[ORM\ManyToOne(inversedBy: 'cameras')]
    #[ORM\JoinColumn(nullable: true)]
    private ?ObservationStation $station = null;

    #[ORM\Column(length: 50)]
    #[Assert\Choice(choices: ['active', 'inactive', 'error'], message: 'Statut invalide')]
    private ?string $status = 'inactive';

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $model = null;

    #[ORM\Column(length: 50, nullable: true)]
    private ?string $resolution = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $ptzCapabilities = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $cameraSettings = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTime $lastConnection = null;

    #[ORM\Column]
    private ?\DateTime $createdAt = null;

    #[ORM\Column]
    private ?\DateTime $updatedAt = null;

    /**
     * @var Collection<int, DogDetection>
     */
    #[ORM\OneToMany(targetEntity: DogDetection::class, mappedBy: 'camera', orphanRemoval: true)]
    private Collection $detections;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->updatedAt = new \DateTime();
        $this->port = 80;
        $this->detections = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;
        return $this;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(string $ipAddress): static
    {
        $this->ipAddress = $ipAddress;
        return $this;
    }

    public function getPort(): ?int
    {
        return $this->port;
    }

    public function setPort(int $port): static
    {
        $this->port = $port;
        return $this;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(?string $username): static
    {
        $this->username = $username;
        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(?string $password): static
    {
        $this->password = $password;
        return $this;
    }

    public function getStreamUrl(): ?string
    {
        return $this->streamUrl;
    }

    public function setStreamUrl(?string $streamUrl): static
    {
        $this->streamUrl = $streamUrl;
        return $this;
    }

    public function getRtspUrl(): ?string
    {
        return $this->rtspUrl;
    }

    public function setRtspUrl(?string $rtspUrl): static
    {
        $this->rtspUrl = $rtspUrl;
        return $this;
    }

    public function getSnapshotUrl(): ?string
    {
        return $this->snapshotUrl;
    }

    public function setSnapshotUrl(?string $snapshotUrl): static
    {
        $this->snapshotUrl = $snapshotUrl;
        return $this;
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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;
        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(?string $model): static
    {
        $this->model = $model;
        return $this;
    }

    public function getResolution(): ?string
    {
        return $this->resolution;
    }

    public function setResolution(?string $resolution): static
    {
        $this->resolution = $resolution;
        return $this;
    }

    public function getPtzCapabilities(): ?array
    {
        return $this->ptzCapabilities;
    }

    public function setPtzCapabilities(?array $ptzCapabilities): static
    {
        $this->ptzCapabilities = $ptzCapabilities;
        return $this;
    }

    /**
     * Set default PTZ capabilities for a PTZ camera
     */
    public function setDefaultPtzCapabilities(): static
    {
        $this->ptzCapabilities = ['ptz', 'zoom', 'presets'];
        return $this;
    }

    /**
     * Check if camera supports PTZ
     */
    public function supportsPTZ(): bool
    {
        $capabilities = $this->ptzCapabilities ?? [];
        return in_array('ptz', $capabilities);
    }

    /**
     * Check if camera supports zoom
     */
    public function supportsZoom(): bool
    {
        $capabilities = $this->ptzCapabilities ?? [];
        return in_array('zoom', $capabilities);
    }

    /**
     * Check if camera supports presets
     */
    public function supportsPresets(): bool
    {
        $capabilities = $this->ptzCapabilities ?? [];
        return in_array('presets', $capabilities);
    }

    public function getCameraSettings(): ?array
    {
        return $this->cameraSettings;
    }

    public function setCameraSettings(?array $cameraSettings): static
    {
        $this->cameraSettings = $cameraSettings;
        return $this;
    }

    public function getLastConnection(): ?\DateTime
    {
        return $this->lastConnection;
    }

    public function setLastConnection(?\DateTime $lastConnection): static
    {
        $this->lastConnection = $lastConnection;
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
     * @return Collection<int, DogDetection>
     */
    public function getDetections(): Collection
    {
        return $this->detections;
    }

    public function addDetection(DogDetection $detection): static
    {
        if (!$this->detections->contains($detection)) {
            $this->detections->add($detection);
            $detection->setCamera($this);
        }

        return $this;
    }

    public function removeDetection(DogDetection $detection): static
    {
        if ($this->detections->removeElement($detection)) {
            if ($detection->getCamera() === $this) {
                $detection->setCamera(null);
            }
        }

        return $this;
    }

    /**
     * Get the full stream URL with authentication
     * Supports RTSP, HTTP, and other stream protocols
     */
    public function getFullStreamUrl(): string
    {
        $hasScheme = static fn(?string $url): bool => is_string($url) && preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) === 1;

        // If RTSP URL is set, use it
        if ($this->rtspUrl) {
            $auth = '';
            if ($this->username && $this->password) {
                // Insert auth into RTSP URL
                $rtsp = str_replace('://', '://' . $this->username . ':' . $this->password . '@', $this->rtspUrl);
                return $rtsp;
            }
            return $this->rtspUrl;
        }
        
        if ($this->streamUrl) {
            // Check if it's already a full URL
            if ($hasScheme($this->streamUrl)) {
                return $this->streamUrl;
            }
            if (str_starts_with($this->streamUrl, '/data:')) {
                return substr($this->streamUrl, 1);
            }
            $auth = '';
            if ($this->username && $this->password) {
                $auth = $this->username . ':' . $this->password . '@';
            }
            if (!$this->ipAddress) {
                return $this->streamUrl;
            }
            $path = str_starts_with($this->streamUrl, '/') ? $this->streamUrl : '/' . $this->streamUrl;
            return 'http://' . $auth . $this->ipAddress . ':' . $this->port . $path;
        }
        
        $auth = '';
        if ($this->username && $this->password) {
            $auth = $this->username . ':' . $this->password . '@';
        }
        
        return 'http://' . $auth . $this->ipAddress . ':' . $this->port . '/stream';
    }

    /**
     * Get the full snapshot URL with authentication
     */
    public function getFullSnapshotUrl(): string
    {
        $hasScheme = static fn(?string $url): bool => is_string($url) && preg_match('/^[a-z][a-z0-9+.-]*:/i', $url) === 1;

        if ($this->snapshotUrl) {
            if ($hasScheme($this->snapshotUrl)) {
                return $this->snapshotUrl;
            }
            if (str_starts_with($this->snapshotUrl, '/data:')) {
                return substr($this->snapshotUrl, 1);
            }
            $auth = '';
            if ($this->username && $this->password) {
                $auth = $this->username . ':' . $this->password . '@';
            }
            if (!$this->ipAddress) {
                return $this->snapshotUrl;
            }
            $path = str_starts_with($this->snapshotUrl, '/') ? $this->snapshotUrl : '/' . $this->snapshotUrl;
            return 'http://' . $auth . $this->ipAddress . ':' . $this->port . $path;
        }
        
        $auth = '';
        if ($this->username && $this->password) {
            $auth = $this->username . ':' . $this->password . '@';
        }
        
        return 'http://' . $auth . $this->ipAddress . ':' . $this->port . '/snapshot.jpg';
    }

    /**
     * Get MJPEG stream URL for direct browser playback
     * Many IP cameras support MJPEG which plays directly in browsers
     */
    public function getMjpegStreamUrl(): string
    {
        $auth = '';
        if ($this->username && $this->password) {
            $auth = $this->username . ':' . $this->password . '@';
        }
        
        // Try common MJPEG endpoints
        $mjpegEndpoints = [
            '/mjpeg',
            '/videostream.cgi',
            '/axis-cgi/mjpg/video.cgi',
            '/cgi-bin/mjpg/video.cgi',
            '/live/ch00_0',
            '/stream'
        ];
        
        // If streamUrl is set and looks like MJPEG, use it
        if ($this->streamUrl && !str_contains($this->streamUrl, 'rtsp')) {
            if (str_starts_with($this->streamUrl, 'http')) {
                return $this->streamUrl;
            }
            return 'http://' . $auth . $this->ipAddress . ':' . $this->port . $this->streamUrl;
        }
        
        // Return first common MJPEG endpoint
        return 'http://' . $auth . $this->ipAddress . ':' . $this->port . $mjpegEndpoints[0];
    }

    /**
     * Check if camera supports MJPEG streaming
     */
    public function supportsMjpeg(): bool
    {
        // Most IP cameras support MJPEG
        return true;
    }

    /**
     * Get the HLS stream URL for the transcoded stream
     * Returns the URL to the HLS playlist generated by StreamTranscoderService
     */
    public function getHlsStreamUrl(): ?string
    {
        if (!$this->id) {
            return null;
        }
        
        // Check if the HLS playlist exists
        $playlistPath = '/streams/camera_' . $this->id . '/playlist.m3u8';
        return $playlistPath;
    }

    /**
     * Check if HLS stream is available (playlist exists)
     */
    public function hasHlsStream(): bool
    {
        // This is a helper method - actual file check would be done by the service
        return $this->id !== null;
    }
}
