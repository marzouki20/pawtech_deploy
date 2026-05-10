<?php

namespace App\Entity;

use App\Repository\GuestRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: GuestRepository::class)]
class Guest
{
    public const ROLES = ['KEYNOTE_SPEAKER', 'SPEAKER', 'PANELIST', 'MODERATOR', 'WORKSHOP_LEADER'];

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le prénom est obligatoire')]
    #[Assert\Length(min: 2, max: 100)]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\-]+$/',
        message: 'First name must contain only letters'
    )]
    private ?string $prenom = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'Le nom est obligatoire')]
    #[Assert\Length(min: 2, max: 100)]
    #[Assert\Regex(
        pattern: '/^[a-zA-ZÀ-ÿ\s\-]+$/',
        message: 'Last name must contain only letters'
    )]
    private ?string $nom = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'L\'email est obligatoire')]
    #[Assert\Email(message: 'L\'email n\'est pas valide')]
    private ?string $email = null;

    #[ORM\Column(length: 20, nullable: true)]
    #[Assert\Regex(
        pattern: '/^[0-9]{8}$/',
        message: 'Phone number must be exactly 8 digits'
    )]
    private ?string $phone = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $organisation = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $bio = null;

    #[ORM\Column(length: 50)]
    #[Assert\NotBlank(message: 'Le rôle est obligatoire')]
    #[Assert\Choice(choices: self::ROLES, message: 'Rôle invalide')]
    private ?string $role = null;

    #[ORM\ManyToOne(inversedBy: 'guests')]
    private ?Evenement $evenement = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;
        return $this;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getFullName(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function setPhone(?string $phone): static
    {
        $this->phone = $phone;
        return $this;
    }

    public function getOrganisation(): ?string
    {
        return $this->organisation;
    }

    public function setOrganisation(?string $organisation): static
    {
        $this->organisation = $organisation;
        return $this;
    }

    public function getBio(): ?string
    {
        return $this->bio;
    }

    public function setBio(?string $bio): static
    {
        $this->bio = $bio;
        return $this;
    }

    public function getRole(): ?string
    {
        return $this->role;
    }

    public function setRole(string $role): static
    {
        $this->role = $role;
        return $this;
    }

    public function getEvenement(): ?Evenement
    {
        return $this->evenement;
    }

    public function setEvenement(?Evenement $evenement): static
    {
        $this->evenement = $evenement;
        return $this;
    }
}
