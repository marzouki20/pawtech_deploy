<?php

namespace App\Entity;

use App\Repository\ProduitRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ProduitRepository::class)]
#[ORM\Table(name: 'produit')]
class Produit
{
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank(message: 'Name is required')]
    #[Assert\Length(
        min: 2,
        max: 255,
        minMessage: 'Name must be at least {{ limit }} characters',
        maxMessage: 'Name cannot exceed {{ limit }} characters'
    )]
    private ?string $nom = null;

    #[ORM\Column(type: 'float')]
    #[Assert\NotBlank(message: 'Price is required')]
    #[Assert\Type(type: 'float', message: 'Price must be a number')]
    #[Assert\Positive(message: 'Price must be greater than 0')]
    private ?float $prix = null;

    #[ORM\ManyToOne(targetEntity: Categorie::class)]
    #[ORM\JoinColumn(name: 'categorie', referencedColumnName: 'id', nullable: false)] 
    #[Assert\NotNull(message: 'Category is required')]
    private ?Categorie $categorie = null;

    #[ORM\Column(length: 255)]
    //#[Assert\NotBlank(message: 'Image is required')]
    #[Assert\Length(
        max: 255,
        maxMessage: 'Image cannot exceed {{ limit }} characters'
    )]
    private ?string $image = null;

    #[ORM\Column(type: 'integer')]
    #[Assert\NotBlank(message: 'Quantity is required')]
    #[Assert\Type(type: 'integer', message: 'Quantity must be an integer')]
    #[Assert\PositiveOrZero(message: 'Quantity must be positive or zero')]
    private ?int $quantite = null;

    #[ORM\Column(name: 'seuil_alert', type: 'integer')]
    #[Assert\NotBlank(message: 'Alert threshold is required')]
    #[Assert\Type(type: 'integer', message: 'Alert threshold must be an integer')]
    #[Assert\PositiveOrZero(message: 'Alert threshold must be positive or zero')]
    private ?int $seuilAlert = null;

    #[ORM\Column(name: 'rating_total', type: 'float', options: ['default' => 0])]
    private float $ratingTotal = 0;

    #[ORM\Column(name: 'rating_count', type: 'integer', options: ['default' => 0])]
    private int $ratingCount = 0;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPrix(): ?float
    {
        return $this->prix;
    }

    public function setPrix(float $prix): static
    {
        $this->prix = $prix;
        return $this;
    }

    public function getCategorie(): ?Categorie
    {
        return $this->categorie;
    }

    public function setCategorie(?Categorie $categorie): static
    {
        $this->categorie = $categorie;
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

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(?int $quantite): static
    {
        $this->quantite = $quantite;
        return $this;
    }

    public function getSeuilAlert(): ?int
    {
        return $this->seuilAlert;
    }

    public function setSeuilAlert(?int $seuilAlert): static
    {
        $this->seuilAlert = $seuilAlert;
        return $this;
    }

    public function getRatingTotal(): float
    {
        return isset($this->ratingTotal) ? $this->ratingTotal : 0.0;
    }

    public function setRatingTotal(float $ratingTotal): static
    {
        $this->ratingTotal = $ratingTotal;
        return $this;
    }

    public function getRatingCount(): int
    {
        return isset($this->ratingCount) ? $this->ratingCount : 0;
    }

    public function setRatingCount(int $ratingCount): static
    {
        $this->ratingCount = $ratingCount;
        return $this;
    }

    public function getAverageRating(): float
    {
        $count = $this->getRatingCount();
        if ($count === 0) {
            return 0;
        }
        return round($this->getRatingTotal() / $count, 1);
    }

    public function addRating(float $rating): void
    {
        $this->ratingTotal = $this->getRatingTotal() + $rating;
        $this->ratingCount = $this->getRatingCount() + 1;
    }

    public function verifierDisponibilite(): bool
    {
        return ($this->quantite ?? 0) > 0;
    }

    public function __toString(): string
    {
        return $this->nom ?? '';
    }

}
