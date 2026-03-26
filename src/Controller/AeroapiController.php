<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Vuelo;
use App\Entity\Estado;
use App\Entity\EstadoVuelo;

class AeroapiController extends AbstractController
{
    #[Route('/aeroapi', name: 'app_aeroapi')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $flightNumber = trim($request->query->get('flight', '') ?? '');
        $flightData = null;
        $error = null;

        if ($flightNumber !== '') {
            $apiKey = $_ENV['AERO_API_KEY'] ?? ''; 
            
            if (empty($apiKey)) {
                $error = "Falta configurar la clave AERO_API_KEY en el archivo .env de tu proyecto.";
            } else {
                // --- SISTEMA DE CACHÉ (Ahorro de dinero) ---
                // Buscamos si ya hemos consultado este vuelo en los últimos 5 minutos
                $fiveMinutesAgo = new \DateTime('-5 minutes');
                $cachedRecord = $em->getRepository(EstadoVuelo::class)->createQueryBuilder('ev')
                    ->join('ev.vuelo', 'v')
                    ->where('v.numero = :fn')
                    ->andWhere('ev.fechaHora >= :fiveMins')
                    ->setParameter('fn', $flightNumber)
                    ->setParameter('fiveMins', $fiveMinutesAgo)
                    ->orderBy('ev.fechaHora', 'DESC')
                    ->setMaxResults(1)
                    ->getQuery()
                    ->getOneOrNullResult();

                if ($cachedRecord && !empty($cachedRecord->getRawData())) {
                    // ¡Tenemos datos recientes! Usamos la caché para NO gastar dinero en la API
                    $bestFlight = $cachedRecord->getRawData();
                    $flightData = ['flights' => [$bestFlight]];
                    $mapBase64 = null;
                } else {
                    // No hay caché o es antigua: Hacemos la llamada real a la API
                    $options = [
                        'http' => [
                            'method' => 'GET',
                            'header' => [
                                "x-apikey: " . trim($apiKey),
                                "Accept: application/json; charset=UTF-8"
                            ],
                            'ignore_errors' => true
                        ]
                    ];
                    
                    $context = stream_context_create($options);
                    $url = "https://aeroapi.flightaware.com/aeroapi/flights/" . urlencode($flightNumber);
                    $jsonResponse = @file_get_contents($url, false, $context);
                    
                    if ($jsonResponse === false) {
                        $error = "Vuelo '$flightNumber' no encontrado o no se pudo contactar con la API de FlightAware.";
                    } else {
                        $flightData = json_decode($jsonResponse, true);
                        
                        if (isset($flightData['detail'])) {
                            $error = "Error de FlightAware: " . $flightData['detail'];
                            $flightData = null;
                        } elseif (empty($flightData['flights'])) {
                            $error = "No se encontraron resultados en FlightAware para '$flightNumber'.";
                            $flightData = null;
                        } else {
                            $bestFlight = null;
                            $now = time();
                            $minDiff = PHP_INT_MAX;

                            foreach ($flightData['flights'] as $flight) {
                                if (stripos($flight['status'] ?? '', 'En Route') !== false) {
                                    $bestFlight = $flight;
                                    break;
                                }
                                $scheduledOut = isset($flight['scheduled_out']) ? strtotime($flight['scheduled_out']) : 0;
                                if ($scheduledOut > 0) {
                                    $diff = abs($now - $scheduledOut);
                                    if ($diff < $minDiff) {
                                        $minDiff = $diff;
                                        $bestFlight = $flight;
                                    }
                                }
                            }
                            
                            if (!$bestFlight) {
                                $bestFlight = $flightData['flights'][0] ?? null;
                            }
                            
                            $flightData['flights'] = [$bestFlight];

                            // Guardar en BD para futuras consultas y mantener un historial detallado (caché y datos)
                            try {
                                $statusStr = mb_substr($bestFlight['status'] ?? 'Desconocido', 0, 100);
                                
                                // 1. ESTADO
                                $estado = $em->getRepository(Estado::class)->findOneBy(['nombre' => $statusStr]);
                                if (!$estado) {
                                    $estado = new Estado();
                                    $estado->setNombre($statusStr);
                                    $em->persist($estado);
                                }
                                
                                // 2. VUELO
                                $vuelo = $em->getRepository(Vuelo::class)->findOneBy(['numero' => $flightNumber]);
                                if (!$vuelo) {
                                    $vuelo = new Vuelo();
                                    $vuelo->setNumero($flightNumber);
                                }
                                
                                if (!empty($bestFlight['scheduled_out'])) {
                                    $vuelo->setHoraSalida(new \DateTime($bestFlight['scheduled_out']));
                                }
                                if (!empty($bestFlight['scheduled_in'])) {
                                    $vuelo->setHoraLlegada(new \DateTime($bestFlight['scheduled_in']));
                                }
                                $vuelo->setEstadoActual($estado);
                                $em->persist($vuelo);
                                
                                // 3. ESTADO_VUELO (Un solo registro por vuelo, se actualiza)
                                $estadoVuelo = $em->getRepository(EstadoVuelo::class)->findOneBy(['vuelo' => $vuelo]);
                                if (!$estadoVuelo) {
                                    $estadoVuelo = new EstadoVuelo();
                                    $estadoVuelo->setVuelo($vuelo);
                                }
                                $estadoVuelo->setEstado($estado);
                                $estadoVuelo->setFechaHora(new \DateTime());
                                
                                $actualOut = $bestFlight['actual_out'] ?? $bestFlight['estimated_out'] ?? $bestFlight['scheduled_out'] ?? null;
                                if ($actualOut) {
                                    $estadoVuelo->setHoraSalida(new \DateTime($actualOut));
                                }
                                
                                $actualIn = $bestFlight['actual_in'] ?? $bestFlight['estimated_in'] ?? $bestFlight['scheduled_in'] ?? null;
                                if ($actualIn) {
                                    $estadoVuelo->setHoraLlegada(new \DateTime($actualIn));
                                }
                                
                                $estadoVuelo->setRawData($bestFlight);
                                $em->persist($estadoVuelo);
                                
                                $em->flush();
                            } catch (\Exception $e) { 
                                $error = "Error al guardar en Base de Datos: " . $e->getMessage();
                            }
                        }
                    }
                }
            }
        }

        return $this->render('aeroapi/index.html.twig', [
            'controller_name' => 'AeroapiController',
            'flight_info' => $flightData,
            'searched_flight' => $flightNumber,
            'error' => $error,
        ]);
    }

    #[Route('/historial', name: 'app_aeroapi_historial')]
    public function historial(EntityManagerInterface $em): Response
    {
        // Obtener solo el registro más reciente de cada vuelo (sin duplicados)
        $records = $em->createQueryBuilder()
            ->select('ev')
            ->from(EstadoVuelo::class, 'ev')
            ->where('ev.id IN (
                SELECT MAX(ev2.id) FROM App\Entity\EstadoVuelo ev2 GROUP BY ev2.vuelo
            )')
            ->orderBy('ev.fechaHora', 'DESC')
            ->getQuery()
            ->getResult();

        return $this->render('aeroapi/historial.html.twig', [
            'records' => $records,
        ]);
    }
}
