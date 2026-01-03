<?php

namespace App\Entity;

use App\Repository\VoyageDeplacementRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: VoyageDeplacementRepository::class)]
class VoyageDeplacement
{
    public const STATUS_DRAFT = 'draft';
    public const STATUS_SUBMITTED = 'submitted';
    public const STATUS_VALIDATED = 'validated';
    public const STATUS_REJECTED = 'rejected';
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'voyageDeplacements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?Employee $employee = null;

    #[ORM\ManyToOne(inversedBy: 'voyageDeplacements')]
    #[ORM\JoinColumn(nullable: false)]
    private ?PeriodePaie $periodePaie = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $typeVoyage = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $motif = null;

    #[ORM\Column(length: 255)]
    private ?string $modeTransport = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateHeureDepart = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $dateHeureRetour = null;

    #[ORM\Column]
    private ?float $distanceKm = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $commentaire = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmployee(): ?Employee
    {
        return $this->employee;
    }

    public function setEmployee(?Employee $employee): static
    {
        $this->employee = $employee;

        return $this;
    }

    public function getPeriodePaie(): ?PeriodePaie
    {
        return $this->periodePaie;
    }

    public function setPeriodePaie(?PeriodePaie $periodePaie): static
    {
        $this->periodePaie = $periodePaie;

        return $this;
    }

    public function getTypeVoyage(): ?string
    {
        return $this->typeVoyage;
    }

    public function setTypeVoyage(?string $typeVoyage): static
    {
        $this->typeVoyage = $typeVoyage;

        return $this;
    }

    public function getMotif(): ?string
    {
        return $this->motif;
    }

    public function setMotif(?string $motif): static
    {
        $this->motif = $motif;

        return $this;
    }

    public function getModeTransport(): ?string
    {
        return $this->modeTransport;
    }

    public function setModeTransport(string $modeTransport): static
    {
        $this->modeTransport = $modeTransport;

        return $this;
    }

    public function getDateHeureDepart(): ?\DateTimeInterface
    {
        return $this->dateHeureDepart;
    }

    public function setDateHeureDepart(\DateTimeInterface $dateHeureDepart): static
    {
        $this->dateHeureDepart = $dateHeureDepart;

        return $this;
    }

    public function getDateHeureRetour(): ?\DateTimeInterface
    {
        return $this->dateHeureRetour;
    }

    public function setDateHeureRetour(\DateTimeInterface $dateHeureRetour): static
    {
        $this->dateHeureRetour = $dateHeureRetour;

        return $this;
    }

    public function getDistanceKm(): ?float
    {
        return $this->distanceKm;
    }

    public function setDistanceKm(float $distanceKm): static
    {
        $this->distanceKm = $distanceKm;

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

    public function getCommentaire(): ?string
    {
        return $this->commentaire;
    }

    public function setCommentaire(?string $commentaire): static
    {
        $this->commentaire = $commentaire;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }
}
