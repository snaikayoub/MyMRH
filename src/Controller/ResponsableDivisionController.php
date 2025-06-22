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

        // Période courante
        $periodeCourante = $periodeRepo->findOneBy(
            ['typePaie' => $type, 'statut' => 'Ouverte'],
            ['annee' => 'DESC', 'mois' => 'DESC', 'quinzaine' => 'DESC']
        );

        // Récupère toutes les primes prêtes / validées pour cette division et ce type
        $allReady     = $repo->findByDivisionAndStatusAndType($user, 'service_validated', $type);
        $allValidated = $repo->findByDivisionAndStatusAndType($user, 'division_validated', $type);

        // Filtre pour ne garder que celles du service sélectionné
        $today = new \DateTimeImmutable();
        $ready = array_filter($allReady, function (PrimePerformance $pp) use ($serviceId, $today) {
            // on cherche une situation active de cet employé dans ce service
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
        $validated = array_filter($allValidated, function (PrimePerformance $pp) use ($serviceId, $today) {
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

        return $this->render('responsable_d/prime_performance.html.twig', [
            'type'            => $type,
            'service'         => $service,
            'periodeCourante' => $periodeCourante,
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
        //dd($ids);
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
        return $this->redirectToRoute('responsable_division_prime_performance', ['type' => $type]);
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
        $ids       = $request->request->get('selected', []);
        $type      = $request->request->get('type', 'mensuelle');
        $serviceId = $request->request->getInt('serviceId');

        if (empty($ids)) {
            $this->addFlash('warning', 'Aucune prime sélectionnée.');
            return $this->redirectToRoute('responsable_division_prime_performance', ['type' => $type, 'serviceId' => $serviceId]);
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

        $this->addFlash('success', "$count prime(s) retournée(s) au service.");
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
