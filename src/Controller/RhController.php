<?php

// src/Controller/RhController.php

namespace App\Controller;

use Dompdf\Dompdf;
use Dompdf\Options;
use App\Repository\DivisionRepository;
use App\Repository\PeriodePaieRepository;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Symfony\Component\HttpFoundation\Request;
use App\Repository\PrimePerformanceRepository;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;

#[Route('/rh')]
class RhController extends AbstractController
{
    #[Route('/', name: 'rh_dashboard')]
    public function dashboard(
        DivisionRepository $divisionRepo,
        PrimePerformanceRepository $primeRepo
    ): Response {
        $this->denyAccessUnlessGranted('ROLE_RH');

        $divisions = $divisionRepo->findAll();

        // Statistiques RH
        $globalStats = [
            'draft' => $primeRepo->countByStatus('draft'),
            'submitted' => $primeRepo->countByStatus('submitted'),
            'service_validated' => $primeRepo->countByStatus('service_validated'),
            'division_validated' => $primeRepo->countByStatus('division_validated'),
        ];

        return $this->render('rh/dashboard.html.twig', [
            'divisions' => $divisions,
            'stats' => $globalStats,
        ]);
    }

    #[Route('/etat-validations', name: 'rh_etat_validations')]
    public function etatValidations(DivisionRepository $divisionRepo): Response
    {
        $divisions = $divisionRepo->findAll();
        return $this->render('rh/etat_validations.html.twig', [
            'divisions' => $divisions,
        ]);
    }

    #[Route('/etat-periode/{type}', name: 'rh_etat_periode')]
    public function etatParPeriode(string $type, PeriodePaieRepository $repo): Response
    {
        $periodes = $repo->findBy(['typePaie' => $type], ['annee' => 'DESC', 'mois' => 'DESC']);
        return $this->render('rh/etat_periode.html.twig', [
            'type' => $type,
            'periodes' => $periodes,
        ]);
    }
    #[Route('/tableau-synthese', name: 'rh_tableau_synthese')]
    public function tableauSynthese(
        Request $request,
        PrimePerformanceRepository $primeRepo,
        PeriodePaieRepository $periodeRepo,
        DivisionRepository $divisionRepo
    ): Response {
        $periodes = $periodeRepo->findBy([], ['annee' => 'DESC', 'mois' => 'DESC']);
        $divisions = $divisionRepo->findAll();

        $periodeId = $request->query->get('periode');
        $statut = $request->query->get('statut');
        $divisionId = $request->query->get('division');

        $criteria = [];
        if ($periodeId) {
            $criteria['periodePaie'] = $periodeId;
        }
        if ($statut) {
            $criteria['status'] = $statut;
        }

        // Filtrage par division (sur l’employeeSituation → service → division)
        $primes = $primeRepo->findWithDivision($criteria, $divisionId);

        // ⬇️ Ici le bloc d’export
        if ($request->query->get('export') === 'xlsx') {
            return $this->exportXlsx($primes);
        }
        if ($request->query->get('export') === 'pdf') {
            return $this->exportPdf($primes);
        }


        // ⬇️ Rendu normal du tableau filtré

        return $this->render('rh/tableau_synthese.html.twig', [
            'primes'     => $primes,
            'periodes'   => $periodes,
            'divisions'  => $divisions,
            'periodeId'  => $periodeId,
            'statut'     => $statut,
            'divisionId' => $divisionId,
        ]);
    }



    private function exportXlsx($primes)
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        $sheet->fromArray([
            ['Matricule', 'Nom', 'Période', 'Division', 'Service', 'Statut', 'Montant']
        ], NULL, 'A1');

        $i = 2;
        foreach ($primes as $pp) {
            $sheet->fromArray([
                $pp->getEmployee()->getMatricule(),
                $pp->getEmployee()->getFullName(),
                (string)$pp->getPeriodePaie(),
                $pp->getEmployee()->getEmployeeSituations()->first()->getService()->getDivision()->getNom() ?? '',
                $pp->getEmployee()->getEmployeeSituations()->first()->getService()->getNom() ?? '',
                $pp->getStatus(),
                $pp->getMontantFormate()
            ], NULL, 'A' . $i++);
        }

        $writer = new Xlsx($spreadsheet);

        $response = new StreamedResponse(function () use ($writer) {
            $writer->save('php://output');
        });
        $filename = 'synthese_rh_' . date('Ymd_His') . '.xlsx';
        $response->headers->set('Content-Type', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
        $response->headers->set('Content-Disposition', 'attachment;filename="' . $filename . '"');
        $response->headers->set('Cache-Control', 'max-age=0');
        return $response;
    }

    private function exportPdf($primes)
    {
        $html = $this->renderView('rh/partials/export_pdf.html.twig', ['primes' => $primes]);
        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html);
        $dompdf->setPaper('A4', 'landscape');
        $dompdf->render();

        $filename = 'synthese_rh_' . date('Ymd_His') . '.pdf';

        return new Response($dompdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'attachment; filename="' . $filename . '"'
        ]);
    }

    
}
