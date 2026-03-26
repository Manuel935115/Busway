<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class HomeController extends AbstractController
{
    #[Route(['/', '/index'], name: 'app_welcome')]
    public function index(): Response
    {
        return $this->render('home/index.html.twig', [
            'controller_name' => 'HomeController',
            'mensaje' => '¡Bienvenido a tu nuevo proyecto en Symfony 6.4!',
            'fecha' => new \DateTime(),
        ]);
    }
}