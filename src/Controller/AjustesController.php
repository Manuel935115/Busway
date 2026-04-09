<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class AjustesController extends AbstractController
{
    #[Route('/ajustes', name: 'app_ajustes')]
    public function index(): Response
    {
        return $this->render('ajustes/index.html.twig', [
            'controller_name' => 'AjustesController',
        ]);
    }
}
