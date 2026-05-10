<?php

namespace App\Entity;

use App\Repository\ConsultationRepository;
use App\Entity\Dogs;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ConsultationRepository::class)]
class Consultation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['consultation'])]
    private ?int $id = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    #[Groups(['consultation'])]
    #[Assert\NotBlank(message: "Date is required")]
    #[Assert\Type("\DateTimeInterface", message: "The value {{ value }} is not a valid date.")]
    #[Assert\GreaterThanOrEqual(
        "today",
        message: "The date cannot be in the past. Please select today or a future date."
    )]
    private ?\DateTimeImmutable $date = null;

    #[ORM\Column(length: 50)]
    #[Groups(['consultation'])]
    #[Assert\NotBlank(message: "Type is required")]
    #[Assert\Length(
        min: 2,
        max: 50,
        minMessage: "Type must be at least {{ limit }} characters long",
        maxMessage: "Type cannot be longer than {{ limit }} characters"
    )]
    private ?string $type = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(['consultation'])]
    #[Assert\NotBlank(message: "Diagnostic is required")]
    #[Assert\Length(
        min: 10,
        minMessage: "Diagnostic must be at least {{ limit }} characters long"
    )]
    private ?string $diagnostic = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    #[Groups(['consultation'])]
    private ?string $traitement = null;

    #[ORM\ManyToOne(targetEntity: User::class, inversedBy: 'consultations')]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false)]
    #[Groups(['consultation'])]
    #[Assert\NotNull(message: "User is required")]
    private ?User $user = null;

    #[ORM\ManyToOne(targetEntity: Dogs::class, inversedBy: 'consultations')]
    #[ORM\JoinColumn(name: 'dog_id', referencedColumnName: 'id', nullable: false)]
    #[Assert\NotNull(message: "Dog is required")]
    private ?Dogs $chien = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeImmutable
    {
        return $this->date;
    }

    public function setDate(?\DateTimeImmutable $date): static
    {
        $this->date = $date;
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

    public function getDiagnostic(): ?string
    {
        return $this->diagnostic;
    }

    public function setDiagnostic(?string $diagnostic): static
    {
        $this->diagnostic = $diagnostic;
        return $this;
    }

    public function getTraitement(): ?string
    {
        return $this->traitement;
    }

    public function setTraitement(?string $traitement): static
    {
        $this->traitement = $traitement;
        return $this;
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

    public function getChien(): ?Dogs
    {
        return $this->chien;
    }

    public function setChien(?Dogs $chien): static
    {
        $this->chien = $chien;
        return $this;
    }

    // Alias methods for templates expecting 'dog' naming
    public function getDog(): ?Dogs
    {
        return $this->chien;
    }

    public function setDog(?Dogs $dog): static
    {
        $this->chien = $dog;
        return $this;
    }

    public function dog(): ?Dogs
    {
        return $this->chien;
    }
}