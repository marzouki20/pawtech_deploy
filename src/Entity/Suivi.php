<?php

namespace App\Entity;

use App\Repository\SuiviRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: SuiviRepository::class)]
#[ORM\Table(name: 'suivi')]
class Suivi
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['suivis'])]
    private ?int $id = null;

    #[ORM\Column(name: 'ai_analysis_report', type: Types::TEXT, nullable: true)]
    private ?string $aiAnalysisReport = null;

    #[ORM\Column(name: 'affected_body_parts', type: Types::JSON, nullable: true)]
    private ?array $affectedBodyParts = null;

    #[ORM\Column(name: 'emergency_level', length: 20, nullable: true)]
    private ?string $emergencyLevel = null;

    #[ORM\Column(name: 'etat', length: 50)]
    #[Groups(['suivis'])]
    #[Assert\NotBlank(message: "L'etat est requis")]
    #[Assert\Length(
        min: 3,
        max: 50,
        minMessage: "L'etat doit contenir au moins {{ limit }} caracteres",
        maxMessage: "L'etat ne peut pas depasser {{ limit }} caracteres"
    )]
    private ?string $etat = null;

    #[ORM\Column(name: 'recommandation', type: Types::TEXT)]
    #[Groups(['suivis'])]
    #[Assert\NotBlank(message: 'La recommandation est requise')]
    #[Assert\Length(
        min: 10,
        minMessage: 'La recommandation doit contenir au moins {{ limit }} caracteres'
    )]
    private ?string $recommandation = null;

    #[ORM\Column(name: 'type', length: 255)]
    #[Groups(['suivis'])]
    #[Assert\NotBlank(message: 'Le type de suivi est requis')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le type doit contenir au moins {{ limit }} caracteres',
        maxMessage: 'Le type ne peut pas depasser {{ limit }} caracteres'
    )]
    private ?string $type = null;

    #[ORM\Column(name: 'prochaine_visite', type: Types::DATETIME_MUTABLE, nullable: false)]
    #[Groups(['suivis'])]
    #[Assert\NotBlank(message: 'La date de prochaine visite est requise')]
    #[Assert\Type('\DateTimeInterface', message: 'La date de prochaine visite doit etre une date valide')]
    #[Assert\GreaterThanOrEqual('today', message: "La date de prochaine visite doit etre aujourd'hui ou dans le futur")]
    private ?\DateTimeInterface $prochaineVisite = null;

    #[ORM\ManyToOne(targetEntity: Consultation::class)]
    #[ORM\JoinColumn(name: 'consultation_id', nullable: false)]
    #[Groups(['suivis'])]
    #[Assert\NotNull(message: 'La consultation associee est requise')]
    private ?Consultation $consultation = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getAiAnalysisReport(): ?string
    {
        return $this->aiAnalysisReport;
    }

    public function setAiAnalysisReport(?string $aiAnalysisReport): static
    {
        $this->aiAnalysisReport = $aiAnalysisReport;

        return $this;
    }

    public function getAffectedBodyParts(): ?array
    {
        return $this->affectedBodyParts;
    }

    public function setAffectedBodyParts(?array $affectedBodyParts): static
    {
        $this->affectedBodyParts = $affectedBodyParts;

        return $this;
    }

    public function getEmergencyLevel(): ?string
    {
        if ($this->emergencyLevel !== null && $this->emergencyLevel !== '') {
            return strtolower($this->emergencyLevel);
        }

        $report = (string) ($this->aiAnalysisReport ?? '');
        if (preg_match('/Predicted emergency:\s*(critical|high|medium|low)/i', $report, $matches) === 1) {
            return strtolower($matches[1]);
        }

        return null;
    }

    public function setEmergencyLevel(?string $emergencyLevel): static
    {
        $this->emergencyLevel = $emergencyLevel !== null ? strtolower(trim($emergencyLevel)) : null;

        return $this;
    }

    public function getEmergencyLevelDisplay(): string
    {
        return match ($this->getEmergencyLevel()) {
            'critical' => 'Critical',
            'high' => 'High',
            'medium' => 'Medium',
            'low' => 'Low',
            default => 'Not analyzed',
        };
    }

    public function getEmergencyLevelClass(): string
    {
        return match ($this->getEmergencyLevel()) {
            'critical' => 'bg-red-100 text-red-800 border-red-300',
            'high' => 'bg-orange-100 text-orange-800 border-orange-300',
            'medium' => 'bg-yellow-100 text-yellow-800 border-yellow-300',
            'low' => 'bg-green-100 text-green-800 border-green-300',
            default => 'bg-gray-100 text-gray-800 border-gray-300',
        };
    }

    public function getEtat(): ?string
    {
        return $this->etat;
    }

    public function setEtat(?string $etat): static
    {
        $this->etat = $etat;

        return $this;
    }

    public function getRecommandation(): ?string
    {
        return $this->recommandation;
    }

    public function setRecommandation(?string $recommandation): static
    {
        $this->recommandation = $recommandation;

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

    public function getProchaineVisite(): ?\DateTimeInterface
    {
        return $this->prochaineVisite;
    }

    public function setProchaineVisite(?\DateTimeInterface $prochaineVisite): static
    {
        $this->prochaineVisite = $prochaineVisite;

        return $this;
    }

    public function getConsultation(): ?Consultation
    {
        return $this->consultation;
    }

    public function setConsultation(?Consultation $consultation): static
    {
        $this->consultation = $consultation;

        return $this;
    }

    public function getChienNom(): ?string
    {
        if ($this->consultation && $this->consultation->getDog()) {
            return $this->consultation->getDog()->getName();
        }

        return null;
    }

    public function __toString(): string
    {
        return 'Suivi #' . $this->id . ' - ' . $this->type . ' (' . $this->etat . ')';
    }
}
