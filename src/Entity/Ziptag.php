<?php

namespace App\Entity;

use App\Repository\ZiptagRepository;
use Symfony\Component\Serializer\Annotation\Groups;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ZiptagRepository::class)]
#[ORM\Table(name: 'ziptages')]
#[UniqueEntity(fields: ['serialNumber'], message: 'Serial number already exists.')]
class Ziptag
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['ziptags'])]
    // @phpstan-ignore-next-line Doctrine sets generated IDs at runtime.
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank(message: 'Serial number is required.')]
    #[Assert\Length(max: 255, maxMessage: 'Serial number must be at most {{ limit }} characters.')]
    #[Groups(['ziptags'])]
    private ?string $serialNumber = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Firmware version is required.')]
    #[Assert\Length(max: 255, maxMessage: 'Firmware version must be at most {{ limit }} characters.')]
    #[Assert\Regex(
        pattern: '/^v\d+\.\d+\.\d+$/',
        message: 'Firmware version must match vX.X.X (e.g., v1.1.1).'
    )]
    #[Groups(['ziptags'])]
    private ?string $firmwareVersion = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Model is required.')]
    #[Assert\Length(max: 255, maxMessage: 'Model must be at most {{ limit }} characters.')]
    #[Groups(['ziptags'])]
    private ?string $model = null;


    #[ORM\OneToOne(targetEntity: Dogs::class, inversedBy: 'ziptag', cascade: ['persist'])]
    #[ORM\JoinColumn(nullable: true)]
    #[Groups(['ziptags'])]
    private ?Dogs $dog = null;

    #[ORM\OneToMany(mappedBy: 'ziptag', targetEntity: ZiptagInfo::class, orphanRemoval: true)]
    private Collection $infos;

    public function __construct()
    {
        $this->infos = new ArrayCollection();
    }
    public function getId(): ?int
    {
        return $this->id;
    }

    public function getSerialNumber(): ?string
    {
        return $this->serialNumber;
    }

    public function setSerialNumber(string $serialNumber): static
    {
        $this->serialNumber = $serialNumber;
        return $this;
    }

    public function getFirmwareVersion(): ?string
    {
        return $this->firmwareVersion;
    }

    public function setFirmwareVersion(string $firmwareVersion): static
    {
        $this->firmwareVersion = $firmwareVersion;
        return $this;
    }

    public function getModel(): ?string
    {
        return $this->model;
    }

    public function setModel(string $model): static
    {
        $this->model = $model;
        return $this;
    }

    public function getDog(): ?Dogs
    {
        return $this->dog;
    }

    public function setDog(?Dogs $dog): static
    {
        $this->dog = $dog;
        return $this;
    }

    /**
     * @return Collection<int, ZiptagInfo>
     */
    public function getInfos(): Collection
    {
        return $this->infos;
    }

    public function addInfo(ZiptagInfo $info): static
    {
        if (!$this->infos->contains($info)) {
            $this->infos->add($info);
            $info->setZiptag($this);
        }

        return $this;
    }

    public function removeInfo(ZiptagInfo $info): static
    {
        if ($this->infos->removeElement($info)) {
            if ($info->getZiptag() === $this) {
                $info->setZiptag(null);
            }
        }

        return $this;
    }
}
