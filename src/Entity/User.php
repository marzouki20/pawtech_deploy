<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;


    /**
     * @var Collection<int, Participation>
     */

    #[ORM\OneToMany(targetEntity: Participation::class, mappedBy: 'user', orphanRemoval: true)]
    private Collection $participations;




    #[ORM\Column(length: 30)]
    #[Assert\NotBlank(message: "The Last name cannot be empty.")]
    #[Assert\Length(
        min: 2,
        max: 30,

        minMessage: "The last name must be at least {{ limit }} characters long.",
        maxMessage: "The last name cannot be longer than {{ limit }} characters."
    )]
    #[Assert\Regex(
        pattern: "/^[a-zA-ZÀ-ÿ\s'-]+$/",
        message: "The last name can only contain letters, spaces, apostrophes, or hyphens."

    )]
    private ?string $nom = null;

    #[ORM\Column(length: 30)]
    #[Assert\NotBlank(message: "The first name cannot be empty.")]
    #[Assert\Length(
        min: 3,
        max: 100,

        minMessage: "The first name must be at least {{ limit }} characters long.",
        maxMessage: "The first name cannot be longer than {{ limit }} characters."
    )]
    #[Assert\Regex(
        pattern: "/^[a-zA-ZÀ-ÿ\s'-]+$/",
        message: "The first name can only contain letters, spaces, apostrophes, or hyphens."

    )]
    private ?string $prenom = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "The email cannot be empty.")]

    #[Assert\Email(message: "The email '{{ value }}' is not valid.")]

    private ?string $email = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: "The phone number cannot be empty.")]
    #[Assert\Regex(
        pattern: "/^\d+$/",

        message: "The phone number must contain only digits."
    )]
    #[Assert\Length(
        exactMessage: "The phone number must be exactly {{ limit }} digits.",

        exactly :8
    )]
    private ?int $telephone = null;

    #[ORM\Column(length: 20)]
    #[Assert\NotBlank(message: "The role cannot be empty.")]
    private ?string $role = null;

    #[ORM\Column(length: 10)]
    #[Assert\NotBlank(message: "The status cannot be empty.")]
    #[Assert\Choice(
        choices: ['Actif', 'Inactif','actif', 'inactif'],


        message: "The selected status is not valid."

    )]
    private ?string $status = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: "The password cannot be empty.")]
    #[Assert\Length(
        min: 8,
        max: 255,

        minMessage: "The password must be at least {{ limit }} characters long.",
        maxMessage: "The password cannot be longer than {{ limit }} characters."
    )]
    #[Assert\Regex(
        pattern: "/^(?=.*[a-zA-Z])(?=.*\d).+$/",
        message: "Le mot de passe doit contenir au moins une lettre et un chiffre."

    )]
    private ?string $password = null;

    #[ORM\Column(type: 'text', options: ['comment' => 'Stores avatar as file path or Base64 data URI'], columnDefinition: 'LONGTEXT')]
    private ?string $user_image = null;


    #[ORM\Column(type: 'text', options: ['comment' => 'Stores face/avatar as Base64 data URI'], columnDefinition: 'LONGTEXT')]
    private ?string $user_face = null;



    #[ORM\Column(nullable: true)]
    #[Assert\NotBlank(message: "The order number cannot be empty.")]
    private ?int $order_number = null;

    #[ORM\Column(length: 10, nullable: true)]
    #[Assert\NotBlank(message: "The matricule cannot be empty.")]
    private ?string $matricule = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\NotBlank(message: "The affected zone cannot be empty.")]
    private ?string $zone_affectee = null;


    public function __construct()
    {

        $this->participations = new ArrayCollection();

       
        $this->status = 'Actif';
        $this->role = 'Client';
        $this->order_number = 0; 
        $this->matricule = 'N/A'; 
        $this->zone_affectee = 'N/A'; 

    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function setId(int $id): static
    {
        $this->id = $id;

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

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
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

    public function getTelephone(): ?int
    {
        return $this->telephone;
    }

    public function setTelephone(int $telephone): static
    {
        $this->telephone = $telephone;

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

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $role = $this->role ?? 'ROLE_USER';
        $normalized = strtoupper(str_replace(' ', '_', $role));

        return [str_starts_with($normalized, 'ROLE_') ? $normalized : 'ROLE_'.$normalized];
    }

    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
    }

    public function getUserImage(): ?string
    {
        return $this->user_image;
    }

    public function setUserImage(string $user_image): static
    {
        $this->user_image = $user_image;

        return $this;
    }

    public function getOrderNumber(): ?int
    {
        return $this->order_number;
    }

    public function setOrderNumber(?int $order_number): static
    {
        $this->order_number = $order_number;

        return $this;
    }

    public function getMatricule(): ?string
    {
        return $this->matricule;
    }

    public function setMatricule(?string $matricule): static
    {
        $this->matricule = $matricule;

        return $this;
    }

    public function getZoneAffectee(): ?string
    {
        return $this->zone_affectee;
    }

    public function setZoneAffectee(?string $zone_affectee): static
    {
        $this->zone_affectee = $zone_affectee;

        return $this;
    }





 public function getFullName(): string
    {
        return $this->prenom . ' ' . $this->nom;
    }

    /**
     * @return Collection<int, Participation>
     */
    public const DELETE_ERROR_MESSAGE = 'An error occurred while deleting the user. Please try again.';

    public function getParticipations(): Collection
    {
        return $this->participations;
    }

    public function addParticipation(Participation $participation): static
    {
        if (!$this->participations->contains($participation)) {
            $this->participations->add($participation);
            $participation->setUser($this);
        }
        return $this;
    }

    public function removeParticipation(Participation $participation): static
    {
        if ($this->participations->removeElement($participation)) {
            if ($participation->getUser() === $this) {
                $participation->setUser(null);
            }
        }
        return $this;
    }

    // Alias methods for compatibility with event registration
    public function getPhone(): ?string
    {
        return $this->telephone ? (string) $this->telephone : null;
    }

    public function setPhone(?string $phone): static
    {
        $this->telephone = $phone ? (int) preg_replace('/[^0-9]/', '', $phone) : null;
        return $this;
    }

    public function getUserFace(): ?string
    {
        return $this->user_face;
    }

    public function setUserFace(string $user_face): static
    {
        $this->user_face = $user_face;

        return $this;
    }




}
