<?php

namespace App\Entity;

use App\Repository\DogsRepository;
use Symfony\Component\Serializer\Annotation\Groups;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;








#[ORM\Entity(repositoryClass: DogsRepository::class)]
class Dogs
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['dogs'])] // Added back for ID
    // @phpstan-ignore-next-line Doctrine sets generated IDs at runtime.
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Name cannot be blank.")]
    #[Groups(['dogs'])] // Keep for search
    private ?string $name = null;
    
    #[ORM\Column]
    #[Assert\NotBlank(message: "Age is required.")]
    #[Assert\Range(min: 0, max: 30, notInRangeMessage: "Age must be between {{ min }} and {{ max }} years.")]
    #[Groups(['dogs'])] // Added back
    private ?int $age = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Breed is required.")]
    #[Groups(['dogs'])] // Added back - NEEDED for search!
    private ?string $breed = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Gender is required.")]
    #[Assert\Choice(choices: ["Male", "Female"], message: "Gender must be Male or Female.")]
    #[Groups(['dogs'])] // Added back
    private ?string $gender = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Size is required.")]
    #[Assert\Choice(choices: ["XS", "S", "M", "L", "XL"], message: "Size must be XS, S, M, L, or XL.")]
    #[Groups(['dogs'])] // Added back
    private ?string $size = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "Weight is required.")]
    #[Assert\Positive(message: "Weight must be a positive number.")]
    #[Groups(['dogs'])] // Added back
    private ?float $weight = null;

    #[ORM\Column]
    #[Groups(['dogs'])] // Added back
    private ?bool $vaccinated = null;

    #[ORM\Column]
    #[Groups(['dogs'])] // Added back
    private ?bool $friendly_with_kids = null;

    #[ORM\Column]
    #[Groups(['dogs'])] // Added back
    private ?bool $friendly_with_dogs = null;

    #[ORM\Column]
    #[Groups(['dogs'])] // Added back
    private ?bool $friendly_with_cats = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Health status is required.")]
    #[Assert\Choice(choices: ["Healthy", "Minor issues", "Chronic"], message: "Invalid health status.")]
    #[Groups(['dogs'])] // Added back
    private ?string $health_status = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: "Adoption status is required.")]
    #[Assert\Choice(choices: ["Available", "Reserved", "Adopted", "Streetdog"], message: "Invalid adoption status.")]
    #[Groups(['dogs'])] // Added back
    private ?string $adoption_status = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "Arrival date is required.")]
    #[Groups(['dogs'])] // Added back
    private ?\DateTime $arrival_date = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: "Image is required.")]
    #[Groups(['dogs'])] // Added back
    private ?string $image = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['dogs'])] // Added back
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Groups(['dogs'])] // IMPORTANT: Added back - This is used in your search and JavaScript!
    private ?string $microchip_number = null;

    #[ORM\OneToOne(mappedBy: 'dog', targetEntity: Ziptag::class, cascade: ['persist'])]
    #[Groups(['dogs'])] // Added back if you need ziptag data
    private ?Ziptag $ziptag = null;



    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'dogs')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: true)]
    #[Groups(['dogs'])]
    private ?User $user = null;

    


    public function __toString(): string
    {
        if ($this->name !== null && $this->name !== '') {
            return $this->name;
        }
        if ($this->user !== null) {
            return (string) $this->user->getId();
        }
        return 'Dog';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): static
    {
        $this->user = $user;
        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getAge(): ?int
    {
        return $this->age;
    }

    public function setAge(?int $age): static
    {
        $this->age = $age;

        return $this;
    }

    public function getBreed(): ?string
    {
        return $this->breed;
    }

    public function setBreed(?string $breed): static
    {
        $this->breed = $breed;

        return $this;
    }

    public function getGender(): ?string
    {
        return $this->gender;
    }

    public function setGender(?string $gender): static
    {
        $this->gender = $gender;

        return $this;
    }

    public function getSize(): ?string
    {
        return $this->size;
    }

    public function setSize(?string $size): static
    {
        $this->size = $size;

        return $this;
    }

    public function getWeight(): ?float
    {
        return $this->weight;
    }

    public function setWeight(?float $weight): static
    {
        $this->weight = $weight;

        return $this;
    }

    public function isVaccinated(): ?bool
    {
        return $this->vaccinated;
    }

    public function setVaccinated(bool $vaccinated): static
    {
        $this->vaccinated = $vaccinated;

        return $this;
    }

    public function isFriendlyWithKids(): ?bool
    {
        return $this->friendly_with_kids;
    }

    public function setFriendlyWithKids(bool $friendly_with_kids): static
    {
        $this->friendly_with_kids = $friendly_with_kids;

        return $this;
    }

    public function isFriendlyWithDogs(): ?bool
    {
        return $this->friendly_with_dogs;
    }

    public function setFriendlyWithDogs(bool $friendly_with_dogs): static
    {
        $this->friendly_with_dogs = $friendly_with_dogs;

        return $this;
    }

    public function isFriendlyWithCats(): ?bool
    {
        return $this->friendly_with_cats;
    }

    public function setFriendlyWithCats(bool $friendly_with_cats): static
    {
        $this->friendly_with_cats = $friendly_with_cats;

        return $this;
    }

    public function getHealthStatus(): ?string
    {
        return $this->health_status;
    }

    public function setHealthStatus(?string $health_status): static
    {
        $this->health_status = $health_status;

        return $this;
    }

    public function getAdoptionStatus(): ?string
    {
        return $this->adoption_status;
    }

    public function setAdoptionStatus(?string $adoption_status): static
    {
        $this->adoption_status = $adoption_status;

        return $this;
    }

    public function getArrivalDate(): ?\DateTime
    {
        return $this->arrival_date;
    }

    public function setArrivalDate(?\DateTime $arrival_date): static
    {
        $this->arrival_date = $arrival_date;

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

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;

        return $this;
    }

    public function getMicrochipNumber(): ?string
    {
        return $this->microchip_number;
    }

    public function setMicrochipNumber(?string $microchip_number): static
    {
        $this->microchip_number = $microchip_number;

        return $this;
    }

    public function getZiptag(): ?Ziptag
    {
        return $this->ziptag;
    }

    public function setZiptag(?Ziptag $ziptag): static
    {
        $this->ziptag = $ziptag;

        if ($ziptag && $ziptag->getDog() !== $this) {
            $ziptag->setDog($this);
        }

        return $this;
    }
    ////////////////////
 
}
