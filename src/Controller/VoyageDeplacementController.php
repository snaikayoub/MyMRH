<?php

namespace App\Controller;

use App\Entity\User;
use App\Entity\PeriodePaie;
use App\Entity\VoyageDeplacement;
use App\Form\VoyageDeplacementType;
use App\Service\GestionnaireService;
use Doctrine\ORM\EntityManagerInterface;
use App\Repository\PeriodePaieRepository;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Repository\VoyageDeplacementRepository;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Workflow\WorkflowInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;


#[Route('/voyage')]
class VoyageDeplacementController extends AbstractController
{
    /**
     * 📋 Liste des voyages de l'utilisateur connecté
     */
    #[Route('/', name: 'voyage_list')]
    public function list(
        VoyageDeplacementRepository $repository
    ): Response {
        // Récupération de l'utilisateur connecté
        $user = $this->getUser();

        // Récupération des voyages liés à l'employé
        $voyages = $repository->findBy(
            ['employee' => $user],
            ['createdAt' => 'DESC']
        );

        return $this->render('voyage/list.html.twig', [
            'voyages' => $voyages,
        ]);
    }

    /**
     * ➕ Création d'un voyage (statut initial : draft)
     */
    #[Route('/new', name: 'voyage_new')]
    public function new(
        Request $request,
        EntityManagerInterface $em,
        PeriodePaieRepository $periodeRepo,
        \App\Service\GestionnaireService $gestionnaireService
    ): Response {
        $user = $this->getUser();

        // Sécurité
        if (!$user instanceof \App\Entity\User) {
            throw $this->createAccessDeniedException();
        }

        // ✅ Récupérer le type de paie depuis l'URL
        $typePaie = $request->query->get('type');

        if (!in_array($typePaie, ['mensuelle', 'quinzaine'])) {
            throw new \InvalidArgumentException('Type de paie invalide. Utilisez "mensuelle" ou "quinzaine".');
        }


        // Récupération des collaborateurs gérés
        $employees = $gestionnaireService->getManagedEmployeesByUser($user, $typePaie);

        if (empty($employees)) {
            throw new \LogicException('Aucun collaborateur rattaché à ce gestionnaire.');
        }

        $voyage = new VoyageDeplacement();
        $voyage->setStatus(VoyageDeplacement::STATUS_DRAFT);
        $voyage->setCreatedAt(new \DateTimeImmutable());

        // ✅ Période de paie ouverte avec le type correspondant
        $periode = $periodeRepo->findOneBy([
            'statut' => PeriodePaie::STATUT_OUVERT,
            'typePaie' => $typePaie
        ]);
        if (!$periode) {
            throw new \LogicException('Aucune période de paie ouverte pour le type "' . $typePaie . '".');
        }

        // ✅ Assigner la période au voyage
        $voyage->setPeriodePaie($periode);

        // Formulaire avec choix des collaborateurs
        $form = $this->createForm(
            VoyageDeplacementType::class,
            $voyage,
            [
                'employees' => $employees, // 👈 clé importante
                'periode_paie_label' => $periode->__toString(), // ✅ Passer le label
            ]
        );

        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($voyage);
            $em->flush();

            $this->addFlash('success', 'Voyage enregistré en brouillon.');
            return $this->redirectToRoute('voyage_list');
        }

        return $this->render('voyage_deplacement/new.html.twig', [
            'form' => $form->createView(),
            'typePaie' => $typePaie, // ✅ Pour affichage dans le template
        ]);
    }

    /**
     * ✏️ Édition d'un voyage (autorisé uniquement en draft ou rejected)
     */
    #[Route('/edit/{id}', name: 'voyage_edit')]
    public function edit(
        VoyageDeplacement $voyage,
        Request $request,
        EntityManagerInterface $em
    ): Response {
        // Sécurité métier
        if (!in_array($voyage->getStatus(), [
            VoyageDeplacement::STATUS_DRAFT,
            VoyageDeplacement::STATUS_REJECTED
        ])) {
            throw $this->createAccessDeniedException('Modification non autorisée.');
        }

        $form = $this->createForm(VoyageDeplacementType::class, $voyage);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $voyage->setUpdatedAt(new \DateTimeImmutable());
            $em->flush();

            $this->addFlash('success', 'Voyage mis à jour.');

            return $this->redirectToRoute('voyage_list');
        }

        return $this->render('voyage/edit.html.twig', [
            'form' => $form->createView(),
            'voyage' => $voyage,
        ]);
    }

    /**
     * 📤 Soumission du voyage (workflow : submit)
     */
    #[Route('/submit/{id}', name: 'voyage_submit', methods: ['POST'])]
    public function submit(
        VoyageDeplacement $voyage,
        WorkflowInterface $voyageWorkflow,
        EntityManagerInterface $em
    ): Response {
        if ($voyageWorkflow->can($voyage, 'submit')) {
            $voyageWorkflow->apply($voyage, 'submit');
            $em->flush();

            $this->addFlash('success', 'Voyage soumis pour validation.');
        } else {
            $this->addFlash('error', 'Action non autorisée.');
        }

        return $this->redirectToRoute('voyage_list');
    }

    /**
     * ❌ Rejet du voyage (workflow : reject)
     */
    #[Route('/reject/{id}', name: 'voyage_reject', methods: ['POST'])]
    public function reject(
        VoyageDeplacement $voyage,
        WorkflowInterface $voyageWorkflow,
        EntityManagerInterface $em
    ): Response {
        if ($voyageWorkflow->can($voyage, 'reject')) {
            $voyageWorkflow->apply($voyage, 'reject');
            $em->flush();

            $this->addFlash('warning', 'Voyage rejeté.');
        }

        return $this->redirectToRoute('voyage_list');
    }

    /**
     * ✅ Validation du voyage (workflow : validate)
     */
    #[Route('/validate/{id}', name: 'voyage_validate', methods: ['POST'])]
    public function validate(
        VoyageDeplacement $voyage,
        WorkflowInterface $voyageWorkflow,
        EntityManagerInterface $em
    ): Response {
        if ($voyageWorkflow->can($voyage, 'validate')) {
            $voyageWorkflow->apply($voyage, 'validate');
            $em->flush();

            $this->addFlash('success', 'Voyage validé.');
        }

        return $this->redirectToRoute('voyage_list');
    }
}
