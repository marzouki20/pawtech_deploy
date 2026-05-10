<?php

namespace App\Entity;

use App\Repository\AlertRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AlertRepository::class)]
class Alert
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Ce champ ne peut pas être vide')]
    #[Assert\Choice(choices: ['TECHNICAL', 'DANGER_DOG', 'HEALTH_ALERT'], message: 'Le type doit être TECHNICAL, DANGER_DOG ou HEALTH_ALERT')]
    private ?string $type = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Assert\NotBlank(message: 'Ce champ ne peut pas être vide')]
    private ?string $message = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Ce champ ne peut pas être vide')]
    #[Assert\Positive(message: 'Ce champ doit être un nombre positif')]
    private ?int $prioritee = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Ce champ ne peut pas être vide')]
    #[Assert\Choice(choices: ['unread', 'read'], message: 'Le statut doit être "unread" ou "read"')]
    private ?string $statut = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Ce champ ne peut pas être vide')]
    private ?\DateTime $date = null;

    #[ORM\Column(name: 'user_id', nullable: true)]
    private ?int $userId = null;

    #[ORM\ManyToOne(inversedBy: 'alerts')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ObservationStation $station = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getPrioritee(): ?int
    {
        return $this->prioritee;
    }

    public function setPrioritee(int $prioritee): static
    {
        $this->prioritee = $prioritee;

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

    public function getDate(): ?\DateTime
    {
        return $this->date;
    }

    public function setDate(\DateTime $date): static
    {
        $this->date = $date;

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

    public function getUserId(): ?int
    {
        return $this->userId;
    }

    public function setUserId(?int $userId): static
    {
        $this->userId = $userId;

        return $this;
    }
}
