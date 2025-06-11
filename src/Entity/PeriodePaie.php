<?php

namespace App\Entity;

use App\Repository\PeriodePaieRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Symfony\Component\Validator\Constraints as Assert;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: PeriodePaieRepository::class)]
class PeriodePaie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\Choice(['mensuelle', 'quinzaine'])]
    private ?string $typePaie = null;

    #[ORM\Column]
    private ?int $mois = null;

    #[ORM\Column]
    private ?int $annee = null;

    #[ORM\Column(nullable: true)]
    #[Assert\Range(min: 1, max: 2)]
    private ?int $quinzaine = null;

    #[ORM\Column(length: 255)]
    private ?string $statut = null;

    /**
     * @var Collection<int, PrimePerformance>
     */
    #[ORM\OneToMany(targetEntity: PrimePerformance::class, mappedBy: 'periodePaie')]
    private Collection $primePerformances;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $scoreEquipe = null;

    #[ORM\Column(type: Types::DECIMAL, precision: 5, scale: 2, nullable: true)]
    private ?string $scoreCollectif = null;


    public function __construct()
    {
        $this->primePerformances = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTypePaie(): ?string
    {
        return $this->typePaie;
    }

    public function setTypePaie(string $typePaie): static
    {
        $this->typePaie = $typePaie;

        return $this;
    }

    public function getMois(): ?int
    {
        return $this->mois;
    }

    public function setMois(int $mois): static
    {
        $this->mois = $mois;

        return $this;
    }

    public function getAnnee(): ?int
    {
        return $this->annee;
    }

    public function setAnnee(int $annee): static
    {
        $this->annee = $annee;

        return $this;
    }

    public function getQuinzaine(): ?int
    {
        return $this->quinzaine;
    }

    public function setQuinzaine(?int $quinzaine): static
    {
        $this->quinzaine = $quinzaine;

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

    /**
     * @return Collection<int, PrimePerformance>
     */
    public function getPrimePerformances(): Collection
    {
        return $this->primePerformances;
    }

    public function addPrimePerformance(PrimePerformance $primePerformance): static
    {
        if (!$this->primePerformances->contains($primePerformance)) {
            $this->primePerformances->add($primePerformance);
            $primePerformance->setPeriodePaie($this);
        }

        return $this;
    }

    public function removePrimePerformance(PrimePerformance $primePerformance): static
    {
        if ($this->primePerformances->removeElement($primePerformance)) {
            // set the owning side to null (unless already changed)
            if ($primePerformance->getPeriodePaie() === $this) {
                $primePerformance->setPeriodePaie(null);
            }
        }

        return $this;
    }

    /**
     * String representation of the PeriodePaie
     */
    public function __toString(): string
    {
        return $this->typePaie . ' - ' . $this->mois . '/' . $this->annee .
            ($this->quinzaine ? ' (Q' . $this->quinzaine . ')' : '');
    }

    public function getScoreEquipe(): ?string
    {
        return $this->scoreEquipe;
    }

    public function setScoreEquipe(?string $scoreEquipe): static
    {
        $this->scoreEquipe = $scoreEquipe;

        return $this;
    }

    public function getScoreCollectif(): ?string
    {
        return $this->scoreCollectif;
    }

    public function setScoreCollectif(?string $scoreCollectif): static
    {
        $this->scoreCollectif = $scoreCollectif;

        return $this;
    }
}