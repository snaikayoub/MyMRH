<?php
// src/Repository/PrimePerformanceRepository.php

namespace App\Repository;

use App\Entity\PrimePerformance;
use App\Entity\User;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

class PrimePerformanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrimePerformance::class);
    }

    /**
     * Compte le nombre de primes pour un validateur de division,
     * selon le type de paie et le statut.
     */
    public function countByTypeAndStatus(User $responsable, string $type, string $status): int
    {
        return (int) $this->createQueryBuilder('pp')
            ->select('COUNT(pp.id)')
            ->join('pp.periodePaie', 'p')
            ->join('pp.employee', 'e')
            ->join('e.employeeSituations', 'es')
            ->join('es.service', 's')
            ->join('s.division', 'd')
            ->where('pp.status = :status')
            ->andWhere('p.typePaie = :type')
            ->andWhere('d.validateurDivision = :resp')
            ->setParameter('status', $status)
            ->setParameter('type',   $type)
            ->setParameter('resp',   $responsable)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Récupère les primes d’une division pour un validateur donné,
     * selon le statut et le type de paie.
     */
    public function findByDivisionAndStatusAndType(User $responsable, string $status, string $type): array
    {
        return $this->createQueryBuilder('pp')
            ->join('pp.periodePaie',        'p')
            ->join('pp.employee',           'e')
            ->join('e.employeeSituations',  'es')
            ->join('es.service',            's')
            ->join('s.division',            'd')
            ->where('pp.status = :status')
            ->andWhere('p.typePaie = :type')
            ->andWhere('d.validateurDivision = :resp')
            ->setParameter('status', $status)
            ->setParameter('type',   $type)
            ->setParameter('resp',   $responsable)
            ->orderBy('p.annee', 'DESC')
            ->addOrderBy('p.mois', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
