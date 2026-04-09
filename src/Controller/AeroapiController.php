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
use App\Entity\Servicio;
use Symfony\Component\HttpFoundation\JsonResponse;

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
                            $error = "El número de vuelo «{$flightNumber}» no existe o no tiene planes de vuelo activos registrados en este momento.";
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
                                
                                // 2. VUELO (Actualizar si existe)
                                $vuelo = $em->getRepository(Vuelo::class)->findOneBy(['numero' => $flightNumber]);
                                if (!$vuelo) {
                                    $vuelo = new Vuelo();
                                    $vuelo->setNumero($flightNumber);
                                }
                                
                                if (!empty($bestFlight['scheduled_out'])) {
                                    $vuelo->setHoraSalidaProgramada(new \DateTime($bestFlight['scheduled_out']));
                                }
                                if (!empty($bestFlight['scheduled_in'])) {
                                    $vuelo->setHoraLlegadaProgramada(new \DateTime($bestFlight['scheduled_in']));
                                }
                                
                                $actualOut = $bestFlight['actual_out'] ?? $bestFlight['estimated_out'] ?? $bestFlight['scheduled_out'] ?? null;
                                if ($actualOut) {
                                    $vuelo->setHoraSalidaReal(new \DateTime($actualOut));
                                }
                                
                                $actualIn = $bestFlight['actual_in'] ?? $bestFlight['estimated_in'] ?? $bestFlight['scheduled_in'] ?? null;
                                if ($actualIn) {
                                    $vuelo->setHoraLlegadaReal(new \DateTime($actualIn));
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
                                $em->persist($vuelo); // Re-persist para asegurar cambios

                                $em->flush();
                            } catch (\Exception $e) { 
                                $error = "Error al guardar en Base de Datos: " . $e->getMessage();
                            }
                        }
                    }
                }
            }
        }

        $servicios = $em->getRepository(Servicio::class)->createQueryBuilder('s')
            ->where('s.vueloTren NOT LIKE :ave')
            ->andWhere('s.vueloTren != :pendiente')
            ->andWhere('s.vueloTren != :empty')
            ->andWhere('s.codigo != :headerRow')
            ->setParameter('ave', 'AVE%')
            ->setParameter('pendiente', 'Pendiente')
            ->setParameter('empty', '')
            ->setParameter('headerRow', 'codigo')
            ->getQuery()
            ->getResult();

        return $this->render('aeroapi/index.html.twig', [
            'controller_name' => 'AeroapiController',
            'flight_info' => $flightData,
            'searched_flight' => $flightNumber,
            'error' => $error,
            'servicios' => $servicios,
        ]);
    }

    #[Route('/api/vuelo/{flight}', name: 'api_vuelo_info', requirements: ['flight' => '.+'])]
    public function apiVuelo(string $flight, EntityManagerInterface $em): JsonResponse
    {
        $apiKey = $_ENV['AERO_API_KEY'] ?? '';
        if (empty($apiKey)) {
            return new JsonResponse(['error' => 'Sin clave API'], 500);
        }

        $fiveMinutesAgo = new \DateTime('-5 minutes');
        $cached = $em->getRepository(EstadoVuelo::class)->createQueryBuilder('ev')
            ->join('ev.vuelo', 'v')
            ->where('v.numero = :fn')
            ->andWhere('ev.fechaHora >= :t')
            ->setParameter('fn', $flight)
            ->setParameter('t', $fiveMinutesAgo)
            ->orderBy('ev.fechaHora', 'DESC')
            ->setMaxResults(1)
            ->getQuery()->getOneOrNullResult();

        if ($cached && !empty($cached->getRawData())) {
            $response = new JsonResponse(['flight' => $cached->getRawData(), 'cached' => true]);
            $response->setCache(['public' => true, 'max_age' => 300]);
            return $response;
        }

        $options = ['http' => ['method' => 'GET', 'header' => ["x-apikey: $apiKey", "Accept: application/json"], 'ignore_errors' => true]];
        $json = @file_get_contents("https://aeroapi.flightaware.com/aeroapi/flights/" . urlencode($flight), false, stream_context_create($options));

        if (!$json) return new JsonResponse(['error' => 'No se pudo contactar la API'], 500);

        $data = json_decode($json, true);
        if (isset($data['detail'])) return new JsonResponse(['error' => $data['detail']], 400);
        if (empty($data['flights'])) return new JsonResponse(['error' => 'Vuelo no encontrado'], 404);

        $bestFlight = null;
        $now = time(); $minDiff = PHP_INT_MAX;
        foreach ($data['flights'] as $f) {
            if (stripos($f['status'] ?? '', 'En Route') !== false) { $bestFlight = $f; break; }
            $t = isset($f['scheduled_out']) ? strtotime($f['scheduled_out']) : 0;
            if ($t > 0 && abs($now - $t) < $minDiff) { $minDiff = abs($now - $t); $bestFlight = $f; }
        }
        if (!$bestFlight) $bestFlight = $data['flights'][0];

        try {
            $statusStr = mb_substr($bestFlight['status'] ?? 'Desconocido', 0, 100);
            $estado = $em->getRepository(Estado::class)->findOneBy(['nombre' => $statusStr]);
            if (!$estado) { $estado = new Estado(); $estado->setNombre($statusStr); $em->persist($estado); }
            $vuelo = $em->getRepository(Vuelo::class)->findOneBy(['numero' => $flight]);
            if (!$vuelo) { $vuelo = new Vuelo(); $vuelo->setNumero($flight); }
            if (!empty($bestFlight['scheduled_out'])) $vuelo->setHoraSalidaProgramada(new \DateTime($bestFlight['scheduled_out']));
            if (!empty($bestFlight['scheduled_in'])) $vuelo->setHoraLlegadaProgramada(new \DateTime($bestFlight['scheduled_in']));
            $vuelo->setEstadoActual($estado); $em->persist($vuelo);
            $ev = $em->getRepository(EstadoVuelo::class)->findOneBy(['vuelo' => $vuelo]);
            if (!$ev) { $ev = new EstadoVuelo(); $ev->setVuelo($vuelo); }
            $ev->setEstado($estado); $ev->setFechaHora(new \DateTime()); $ev->setRawData($bestFlight);
            $em->persist($ev); $em->flush();
        } catch (\Throwable) {}

        $response = new JsonResponse(['flight' => $bestFlight, 'cached' => false]);
        $response->setCache(['public' => true, 'max_age' => 300]);
        return $response;
    }

    #[Route('/historial', name: 'app_aeroapi_historial')]
    public function historial(EntityManagerInterface $em): Response
    {
        // Obtener todo el historial ordenado por los más recientes
        $records = $em->getRepository(EstadoVuelo::class)->findBy([], ['fechaHora' => 'DESC']);

        return $this->render('aeroapi/historial.html.twig', [
            'records' => $records,
        ]);
    }
    #[Route('/historial/eliminar/{id}', name: 'app_aeroapi_eliminar', methods: ['POST'])]
    public function eliminar(int $id, EntityManagerInterface $em): Response
    {
        $estadoVuelo = $em->getRepository(EstadoVuelo::class)->find($id);
        if ($estadoVuelo) {
            $vuelo = $estadoVuelo->getVuelo();
            $em->remove($estadoVuelo);
            
            // Verificar si otros historiales usan este vuelo
            $otros = $em->getRepository(EstadoVuelo::class)->count(['vuelo' => $vuelo]);
            if ($otros === 1 && $vuelo) { // 1 es el que acabamos de marcar para borrar
                $em->remove($vuelo);
            }
            $em->flush();
            $this->addFlash('success', 'Registro eliminado correctamente.');
        }

        return $this->redirectToRoute('app_aeroapi_historial');
    }
}
