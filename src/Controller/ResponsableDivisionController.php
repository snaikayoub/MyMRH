<?php

namespace App\Controller;

use App\Entity\Service;
use App\Entity\PrimePerformance;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\PeriodePaieRepository;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\PrimePerformanceRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/responsable_division')]
class ResponsableDivisionController extends AbstractController
{
    #[Route('/dashboard', name: 'responsable_division_dashboard')]
    public function dashboard(PrimePerformanceRepository $repo): Response
    {
        $user = $this->getUser();

        // Comptes pour le widget
        $countReadyMensuelle = $repo->countByTypeAndStatus($user, 'mensuelle', 'service_validated');
        $countReadyQuinzaine = $repo->countByTypeAndStatus($user, 'quinzaine', 'service_validated');
        $countValidMensuelle = $repo->countByTypeAndStatus($user, 'mensuelle', 'division_validated');
        $countValidQuinzaine = $repo->countByTypeAndStatus($user, 'quinzaine', 'division_validated');

        return $this->render('responsable_d/r_d_dashboard.html.twig', [
            'countReadyMensuelle' => $countReadyMensuelle,
            'countReadyQuinzaine' => $countReadyQuinzaine,
            'countValidMensuelle' => $countValidMensuelle,
            'countValidQuinzaine' => $countValidQuinzaine,
        ]);
    }

    #[Route('/services/{type}', name: 'responsable_division_services')]
    public function listServices(string $type, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        // Récupérer les services dont la division est validée par $user
        $services = $em->getRepository(Service::class)
            ->createQueryBuilder('s')
            ->join('s.division', 'd')
            ->where('d.validateurDivision = :user')
            ->setParameter('user', $user)
            ->orderBy('s.nom', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->render('responsable_d/r_d_services.html.twig', [
            'type'     => $type,
            'services' => $services,
        ]);
    }

    #[Route('/services/{type}/{serviceId}/primes', name: 'responsable_division_prime_performance')]
    public function primePerformance(
        string $type,
        int $serviceId,
        PrimePerformanceRepository $repo,
        PeriodePaieRepository $periodeRepo,
        EntityManagerInterface $em
    ): Response {
        $user = $this->getUser();
        $service = $em->getRepository(Service::class)->find($serviceId);

        if (!$service || $service->getDivision()->getValidateurDivision() !== $user) {
            throw $this->createAccessDeniedException('Service non autorisé');
        }

        // 1) Période courante
        $periodeCourante = $periodeRepo->findOneBy(
            ['typePaie' => $type, 'statut' => 'Ouverte'],
            ['annee' => 'DESC', 'mois' => 'DESC', 'quinzaine' => 'DESC']
        );

        $today = new \DateTimeImmutable();

        // 2) Récupérer *toutes* les primes par statut
        $allSubmitted   = $repo->findByDivisionAndStatusAndType($user, PrimePerformance::STATUS_SUBMITTED,         $type);
        $allReady       = $repo->findByDivisionAndStatusAndType($user, PrimePerformance::STATUS_SERVICE_VALIDATED, $type);
        $allValidated   = $repo->findByDivisionAndStatusAndType($user, PrimePerformance::STATUS_DIVISION_VALIDATED, $type);

        // 3) Filtrer par service actif
        $filterByService = function (array $pps) use ($serviceId, $today): array {
            return array_filter($pps, function (PrimePerformance $pp) use ($serviceId, $today) {
                $situations = $pp->getEmployee()
                    ->getEmployeeSituations()
                    ->filter(
                        fn($es) =>
                        $es->getService()->getId() === $serviceId
                            && $es->getStartDate() <= $today
                            && (null === $es->getEndDate() || $es->getEndDate() >= $today)
                    );
                return !$situations->isEmpty();
            });
        };

        $submitted = $filterByService($allSubmitted);
        $ready     = $filterByService($allReady);
        $validated = $filterByService($allValidated);

        return $this->render('responsable_d/r_d_prime_performance.html.twig', [
            'type'            => $type,
            'service'         => $service,
            'periodeCourante' => $periodeCourante,
            'submitted'       => $submitted,
            'ready'           => $ready,
            'validated'       => $validated,
        ]);
    }


    #[Route('/valider_batch', name: 'responsable_division_valider_batch', methods: ['POST'])]
    public function validerBatch(
        Request $request,
        PrimePerformanceRepository $repo,
        EntityManagerInterface $em,
        WorkflowInterface $primePerformanceStateMachine
    ): Response {
        // Récupère l’array de sélection correctement
        $ids = $request->request->all('selected') ?: [];

        if (empty($ids)) {
            $this->addFlash('warning', 'Aucune prime sélectionnée.');
            return $this->redirectToRoute('responsable_division_prime_performance', [
                'type' => $request->request->get('type', 'mensuelle'),
                'serviceId' => $request->request->getInt('serviceId')
            ]);
        }
        $primes = $repo->findBy(['id' => $ids]);
        $count  = 0;
        foreach ($primes as $pp) {
            if ($primePerformanceStateMachine->can($pp, 'division_validate')) {
                $primePerformanceStateMachine->apply($pp, 'division_validate');
                $count++;
            }
        }
        $em->flush();

        $this->addFlash('success', "$count prime(s) validée(s) par la division.");
        $type = $request->request->get('type', 'mensuelle');
        $serviceId = $request->request->getInt('serviceId', 0);
        return $this->redirectToRoute('responsable_division_prime_performance', ['type' => $type, 'serviceId' => $serviceId]);
    }


    #[Route('/valider/{id}', name: 'responsable_division_valider_ligne', methods: ['POST'])]
    public function validerLigne(
        PrimePerformance $pp,
        Request $request,
        WorkflowInterface $primePerformanceStateMachine,
        EntityManagerInterface $em
    ): Response {
        $type      = $request->request->get('type', 'mensuelle');
        $serviceId = $request->request->getInt('serviceId');
        // Vérifie si la prime peut être validée par la division
        if ($primePerformanceStateMachine->can($pp, 'division_validate')) {
            $primePerformanceStateMachine->apply($pp, 'division_validate');
            $em->flush();
            $this->addFlash('success', 'Prime validée par la division.');
        } else {
            $this->addFlash('error', 'Impossible de valider cette prime.');
        }

        return $this->redirectToRoute('responsable_division_prime_performance', ['type' => $type, 'serviceId' => $serviceId]);
    }

    #[Route('/retour_batch', name: 'responsable_division_retour_batch', methods: ['POST'])]
    public function retourBatch(
        Request $request,
        PrimePerformanceRepository $repo,
        EntityManagerInterface $em,
        WorkflowInterface $primePerformanceStateMachine
    ): Response {
        // Récupère l’array de sélection correctement
        $ids = $request->request->all('selected') ?: [];

        if (empty($ids)) {
            $this->addFlash('warning', 'Aucune prime sélectionnée.');
            return $this->redirectToRoute('responsable_division_prime_performance', [
                'type' => $request->request->get('type', 'mensuelle'),
                'serviceId' => $request->request->getInt('serviceId')
            ]);
        }
        $primes = $repo->findBy(['id' => $ids]);
        $count  = 0;
        foreach ($primes as $pp) {
            if ($primePerformanceStateMachine->can($pp, 'retour_service')) {
                $primePerformanceStateMachine->apply($pp, 'retour_service');
                $count++;
            }
        }
        $em->flush();

        $this->addFlash('success', "$count prime(s) retournée(s) par la division.");
        $type = $request->request->get('type', 'mensuelle');
        $serviceId = $request->request->getInt('serviceId', 0);
        return $this->redirectToRoute('responsable_division_prime_performance', ['type' => $type, 'serviceId' => $serviceId]);
    }

    #[Route('/retour/{id}', name: 'responsable_division_retour_ligne', methods: ['POST'])]
    public function retourLigne(
        PrimePerformance $pp,
        Request $request,
        WorkflowInterface $primePerformanceStateMachine,
        EntityManagerInterface $em
    ): Response {
        $type      = $request->request->get('type', 'mensuelle');
        $serviceId = $request->request->getInt('serviceId');

        if ($primePerformanceStateMachine->can($pp, 'retour_service')) {
            $primePerformanceStateMachine->apply($pp, 'retour_service');
            $em->flush();
            $this->addFlash('success', 'Prime retournée au service.');
        } else {
            $this->addFlash('error', 'Impossible de retourner cette prime.');
        }

        return $this->redirectToRoute('responsable_division_prime_performance', ['type' => $type, 'serviceId' => $serviceId]);
    }
}
