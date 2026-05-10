<?php

namespace App\Entity;

use App\Repository\DonationRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: DonationRepository::class)]
class Donation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank(message: "Amount is required")]
    #[Assert\Type(type: 'numeric', message: "Amount must be a number")]
    #[Assert\Positive(message: "Amount must be greater than 0")]
    private ?string $montant = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank(message: "Date is required")]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $donateur = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Email(message: "Email '{{ value }}' is not a valid email address")]
    #[Assert\Length(max: 255)]
    private ?string $email = null;

    #[ORM\Column(length: 100, nullable: true)]
    #[Assert\Length(max: 100)]
    private ?string $reference = null;

    #[ORM\Column]
    private ?bool $statut = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMontant(): ?string
    {
        return $this->montant;
    }

    public function setMontant(string $montant): static
    {
        $this->montant = $montant;
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

    public function getDonateur(): ?string
    {
        return $this->donateur;
    }

    public function setDonateur(?string $donateur): static
    {
        $this->donateur = $donateur;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getReference(): ?string
    {
        return $this->reference;
    }

    public function setReference(?string $reference): static
    {
        $this->reference = $reference;
        return $this;
    }

    public function getStatut(): ?bool
    {
        return $this->statut;
    }

    public function setStatut(bool $statut): static
    {
        $this->statut = $statut;
        return $this;
    }
}