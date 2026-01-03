<?php

namespace App\Service;

use App\Entity\CategoryTM;
use App\Entity\EmployeeSituation;
use App\Entity\PeriodePaie;
use App\Entity\PrimePerformance;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Workflow\WorkflowInterface;

class PrimePerformanceService
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly WorkflowInterface $primePerformanceStateMachine
    ) {}

    /**
     * Création ou mise à jour d'une prime de performance
     */
    public function submitPrime(
        EmployeeSituation $situation,
        PeriodePaie $periode,
        float $joursPerf,
        float $noteHierarchique
    ): PrimePerformance {

        $repo = $this->em->getRepository(PrimePerformance::class);

        $prime = $repo->findOneBy([
            'employee'    => $situation->getEmployee(),
            'periodePaie' => $periode
        ]) ?? new PrimePerformance();

        // Détermination du taux monétaire
        $tm = $this->resolveTauxMonetaire($situation);

        $prime
            ->setEmployee($situation->getEmployee())
            ->setPeriodePaie($periode)
            ->setTauxMonetaire($tm)
            ->setJoursPerf($joursPerf)
            ->setNoteHierarchique($noteHierarchique)
            ->setStatus(PrimePerformance::STATUS_DRAFT)
            ->calculerMontant();

        $this->em->persist($prime);

        // Transition workflow
        if ($this->primePerformanceStateMachine->can($prime, 'submit')) {
            $this->primePerformanceStateMachine->apply($prime, 'submit');
        }

        $this->em->flush();

        return $prime;
    }

    /**
     * Résolution du taux monétaire depuis CategoryTM
     */
    private function resolveTauxMonetaire(EmployeeSituation $situation): float
    {
        $model = $this->em->getRepository(CategoryTM::class)
            ->findOneBy([
                'grpPerf'  => $situation->getEmployee()->getGrpPerf(),
                'category' => $situation->getCategory()
            ]);

        return $model?->getTM() ?? 0.0;
    }

    public function countPrimePerformanceSaisies($user)
    {
        // Récupérer les statistiques pour le dashboard
        $currentMonth = (new \DateTimeImmutable())->format('m');
        $currentYear = (new \DateTimeImmutable())->format('Y');
        // Compter les primes saisies ce mois
        $PrimesSaisies = $this->em->getRepository(PrimePerformance::class)
            ->createQueryBuilder('pp')
            ->select('COUNT(DISTINCT e.id)')
            ->join('pp.periodePaie', 'p')
            ->join('pp.employee', 'e')
            ->join('e.employeeSituations', 'es')
            ->join('es.service', 's')
            ->join('s.gestionnaire', 'g')
            ->where('g = :user')
            ->andWhere('p.mois = :month')
            ->andWhere('p.annee = :year')
            ->setParameter('user', $user)
            ->setParameter('month', $currentMonth)
            ->setParameter('year', $currentYear)
            ->getQuery()
            ->getSingleScalarResult();
        return $PrimesSaisies;
    }
    public function countPrimePerformanceEnAttente($user)
    {
        // Compter les primes saisies ce mois
        $primesEnAttente = $this->em->getRepository(PrimePerformance::class)
            ->createQueryBuilder('pp')
            ->select('COUNT(DISTINCT e.id)')
            ->join('pp.employee', 'e')
            ->join('e.employeeSituations', 'es')
            ->join('es.service', 's')
            ->join('s.gestionnaire', 'g')
            ->where('g = :user')
            ->andWhere('pp.status = :status')
            ->setParameter('user', $user)
            ->setParameter('status', PrimePerformance::STATUS_DRAFT)
            ->getQuery()
            ->getSingleScalarResult();
        return $primesEnAttente;
    }
    public function countPrimePerformanceValidees($user)
    {
        $primesValidees = $this->em->getRepository(PrimePerformance::class)
            ->createQueryBuilder('pp')
            ->select('COUNT(DISTINCT e.id)')
            ->join('pp.employee', 'e')
            ->join('e.employeeSituations', 'es')
            ->join('es.service', 's')
            ->join('s.gestionnaire', 'g')
            ->where('g = :user')
            ->andWhere('pp.status != :status')
            ->setParameter('user', $user)
            ->setParameter('status', PrimePerformance::STATUS_DRAFT)
            ->getQuery()
            ->getSingleScalarResult();
        return $primesValidees;
    }
}
