<?php

namespace App\Controller;

use App\Entity\EstadoTren;
use App\Entity\EstadoVuelo;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class NotificacionesController extends AbstractController
{
    public function __construct(private EntityManagerInterface $em) {}

    #[Route('/notificaciones', name: 'app_notificaciones')]
    public function index(): Response
    {
        $datos = $this->getDatos();
        return $this->render('notificaciones/index.html.twig', $datos);
    }

    #[Route('/api/notificaciones', name: 'api_notificaciones')]
    public function api(): JsonResponse
    {
        return new JsonResponse($this->getDatos());
    }

    private function getDatos(): array
    {
        $trenes = [];
        $estadosTren = $this->em->getRepository(EstadoTren::class)->findAll();
        foreach ($estadosTren as $et) {
            if (($et->getRetraso() ?? 0) > 0) {
                $trenes[] = [
                    'codigo'     => $et->getTren()->getCodigoComercial(),
                    'tipo'       => $et->getTren()->getTipo(),
                    'retraso'    => $et->getRetraso(),
                    'origen'     => $et->getOrigen(),
                    'destino'    => $et->getDestino(),
                    'actualizado'=> $et->getFechaHora()?->format('H:i'),
                ];
            }
        }

        $vuelos = [];
        $estadosVuelo = $this->em->createQuery(
            'SELECT ev FROM App\Entity\EstadoVuelo ev ORDER BY ev.fechaHora DESC'
        )->getResult();

        $vuelosVistos = [];
        foreach ($estadosVuelo as $ev) {
            $numero = $ev->getVuelo()->getNumero();
            if (isset($vuelosVistos[$numero])) {
                continue;
            }
            $vuelosVistos[$numero] = true;

            $vuelo      = $ev->getVuelo();
            $programada = $vuelo->getHoraSalidaProgramada();
            $real       = $vuelo->getHoraSalidaReal();

            if ($programada && $real && $real > $programada) {
                $retrasoMin = (int)(($real->getTimestamp() - $programada->getTimestamp()) / 60);
                $vuelos[] = [
                    'numero'     => $numero,
                    'retraso'    => $retrasoMin,
                    'estado'     => $ev->getEstado()?->getNombre(),
                    'actualizado'=> $ev->getFechaHora()?->format('H:i'),
                ];
            }
        }

        return [
            'trenes' => $trenes,
            'vuelos' => $vuelos,
            'total'  => count($trenes) + count($vuelos),
        ];
    }
}
