<?php

namespace App\Controller;

use App\Entity\PrimePerformance;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\PeriodePaieRepository;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\PrimePerformanceRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/responsable')]
class ResponsableServiceController extends AbstractController
{
    #[Route('/dashboard', name: 'responsable_dashboard')]
    public function dashboard(PrimePerformanceRepository $repo): Response
    {
        $user = $this->getUser();

        $countMensuelle = $repo->countSubmittedByType($user, 'mensuelle');
        $countQuinzaine = $repo->countSubmittedByType($user, 'quinzaine');

        return $this->render('responsable_s/s_dashboard.html.twig', [
            'countMensuelle' => $countMensuelle,
            'countQuinzaine' => $countQuinzaine,
        ]);
    }

    #[Route('/prime-performance/{type}', name: 'responsable_prime_performance')]
    public function primePerformance(
        string $type,
        PrimePerformanceRepository $repo,
        PeriodePaieRepository $periodeRepo,
        UserInterface $user
    ): Response {
        $periodeCourante = $periodeRepo->findOneBy([
            'typePaie' => $type,
            'statut' => 'Ouverte',
        ], ['annee' => 'DESC', 'mois' => 'DESC']);

        $user = $this->getUser();

        $submitted = $repo->findByServiceAndStatusAndType($user, 'submitted', $type);
        $draft = $repo->findByServiceAndStatusAndType($user, 'draft', $type);
        $validated = $repo->findByServiceAndStatusAndType($user, 'service_validated', $type);

        return $this->render('responsable_s/s_prime_performance.html.twig', [
            'type' => $type,
            'primes' => $submitted,
            'drafts' => $draft,
            'validated' => $validated,
            'periodeCourante' => $periodeCourante,
            'user' => $user,
        ]);
    }

    #[Route('/valider', name: 'responsable_valider_batch', methods: ['POST'])]
    public function validerBatch(
        Request $request,
        PrimePerformanceRepository $repo,
        EntityManagerInterface $em,
        WorkflowInterface $primePerformanceStateMachine
    ): Response {
        // Correction : utilisation de all() au lieu de get()
        $ids = $request->request->all('selected') ?: [];
        
        if (empty($ids)) {
            $this->addFlash('warning', 'Aucun élément sélectionné.');
            return $this->redirectToRoute('responsable_dashboard');
        }

        $primes = $repo->findBy(['id' => $ids]);
        $count = 0;

        foreach ($primes as $pp) {
            if ($primePerformanceStateMachine->can($pp, 'service_validate')) {
                $primePerformanceStateMachine->apply($pp, 'service_validate');
                $count++;
            }
        }

        $em->flush();
        $this->addFlash('success', $count . ' ligne(s) validée(s)');

        // Redirection simplifiée
        $type = $request->request->get('type', 'mensuelle');
        return $this->redirectToRoute('responsable_prime_performance', ['type' => $type]);
    }

    #[Route('/valider/{id}', name: 'responsable_valider_ligne', methods: ['POST'])]
    public function validerLigne(
        PrimePerformance $pp,
        Request $request,
        WorkflowInterface $primePerformanceStateMachine,
        EntityManagerInterface $em
    ): Response {
        if ($primePerformanceStateMachine->can($pp, 'service_validate')) {
            $primePerformanceStateMachine->apply($pp, 'service_validate');
            $em->flush();
            $this->addFlash('success', 'Ligne validée.');
        } else {
            $this->addFlash('error', 'Action non autorisée.');
        }

        // Redirection simplifiée
        $type = $request->request->get('type', 'mensuelle');
        return $this->redirectToRoute('responsable_prime_performance', ['type' => $type]);
    }

    #[Route('/retour', name: 'responsable_retour_batch', methods: ['POST'])]
    public function retourBatch(
        Request $request,
        PrimePerformanceRepository $repo,
        EntityManagerInterface $em,
        WorkflowInterface $primePerformanceStateMachine
    ): Response {
        // Correction : utilisation de all() au lieu de get()
        $ids = $request->request->all('selected') ?: [];
        
        if (empty($ids)) {
            $this->addFlash('warning', 'Aucun élément sélectionné.');
            $type = $request->request->get('type', 'mensuelle');
            return $this->redirectToRoute('responsable_prime_performance', ['type' => $type]);
        }

        $primes = $repo->findBy(['id' => $ids]);
        $count = 0;

        foreach ($primes as $pp) {
            if ($primePerformanceStateMachine->can($pp, 'retour_gestionnaire')) {
                $primePerformanceStateMachine->apply($pp, 'retour_gestionnaire');
                $count++;
            }
        }

        $em->flush();
        $this->addFlash('success', $count . ' prime(s) retournée(s) au gestionnaire.');

        // Redirection simplifiée
        $type = $request->request->get('type', 'mensuelle');
        return $this->redirectToRoute('responsable_prime_performance', ['type' => $type]);
    }

    #[Route('/retour/{id}', name: 'responsable_retour_ligne', methods: ['POST'])]
    public function retourLigne(
        PrimePerformance $pp,
        Request $request,
        WorkflowInterface $primePerformanceStateMachine,
        EntityManagerInterface $em
    ): Response {
        if ($primePerformanceStateMachine->can($pp, 'retour_gestionnaire')) {
            $primePerformanceStateMachine->apply($pp, 'retour_gestionnaire');
            $em->flush();
            $this->addFlash('success', 'Prime retournée au gestionnaire.');
        } else {
            $this->addFlash('error', 'Impossible de retourner cette prime.');
        }

        // Redirection simplifiée
        $type = $request->request->get('type', 'mensuelle');
        return $this->redirectToRoute('responsable_prime_performance', ['type' => $type]);
    }
}