<?php

namespace App\Controller;

use App\Entity\Service;
use App\Entity\Division;
use App\Entity\PrimePerformance;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Workflow\WorkflowInterface;

#[Route('/division')]
class DivisionController extends AbstractController
{
    #[Route('/dashboard', name: 'division_dashboard')]
    public function dashboard(EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        $divisions = $em->getRepository(Division::class)->findBy(['validateurDivision' => $user]);

        return $this->render('division/dashboard.html.twig', [
            'divisions' => $divisions,
        ]);
    }

    #[Route('/services/{type}', name: 'division_services')]
    public function selectServices(string $type, EntityManagerInterface $em): Response
    {
        $user = $this->getUser();

        $services = $em->createQuery(
            "SELECT s FROM App\Entity\Service s 
             JOIN s.division d 
             WHERE d.validateurDivision = :user"
        )->setParameter('user', $user)->getResult();

        return $this->render('division/services.html.twig', [
            'type' => $type,
            'services' => $services,
        ]);
    }

    #[Route('/validation/{type}/{id}', name: 'division_validation')]
    public function validatePrimes(string $type, int $id, EntityManagerInterface $em): Response
    {
        $service = $em->getRepository(Service::class)->find($id);

        $submitted = $em->getRepository(PrimePerformance::class)->findBy([
            'status' => 'submitted',
            'employee' => $service->getEmployees(),
        ]);

        $validated = $em->getRepository(PrimePerformance::class)->findBy([
            'status' => 'service_validated',
            'employee' => $service->getEmployees(),
        ]);

        return $this->render('division/validation.html.twig', [
            'service' => $service,
            'type' => $type,
            'submitted' => $submitted,
            'validated' => $validated,
        ]);
    }

    #[Route('/valider/{id}', name: 'division_valider_prime', methods: ['POST'])]
    public function validerPrime(
        PrimePerformance $pp,
        WorkflowInterface $primePerformanceStateMachine,
        EntityManagerInterface $em
    ): Response {
        if ($primePerformanceStateMachine->can($pp, 'division_validate')) {
            $primePerformanceStateMachine->apply($pp, 'division_validate');
            $em->flush();
            $this->addFlash('success', 'Prime validée.');
        } else {
            $this->addFlash('error', 'Action non autorisée.');
        }

        return $this->redirectToRoute('division_dashboard');
    }
}
