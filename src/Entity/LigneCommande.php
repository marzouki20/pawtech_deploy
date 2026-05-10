<?php

namespace App\Entity;

use App\Repository\LigneCommandeRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: LigneCommandeRepository::class)]
class LigneCommande
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    #[Assert\NotBlank(message: 'Quantity is required')]
    #[Assert\Type(type: 'integer', message: 'Quantity must be an integer')]
    #[Assert\Positive(message: 'Quantity must be greater than 0')]
    private ?int $quantite = null;

    #[ORM\Column(name: 'prix_unitaire', type: 'decimal', precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Unit price is required')]
    #[Assert\Type(type: 'numeric', message: 'Unit price must be a number')]
    #[Assert\Positive(message: 'Unit price must be greater than 0')]
    private ?string $prixUnitaire = null;

    #[ORM\ManyToOne(inversedBy: 'ligneCommandes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Product is required')]
    private ?Produit $produit = null;

    #[ORM\ManyToOne(inversedBy: 'ligneCommandes')]
    #[ORM\JoinColumn(nullable: false)]
    #[Assert\NotNull(message: 'Order is required')]
    private ?Commande $commande = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuantite(): ?int
    {
        return $this->quantite;
    }

    public function setQuantite(int $quantite): static
    {
        $this->quantite = $quantite;
        return $this;
    }

    public function getPrixUnitaire(): ?string
    {
        return $this->prixUnitaire;
    }

    public function setPrixUnitaire(string|float $prixUnitaire): static
    {
        $this->prixUnitaire = (string) $prixUnitaire;
        return $this;
    }

    public function getProduit(): ?Produit
    {
        return $this->produit;
    }

    public function setProduit(?Produit $produit): static
    {
        $this->produit = $produit;
        return $this;
    }

    public function getCommande(): ?Commande
    {
        return $this->commande;
    }

    public function setCommande(?Commande $commande): static
    {
        $this->commande = $commande;
        return $this;
    }

    public function calculerSousTotal(): float
    {
        return (float) ($this->quantite ?? 0) * (float) ($this->prixUnitaire ?? 0);
    }

    public function __toString(): string
    {
        return sprintf('%s x %s', $this->produit?->getNom() ?? 'Produit', $this->quantite ?? 0);
    }
}
