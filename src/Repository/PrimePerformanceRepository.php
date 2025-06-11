<?php

namespace App\Repository;

use App\Entity\User;
use App\Entity\PrimePerformance;
use Doctrine\Persistence\ManagerRegistry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @extends ServiceEntityRepository<PrimePerformance>
 */
class PrimePerformanceRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, PrimePerformance::class);
    }

    //    /**
    //     * @return PrimePerformance[] Returns an array of PrimePerformance objects
    //     */
    //    public function findByExampleField($value): array
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->orderBy('p.id', 'ASC')
    //            ->setMaxResults(10)
    //            ->getQuery()
    //            ->getResult()
    //        ;
    //    }

    //    public function findOneBySomeField($value): ?PrimePerformance
    //    {
    //        return $this->createQueryBuilder('p')
    //            ->andWhere('p.exampleField = :val')
    //            ->setParameter('val', $value)
    //            ->getQuery()
    //            ->getOneOrNullResult()
    //        ;
    //    }
    public function findPrimesEnAttenteValidationServiceParResponsable(User $responsable): array
    {
        return $this->createQueryBuilder('pp')
            ->join('pp.employee', 'e')
            ->join('e.employeeSituations', 'es')
            ->andWhere('es.endDate IS NULL OR es.endDate >= CURRENT_DATE()')
            ->join('es.service', 's')
            ->where('pp.status = :status')
            ->andWhere('s.validateurService = :resp')
            ->setParameter('status', PrimePerformance::STATUS_SUBMITTED)
            ->setParameter('resp', $responsable)
            ->orderBy('pp.periodePaie', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByServiceAndStatus(User $responsable, string $status): array
    {
        return $this->createQueryBuilder('pp')
            ->join('pp.employee', 'e')
            ->join('e.employeeSituations', 'es')
            ->join('es.service', 's')
            ->where('pp.status = :status')
            ->andWhere('s.validateurService = :resp')
            ->andWhere('es.endDate IS NULL OR es.endDate >= CURRENT_DATE()')
            ->setParameter('status', $status)
            ->setParameter('resp', $responsable)
            ->orderBy('pp.periodePaie', 'DESC')
            ->getQuery()
            ->getResult();
    }
    public function findByServiceAndStatusAndType(User $responsable, string $status, string $type): array
    {
        return $this->createQueryBuilder('pp')
            ->join('pp.employee', 'e')
            ->join('e.employeeSituations', 'es')
            ->join('es.service', 's')
            ->join('pp.periodePaie', 'p')
            ->where('pp.status = :status')
            ->andWhere('p.typePaie = :type')
            ->andWhere('s.validateurService = :resp')
            ->andWhere('es.endDate IS NULL OR es.endDate >= CURRENT_DATE()')
            ->setParameter('status', $status)
            ->setParameter('resp', $responsable)
            ->setParameter('type', $type)
            ->orderBy('pp.periodePaie', 'DESC')
            ->getQuery()
            ->getResult();
    }
    public function countSubmittedByType(User $responsable, string $type): int
    {
        return (int) $this->createQueryBuilder('pp')
            ->select('COUNT(pp.id)')
            ->join('pp.employee', 'e')
            ->join('e.employeeSituations', 'es')
            ->join('es.service', 's')
            ->join('pp.periodePaie', 'p')
            ->where('pp.status = :status')
            ->andWhere('p.typePaie = :type')
            ->andWhere('s.validateurService = :resp')
            ->andWhere('es.endDate IS NULL OR es.endDate >= CURRENT_DATE()')
            ->setParameter('status', 'submitted')
            ->setParameter('type', $type)
            ->setParameter('resp', $responsable)
            ->getQuery()
            ->getSingleScalarResult();
    }
}
