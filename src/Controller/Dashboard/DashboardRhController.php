<?php

// src/Controller/Dashboard/RhDashboardController.php
namespace App\Controller\Dashboard;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class DashboardRhController extends AbstractController
{
    #[Route('/rh/dashboard', name: 'dashboard_rh')]
    public function index(): Response
    {
        return $this->render('dashboard/rh.html.twig');
    }
}