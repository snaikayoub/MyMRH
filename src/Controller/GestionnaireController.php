<?php

namespace App\Controller;

use App\Entity\CategoryTM;
use App\Entity\PeriodePaie;
use App\Entity\PrimePerformance;
use App\Entity\EmployeeSituation;
use App\Service\PeriodePaieService;
use App\Service\PrimePerformanceService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use App\Service\GestionnaireService;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/gestionnaire')]
#[IsGranted('ROLE_GESTIONNAIRE_SERVICE')]
class GestionnaireController extends AbstractController
{
    private PeriodePaieService $periodePaieService;
    private GestionnaireService $GestionnaireService;
    private PrimePerformanceService $primePerformanceService;

    public function __construct(PeriodePaieService $periodePaieService, GestionnaireService $GestionnaireService, PrimePerformanceService $primePerformanceService)
    {
        $this->periodePaieService = $periodePaieService;
        $this->GestionnaireService = $GestionnaireService;
        $this->primePerformanceService = $primePerformanceService;
    }

    #[Route('/dashboard', name: 'gestionnaire_dashboard')]
    public function dashboard(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();
        $services = $this->GestionnaireService->getManagedServicesByUser($user);
        $nombreCollaborateurs = $this->GestionnaireService->countCollaborateursByServices($services);

        // Compter les primes saisies ce mois
        $primesSaisiesMois = $this->primePerformanceService->countPrimePerformanceSaisies($user, $em);

        // Compter les primes en attente (draft)
        $primesEnAttente = $this->primePerformanceService->countPrimePerformanceEnAttente($user, $em);

        // Compter les primes validées
        $primesValidees = $this->primePerformanceService->countPrimePerformanceValidees($user, $em);

        return $this->render('gestionnaire/g_dashboard.html.twig', [
            'collaborateurs_count' => $nombreCollaborateurs,
            'collaborateurs_performance' => $nombreCollaborateurs, // Peut être affiné selon vos besoins
            'saisies_mois' => $primesSaisiesMois,
            'primes_en_attente' => $primesEnAttente,
            'primes_validees' => $primesValidees,
            'conges_en_attente' => 0, // À implémenter selon votre entité Congé
            'conges_approuves' => 0,  // À implémenter selon votre entité Congé
        ]);
    }

    // src/Controller/GestionnaireController.php

    #[Route('/saisie/performance/{type}', name: 'gestionnaire_saisie_performance', methods: ['GET', 'POST'])]
    public function saisie(string $type, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        // 1) Récupérer tous les services gérés par ce gestionnaire
        $services = $this->GestionnaireService->getManagedServicesByUser($user);

        // 2) Charger automatiquement la seule période "ouverte" de ce type
        $periodeouverte = $this->periodePaieService->getPeriodeOuverte($type);
        
        if (!$periodeouverte) {
            $this->addFlash('error', 'Aucune période ouverte pour ce type de paie.');
            return $this->redirectToRoute('gestionnaire_dashboard');
        }
        if (!$this->periodePaieService->isScoreConfigured($periodeouverte)) {
            $this->addFlash('error', 'Les scores pour cette période ne sont pas configurés.');
            return $this->redirectToRoute('gestionnaire_dashboard');
        }
        $scoreEquipe    = $periodeouverte->getScoreEquipe();
        $scoreCollectif = $periodeouverte->getScoreCollectif();

        // 3) Rassembler toutes les EmployeeSituations actives des services
        $allSituations = new ArrayCollection();
        foreach ($services as $srv) {
            foreach ($srv->getEmployeeSituations() as $es) {
                $allSituations->add($es);
            }
        }
        $today = new \DateTimeImmutable();
        $situations = $allSituations
            ->filter(
                fn(EmployeeSituation $es) =>
                $es->getTypePaie() === $type
                    && $es->getStartDate() <= $today
                    && (null === $es->getEndDate() || $es->getEndDate() >= $today)
            )
            ->toArray();

        // 4) Récupérer toutes les PrimePerformance existantes pour cette période
        /** @var PrimePerformance[] $allPP */
        $allPP = $em->getRepository(PrimePerformance::class)
            ->findBy(['periodePaie' => $periodeouverte]);

        // 5) Construire submittedMap : status ≠ draft
        $submittedMap = [];
        foreach ($allPP as $pp) {
            if ($pp->getStatus() !== PrimePerformance::STATUS_DRAFT) {
                $submittedMap[$pp->getEmployee()->getId()] = $pp;
            }
        }

        // 6) Filtrer submittedMap pour ne garder que les employés issus de nos situations
        $validEmployeeIds = array_map(
            fn(EmployeeSituation $es) => $es->getEmployee()->getId(),
            $situations
        );
        $submittedMap = array_filter(
            $submittedMap,
            fn(PrimePerformance $pp) => in_array($pp->getEmployee()->getId(), $validEmployeeIds, true)
        );

        // 7) pending = celles sans PP ou PP encore en draft
        $pending = array_filter(
            $situations,
            fn(EmployeeSituation $es) =>
            !isset($submittedMap[$es->getEmployee()->getId()])
        );

        // 8) Rendre la vue
        return $this->render('gestionnaire/g_prime_performance.html.twig', [
            'type'           => $type,
            'periode'        => $periodeouverte,
            'scoreEquipe'    => $scoreEquipe,
            'scoreCollectif' => $scoreCollectif,
            'pending'        => $pending,
            'submittedMap'   => $submittedMap,
        ]);
    }


    #[Route('/saisie/performance/{type}/submit/{esId}', name: 'gestionnaire_submit_line', methods: ['POST'])]
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

    // src/Controller/GestionnaireController.php

    #[Route('/saisie/performance/{type}/revert/{ppId}', name: 'gestionnaire_revert_line', methods: ['POST'])]
    public function revertLine(
        string $type,
        int $ppId,
        Request $request,
        EntityManagerInterface $em,
        WorkflowInterface $primePerformanceStateMachine
    ): Response {
        // Récupération de l'ID de période depuis le champ caché
        $periodeId = $request->request->getInt('periode');

        /** @var PrimePerformance|null $pp */
        $pp = $em->getRepository(PrimePerformance::class)->find($ppId);

        if (!$pp) {
            $this->addFlash('error', 'Prime introuvable.');
        } else {
            // Vérifier qu'on peut bien effectuer la transition de retour
            if ($primePerformanceStateMachine->can($pp, 'retour_gestionnaire')) {
                $primePerformanceStateMachine->apply($pp, 'retour_gestionnaire');
                $em->flush();
                $this->addFlash('success', 'Ligne remise en cours de modification.');
            } else {
                $this->addFlash('warning', 'Impossible de remettre cette ligne en modification.');
            }
        }

        // Redirection vers le même écran de saisie
        return $this->redirectToRoute('gestionnaire_saisie_performance', [
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
        $services = $this->GestionnaireService->getManagedServicesByUser($user);

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
