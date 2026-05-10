<?php

namespace App\Entity;

use App\Repository\ObservationStationRepository;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;
use App\Entity\IoTData;
use App\Entity\IpCamera;

#[UniqueEntity(fields: ['code'], message: 'Ce code existe déjà.')]
#[ORM\Entity(repositoryClass: ObservationStationRepository::class)]
#[ORM\Table(name: 'observation_station')]
#[ORM\UniqueConstraint(name: 'uniq_observation_station_code', columns: ['code'])]
class ObservationStation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['stations'])]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Ce champ ne peut pas être vide')]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(
        pattern: '/^\d{6}$/',
        message: 'Le code doit contenir exactement 6 chiffres.'
    )]
    #[Groups(['stations'])]
    private ?string $code = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Ce champ ne peut pas être vide')]
    #[Assert\Length(max: 50)]
    #[Assert\Regex(
        pattern: '/^[A-Za-z]+_[A-Za-z]+$/',
        message: 'La zone doit être de la forme "ghazela_Nord".'
    )]
    #[Groups(['stations'])]
    private ?string $zone = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Ce champ ne peut pas être vide')]
    #[Assert\Length(max: 255)]
    #[Assert\Regex(
        pattern: '/^-?\d{1,3}\.\d+,\s*-?\d{1,3}\.\d+$/',
        message: 'La localisation doit être au format "48.8566, 2.3522".'
    )]
    #[Groups(['stations'])]
    private ?string $localisation = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Ce champ ne peut pas être vide')]
    #[Assert\Length(max: 50)]
    #[Assert\Choice(
        choices: ['active', 'inactive', 'maintenance'],
        message: 'Le statut doit être "active", "inactive" ou "maintenance".'
    )]
    #[Groups(['stations'])]
    private ?string $statut = null;

    /**
     * @var Collection<int, Alert>
     */
    #[ORM\OneToMany(targetEntity: Alert::class, mappedBy: 'station', orphanRemoval: true)]
    private Collection $alerts;

    /**
     * @var Collection<int, IoTData>
     */
    #[ORM\OneToMany(targetEntity: IoTData::class, mappedBy: 'station', orphanRemoval: true)]
    private Collection $iotData;

    /**
     * @var Collection<int, IpCamera>
     */
    #[ORM\OneToMany(targetEntity: IpCamera::class, mappedBy: 'station', orphanRemoval: true)]
    private Collection $cameras;

    /**
     * @var Collection<int, IoTDevice>
     */
    #[ORM\OneToMany(targetEntity: IoTDevice::class, mappedBy: 'station', orphanRemoval: true)]
    private Collection $iotDevices;

    public function __construct()
    {
        $this->alerts = new ArrayCollection();
        $this->iotData = new ArrayCollection();
        $this->cameras = new ArrayCollection();
        $this->iotDevices = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getCode(): ?string
    {
        return $this->code;
    }

    public function setCode(string $code): static
    {
        $this->code = $code;

        return $this;
    }

    public function getZone(): ?string
    {
        return $this->zone;
    }

    public function setZone(string $zone): static
    {
        $this->zone = $zone;

        return $this;
    }

    public function getLocalisation(): ?string
    {
        return $this->localisation;
    }

    public function setLocalisation(string $localisation): static
    {
        $this->localisation = $localisation;

        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(string $statut): static
    {
        $this->statut = $statut;

        return $this;
    }

    /**
     * @return Collection<int, Alert>
     */
    public function getAlerts(): Collection
    {
        return $this->alerts;
    }

    public function addAlert(Alert $alert): static
    {
        if (!$this->alerts->contains($alert)) {
            $this->alerts->add($alert);
            $alert->setStation($this);
        }

        return $this;
    }

    public function removeAlert(Alert $alert): static
    {
        if ($this->alerts->removeElement($alert)) {
            // set the owning side to null (unless already changed)
            if ($alert->getStation() === $this) {
                $alert->setStation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, IoTData>
     */
    public function getIotData(): Collection
    {
        return $this->iotData;
    }

    public function addIotData(IoTData $iotData): static
    {
        if (!$this->iotData->contains($iotData)) {
            $this->iotData->add($iotData);
            $iotData->setStation($this);
        }

        return $this;
    }

    public function removeIotData(IoTData $iotData): static
    {
        if ($this->iotData->removeElement($iotData)) {
            if ($iotData->getStation() === $this) {
                $iotData->setStation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, IpCamera>
     */
    public function getCameras(): Collection
    {
        return $this->cameras;
    }

    public function addCamera(IpCamera $camera): static
    {
        if (!$this->cameras->contains($camera)) {
            $this->cameras->add($camera);
            $camera->setStation($this);
        }

        return $this;
    }

    public function removeCamera(IpCamera $camera): static
    {
        if ($this->cameras->removeElement($camera)) {
            if ($camera->getStation() === $this) {
                $camera->setStation(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, IoTDevice>
     */
    public function getIotDevices(): Collection
    {
        return $this->iotDevices;
    }

    public function addIotDevice(IoTDevice $iotDevice): static
    {
        if (!$this->iotDevices->contains($iotDevice)) {
            $this->iotDevices->add($iotDevice);
            $iotDevice->setStation($this);
        }

        return $this;
    }

    public function removeIotDevice(IoTDevice $iotDevice): static
    {
        if ($this->iotDevices->removeElement($iotDevice)) {
            if ($iotDevice->getStation() === $this) {
                $iotDevice->setStation(null);
            }
        }

        return $this;
    }
}
