<?php

namespace App\Entity;

use App\Repository\EvenementRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

#[ORM\Entity(repositoryClass: EvenementRepository::class)]
class Evenement
{
    public const TYPES = ['VACCINATION', 'ADOPTION', 'SENSIBILISATION', 'COLLECTE_DONS'];
    public const STATUTS = ['PLANIFIE', 'EN_COURS', 'TERMINE', 'ANNULE'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le titre est obligatoire')]
    #[Assert\Length(min: 3, max: 255, minMessage: 'Le titre doit contenir au moins {{ limit }} caractères')]
    private ?string $titre = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column]
    #[Assert\NotNull(message: 'La date de début est obligatoire')]
    private ?\DateTime $dateDebut = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Expression(
        "this.getDateFin() === null or this.getDateDebut() === null or this.getDateFin() >= this.getDateDebut()",
        message: "La date de fin doit être après la date de début"
    )]
    private ?\DateTime $dateFin = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Le lieu est obligatoire')]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\-]+$/',
        message: 'Location must contain only letters'
    )]
    private ?string $lieu = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'La ville est obligatoire')]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\-]+$/',
        message: 'City name must contain only letters'
    )]
    private ?string $ville = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Positive(message: 'La capacité doit être un nombre positif')]
    private ?int $capaciteMax = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le type est obligatoire')]
    #[Assert\Choice(choices: self::TYPES, message: 'Type invalide')]
    private ?string $type = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le statut est obligatoire')]
    #[Assert\Choice(choices: self::STATUTS, message: 'Statut invalide')]
    private ?string $statut = 'PLANIFIE';

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    /**
     * @var Collection<int, Participation>
     */
    #[ORM\OneToMany(targetEntity: Participation::class, mappedBy: 'evenement', orphanRemoval: true)]
    private Collection $participations;

    /**
     * @var Collection<int, Guest>
     */
    #[ORM\OneToMany(targetEntity: Guest::class, mappedBy: 'evenement', orphanRemoval: true)]
    private Collection $guests;

    public function __construct()
    {
        $this->participations = new ArrayCollection();
        $this->guests = new ArrayCollection();
        $this->statut = 'PLANIFIE';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitre(): ?string
    {
        return $this->titre;
    }

    public function setTitre(?string $titre): static
    {
        $this->titre = $titre;
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

    public function getDateDebut(): ?\DateTime
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTime $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTime
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTime $dateFin): static
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getLieu(): ?string
    {
        return $this->lieu;
    }

    public function setLieu(?string $lieu): static
    {
        $this->lieu = $lieu;
        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(?string $ville): static
    {
        $this->ville = $ville;
        return $this;
    }

    public function getCapaciteMax(): ?int
    {
        return $this->capaciteMax;
    }

    public function setCapaciteMax(?int $capaciteMax): static
    {
        $this->capaciteMax = $capaciteMax;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;
        return $this;
    }

    /**
     * @return Collection<int, Participation>
     */
    public function getParticipations(): Collection
    {
        return $this->participations;
    }

    public function addParticipation(Participation $participation): static
    {
        if (!$this->participations->contains($participation)) {
            $this->participations->add($participation);
            $participation->setEvenement($this);
        }
        return $this;
    }

    public function removeParticipation(Participation $participation): static
    {
        if ($this->participations->removeElement($participation)) {
            if ($participation->getEvenement() === $this) {
                $participation->setEvenement(null);
            }
        }
        return $this;
    }

    /**
     * @return Collection<int, Guest>
     */
    public function getGuests(): Collection
    {
        return $this->guests;
    }

    public function addGuest(Guest $guest): static
    {
        if (!$this->guests->contains($guest)) {
            $this->guests->add($guest);
            $guest->setEvenement($this);
        }
        return $this;
    }

    public function removeGuest(Guest $guest): static
    {
        if ($this->guests->removeElement($guest)) {
            if ($guest->getEvenement() === $this) {
                $guest->setEvenement(null);
            }
        }
        return $this;
    }
}
