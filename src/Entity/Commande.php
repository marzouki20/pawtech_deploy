<?php

namespace App\Entity;

use App\Repository\CommandeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CommandeRepository::class)]
#[ORM\Table(name: 'commande')]
class Commande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'date')]
    #[Assert\NotBlank(message: 'Date is required')]
    #[Assert\Type(type: '\DateTimeInterface', message: 'Date must be a valid date')]
    #[Assert\LessThanOrEqual(
        value: 'today',
        message: 'Date cannot be in the future'
    )]
    private ?\DateTimeInterface $date = null;

    #[ORM\Column(type: 'float')]
    #[Assert\NotBlank(message: 'Total is required')]
    #[Assert\Type(type: 'float', message: 'Total must be a number')]
    #[Assert\PositiveOrZero(message: 'Total must be positive or zero')]
    private ?float $total = null;

    #[ORM\Column(type: 'boolean')]
    private ?bool $statut = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDate(): ?\DateTimeInterface
    {
        return $this->date;
    }

    public function setDate(?\DateTimeInterface $date): static
    {
        $this->date = $date;
        return $this;
    }

    public function getTotal(): ?float
    {
        return $this->total;
    }

    public function setTotal(float $total): static
    {
        $this->total = $total;
        return $this;
    }

    public function isStatut(): ?bool
    {
        return $this->statut;
    }

    public function setStatut(bool $statut): static
    {
        $this->statut = $statut;
        return $this;
    }

    public function __toString(): string
    {
        return 'Order #' . ($this->id ?? '');
    }
}