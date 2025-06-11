<?php

namespace App\Controller;

use App\Entity\Service;
use App\Entity\PeriodePaie;
use App\Entity\PrimePerformance;
use App\Entity\EmployeeSituation;
use App\Entity\CategoryTM;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Workflow\WorkflowInterface;

#[Route('/gestionnaire')]
#[IsGranted('ROLE_GESTIONNAIRE_SERVICE')]
class GestionnaireController extends AbstractController
{
    #[Route('/dashboard', name: 'gestionnaire_dashboard')]
    public function dashboard(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $services = $em->getRepository(Service::class)
            ->createQueryBuilder('s')
            ->join('s.gestionnaire', 'g')
            ->where('g = :user')->setParameter('user', $user)
            ->getQuery()->getResult();
        // Nombre total de collaborateurs (employeeSituations)
        $collaborateursCount = 0;
        foreach ($services as $srv) {
            $collaborateursCount += $srv->getEmployeeSituations()
                ->filter(fn(EmployeeSituation $es) => $es->getStartDate() <= new \DateTimeImmutable() &&
                    (null === $es->getEndDate() || $es->getEndDate() >= new \DateTimeImmutable()))
                ->count();
        }

        // Récupérer les statistiques pour le dashboard
        $currentMonth = (new \DateTimeImmutable())->format('m');
        $currentYear = (new \DateTimeImmutable())->format('Y');

        // Compter les primes saisies ce mois
        $saisiesMois = $em->getRepository(PrimePerformance::class)
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

        // Compter les primes en attente (draft)
        $primesEnAttente = $em->getRepository(PrimePerformance::class)
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

        // Compter les primes validées
        $primesValidees = $em->getRepository(PrimePerformance::class)
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

        return $this->render('gestionnaire/g_dashboard.html.twig', [
            'collaborateurs_count' => $collaborateursCount,
            'collaborateurs_performance' => $collaborateursCount, // Peut être affiné selon vos besoins
            'saisies_mois' => $saisiesMois,
            'primes_en_attente' => $primesEnAttente,
            'primes_validees' => $primesValidees,
            'conges_en_attente' => 0, // À implémenter selon votre entité Congé
            'conges_approuves' => 0,  // À implémenter selon votre entité Congé
        ]);
    }

    #[Route('/saisie/{type}', name: 'gestionnaire_saisie', methods: ['GET', 'POST'])]
    public function saisie(
        string $type,
        Request $request,
        EntityManagerInterface $em,
        WorkflowInterface $primePerformanceStateMachine
    ): Response {
        $user = $this->getUser();

        // 1) Récupérer tous les services gérés par ce gestionnaire
        $services = $em->getRepository(Service::class)
            ->createQueryBuilder('s')
            ->join('s.gestionnaire', 'g')->where('g = :u')->setParameter('u', $user)
            ->orderBy('s.nom', 'ASC')->getQuery()->getResult();

        //4) Charger automatiquement la seule période ouverte
        $periode = $em->getRepository(PeriodePaie::class)->findOneBy([
            'typePaie' => $type,
            'statut' => 'Ouverte',
        ]);

        if (!$periode) {
            $this->addFlash('error', 'Aucune période ouverte pour ce type de paie.');
            return $this->redirectToRoute('gestionnaire_dashboard');
        }
        if (!$periode->getScoreEquipe() || !$periode->getScoreCollectif()) {
            $this->addFlash('error', 'Les scores pour cette période ne sont pas configurés.');
            return $this->redirectToRoute('gestionnaire_dashboard');
        }
        $scoreEquipe    = $periode->getScoreEquipe();
        $scoreCollectif = $periode->getScoreCollectif();

        // 6) Rassembler toutes les EmployeeSituations actives
        $allSituations = new ArrayCollection();
        foreach ($services as $srv) {
            foreach ($srv->getEmployeeSituations() as $es) {
                $allSituations->add($es);
            }
        }
        $today = new \DateTimeImmutable();
        $situations = $allSituations->filter(
            fn(EmployeeSituation $es) =>
            $es->getTypePaie() === $type
                && $es->getStartDate() <= $today
                && (null === $es->getEndDate() || $es->getEndDate() >= $today)
        )->toArray();

        // 7) Récupérer toutes les PrimePerformance existantes pour cette période
        $allPP = $em->getRepository(PrimePerformance::class)
            ->findBy(['periodePaie' => $periode]);

        $submittedMap = [];
        foreach ($allPP as $pp) {
            if ($pp->getStatus() !== PrimePerformance::STATUS_DRAFT) {
                $submittedMap[$pp->getEmployee()->getId()] = $pp;
            }
        }

        // 8) pending : les EmployeeSituations sans PP ou dont la PP est encore en draft
        $pending = array_filter(
            $situations,
            fn(EmployeeSituation $es) => !isset($submittedMap[$es->getEmployee()->getId()])
        );

        return $this->render('gestionnaire/g_prime_performance.html.twig', [
            'type'            => $type,
            'periode'         => $periode,
            'scoreEquipe'     => $scoreEquipe,
            'scoreCollectif'  => $scoreCollectif,
            'pending'         => $pending,
            'submittedMap'    => $submittedMap,
        ]);
    }

    #[Route('/saisie/{type}/submit/{esId}', name: 'gestionnaire_submit_line', methods: ['POST'])]
    public function submitLine(
        string $type,
        int $esId,
        Request $request,
        EntityManagerInterface $em,
        WorkflowInterface $primePerformanceStateMachine
    ): Response {
        $periodeId = $request->request->getInt('periode');

        $es = $em->getRepository(EmployeeSituation::class)->find($esId);
        $periode = $em->getRepository(PeriodePaie::class)->find($periodeId);

        if (!$es || !$periode) {
            $this->addFlash('error', 'Donnée introuvable.');
        } else {
            // Re-vérifier les scores dans la période
            if (!$periode->getScoreEquipe() || !$periode->getScoreCollectif()) {
                $this->addFlash('error', 'Les scores pour cette période ne sont pas configurés.');
                return $this->redirectToRoute('gestionnaire_saisie', ['type' => $type]);
            }

            $all = $request->request->all();
            $vals = $all['vals'] ?? [];

            if (empty($vals['joursPerf']) || empty($vals['noteHierarchique'])) {
                $this->addFlash('error', 'Veuillez renseigner tous les champs avant de soumettre.');
            } else {
                try {
                    $repo = $em->getRepository(PrimePerformance::class);
                    $pp = $repo->findOneBy([
                        'periodePaie' => $periode,
                        'employee'    => $es->getEmployee(),
                    ]);
                    if (!$pp) {
                        $pp = new PrimePerformance();
                        $pp->setEmployee($es->getEmployee())
                            ->setPeriodePaie($periode)
                            ->setStatus(PrimePerformance::STATUS_DRAFT);
                    }

                    // Récupérer le taux monétaire depuis CategoryTM
                    $grp = $es->getEmployee()->getGrpPerf();
                    $cat = $es->getCategory();
                    $model = $em->getRepository(CategoryTM::class)
                        ->findOneBy(['grpPerf' => $grp, 'category' => $cat]);
                    $tm = $model?->getTM() ?? 0.0;

                    $pp->setTauxMonetaire($tm)
                        ->setJoursPerf((float)$vals['joursPerf'])
                        ->setNoteHierarchique((float)$vals['noteHierarchique'])
                        ->calculerMontant();

                    $em->persist($pp);

                    // Changer le statut via le workflow (de draft à submitted)
                    if ($primePerformanceStateMachine->can($pp, 'submit')) {
                        $primePerformanceStateMachine->apply($pp, 'submit');
                    }
                    $em->flush();

                    $this->addFlash('success', sprintf(
                        'Prime de %s calculée : %s',
                        $es->getEmployee()->getMatricule(),
                        $pp->getMontantFormate()
                    ));
                } catch (\InvalidArgumentException $e) {
                    $this->addFlash('error', 'Erreur de calcul : ' . $e->getMessage());
                } catch (\Exception $e) {
                    $this->addFlash('error', 'Erreur lors de la sauvegarde : ' . $e->getMessage());
                }
            }
        }

        return $this->redirectToRoute('gestionnaire_saisie', [
            'type'    => $type,
            'periode' => $periodeId,
        ]);
    }

    #[Route('/saisie/{type}/revert/{ppId}', name: 'gestionnaire_revert_line', methods: ['POST'])]
    public function revertLine(
        string $type,
        int $ppId,
        Request $request,
        EntityManagerInterface $em,
        WorkflowInterface $primePerformanceStateMachine
    ): Response {
        $periodeId = $request->request->getInt('periode');
        $pp = $em->getRepository(PrimePerformance::class)->find($ppId);
        if ($primePerformanceStateMachine->can($pp, 'revert')) {
            $primePerformanceStateMachine->apply($pp, 'revert');
            $em->flush();
            $this->addFlash('success', 'Ligne remise en cours de modification.');
        }
        return $this->redirectToRoute('gestionnaire_saisie', [
            'type'    => $type,
            'periode' => $periodeId,
        ]);
    }

    #[Route('/api/periode/{id}/scores', name: 'api_periode_scores', methods: ['GET'])]
    public function getScoresPeriode(int $id, EntityManagerInterface $em): JsonResponse
    {
        $periode = $em->getRepository(PeriodePaie::class)->find($id);
        if (!$periode) {
            return new JsonResponse(['error' => 'Période introuvable'], 404);
        }
        return new JsonResponse([
            'scoreEquipe'    => $periode->getScoreEquipe(),
            'scoreCollectif' => $periode->getScoreCollectif(),
            'configured'     => ((bool)$periode->getScoreEquipe() && (bool)$periode->getScoreCollectif()),
            'periode' => [
                'id'       => $periode->getId(),
                'mois'     => $periode->getMois(),
                'annee'    => $periode->getAnnee(),
                'quinzaine' => $periode->getQuinzaine(),
                'label'    => sprintf(
                    '%02d/%d%s',
                    $periode->getMois(),
                    $periode->getAnnee(),
                    $periode->getQuinzaine() ? ' (Q' . $periode->getQuinzaine() . ')' : ''
                )
            ]
        ]);
    }
    #[Route('/historique/{type}', name: 'gestionnaire_historique')]
    public function historique(string $type, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        // Récupérer les services gérés par ce gestionnaire
        $services = $em->getRepository(Service::class)
            ->createQueryBuilder('s')
            ->join('s.gestionnaire', 'g')
            ->where('g = :user')
            ->setParameter('user', $user)
            ->getQuery()->getResult();

        // Récupérer l'historique des primes
        // Logique à implémenter selon vos besoins

        return $this->render('gestionnaire/historique.html.twig', [
            'type' => $type,
            'services' => $services,
        ]);
    }

    #[Route('/conges/validation', name: 'gestionnaire_conges_validation')]
    public function congesValidation(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        // Récupérer les demandes de congés en attente
        // Logique à implémenter selon vos entités

        return $this->render('gestionnaire/conges_validation.html.twig', [
            // Variables nécessaires
        ]);
    }

    #[Route('/conges/planning', name: 'gestionnaire_conges_planning')]
    public function congesPlanning(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        // Récupérer le planning des congés
        // Logique à implémenter selon vos besoins

        return $this->render('gestionnaire/conges_planning.html.twig', [
            // Variables nécessaires
        ]);
    }

    #[Route('/rapports', name: 'gestionnaire_rapports')]
    public function rapports(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        // Générer les rapports et statistiques
        // Logique à implémenter selon vos besoins

        return $this->render('gestionnaire/rapports.html.twig', [
            // Variables nécessaires
        ]);
    }

    #[Route('/aide', name: 'gestionnaire_aide')]
    public function aide(): Response
    {
        return $this->render('gestionnaire/aide.html.twig');
    }
}
