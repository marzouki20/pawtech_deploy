<?php

namespace App\Entity;

use App\Repository\AdoptionRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AdoptionRepository::class)]
class Adoption
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    // @phpstan-ignore-next-line Doctrine sets generated IDs at runtime.
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'User is required.')]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Dogs::class)]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Dog is required.')]
    private ?Dogs $dog = null;

    #[ORM\Column(type: 'datetime_immutable')]
    #[Assert\NotNull(message: 'Application date is required.')]
    #[Assert\LessThanOrEqual('now', message: 'Application date cannot be in the future.')]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\NotNull(message: 'Applicant age is required.')]
    #[Assert\Range(min: 18, max: 120, notInRangeMessage: 'Applicant age must be between {{ min }} and {{ max }}.')]
    private ?int $applicantAge = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\NotNull(message: 'Income is required.')]
    #[Assert\PositiveOrZero(message: 'Income must be zero or positive.')]
    #[Assert\LessThanOrEqual(value: 1000000, message: 'Income cannot exceed 1000000.')]
    private ?string $income = null;

    #[ORM\Column(type: 'string', length: 100, nullable: true)]
    #[Assert\NotBlank(message: 'Housing type is required.')]
    #[Assert\Choice(choices: ['apartment', 'house', 'farm', 'other'], message: 'Invalid housing type selected.')]
    #[Assert\Length(max: 100, maxMessage: 'Housing type cannot be longer than 100 characters.')]
    private ?string $housingType = null;

    #[ORM\Column(type: 'boolean', nullable: true)]
    private ?bool $hasYard = false;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\NotNull(message: 'Family size is required.')]
    #[Assert\Range(min: 1, max: 20, notInRangeMessage: 'Family size must be between {{ min }} and {{ max }}.')]
    private ?int $familySize = null;

    #[ORM\Column(type: 'integer', nullable: true)]
    #[Assert\NotNull(message: 'Hours away per day is required.')]
    #[Assert\Range(min: 0, max: 24, notInRangeMessage: 'Hours away per day must be between {{ min }} and {{ max }}.')]
    private ?int $hoursAwayPerDay = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getDog(): ?Dogs
    {
        return $this->dog;
    }

    public function setDog(?Dogs $dog): self
    {
        $this->dog = $dog;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): self
    {
        if ($createdAt instanceof \DateTimeImmutable) {
            $this->createdAt = $createdAt;
        } else {
            $this->createdAt = \DateTimeImmutable::createFromMutable($createdAt);
        }

        return $this;
    }

    public function getApplicantAge(): ?int
    {
        return $this->applicantAge;
    }

    public function setApplicantAge(?int $applicantAge): self
    {
        $this->applicantAge = $applicantAge;

        return $this;
    }

    public function getIncome(): ?string
    {
        return $this->income;
    }

    public function setIncome(?string $income): self
    {
        $this->income = $income;

        return $this;
    }

    public function getHousingType(): ?string
    {
        return $this->housingType;
    }

    public function setHousingType(?string $housingType): self
    {
        $this->housingType = $housingType;

        return $this;
    }

    public function getHasYard(): ?bool
    {
        return $this->hasYard;
    }

    public function setHasYard(?bool $hasYard): self
    {
        $this->hasYard = $hasYard;

        return $this;
    }

    public function getFamilySize(): ?int
    {
        return $this->familySize;
    }

    public function setFamilySize(?int $familySize): self
    {
        $this->familySize = $familySize;

        return $this;
    }

    public function getHoursAwayPerDay(): ?int
    {
        return $this->hoursAwayPerDay;
    }

    public function setHoursAwayPerDay(?int $hoursAwayPerDay): self
    {
        $this->hoursAwayPerDay = $hoursAwayPerDay;

        return $this;
    }
}
