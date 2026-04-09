<?php

namespace App\Controller;

use App\Entity\BusquedaParada;
use App\Service\HttpService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class BusesController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $em,
        private HttpService $http,
        private LoggerInterface $logger,
        private CacheInterface $cache,
    ) {}

    // ── Token management ──────────────────────────────────────────────────────

    private function getToken(): ?string
    {
        return $this->cache->get('emt_token', function (ItemInterface $item): ?string {
            $item->expiresAfter(3300); // 55 minutos

            $email    = $_ENV['EMT_EMAIL']    ?? '';
            $password = $_ENV['EMT_PASSWORD'] ?? '';

            if (!$email || !$password) {
                $item->expiresAfter(60); // reintenta rápido si faltan credenciales
                return null;
            }

            $response = $this->http->post(
                'https://openapi.emtmadrid.es/v2/mobilitylabs/user/login/',
                null,
                ['email: ' . $email, 'password: ' . $password],
                10
            );

            if (!$response) {
                $item->expiresAfter(60);
                return null;
            }

            $json  = json_decode($response, true);
            $token = $json['data'][0]['accessToken'] ?? null;

            if (!$token) {
                $item->expiresAfter(60);
            }

            return $token;
        });
    }

    // ── Arrivals call ─────────────────────────────────────────────────────────

    private function getArrivals(string $stopId, string $token): ?array
    {
        $url  = "https://openapi.emtmadrid.es/v2/transport/busemtmad/stops/{$stopId}/arrives/";
        $body = json_encode([
            'cultureInfo'                              => 'ES',
            'Text_StopRequired_YN'                     => 'Y',
            'Text_EstimationsRequired_YN'              => 'Y',
            'Text_IncidencesRequired_YN'               => 'N',
            'DateTime_Referenced_Incidencies_YYYYMMDD' => date('Ymd'),
        ]);

        $response = $this->http->post($url, $body, ['accessToken: ' . $token], 10);

        if (!$response) return null;
        return json_decode($response, true);
    }

    // ── Stops cache ───────────────────────────────────────────────────────────

    private function getStops(string $token): array
    {
        return $this->cache->get('emt_stops', function (ItemInterface $item) use ($token): array {
            $item->expiresAfter(21600); // 6 horas

            $response = $this->http->get(
                'https://openapi.emtmadrid.es/v2/transport/busemtmad/stops/arroundxy/-3.70325/40.41650/5000/',
                ['accessToken: ' . $token],
                15
            );

            if (!$response) return [];

            $json  = json_decode($response, true);
            $stops = [];
            foreach ($json['data'] ?? [] as $s) {
                $stops[] = [
                    'id'   => $s['stopId'],
                    'name' => trim($s['stopName'] ?? ''),
                    'addr' => trim($s['address']  ?? ''),
                ];
            }

            return $stops;
        });
    }

    // ── Routes ────────────────────────────────────────────────────────────────

    #[Route('/api/buses/autocompletear', name: 'api_buses_autocompletear')]
    public function autocompletear(Request $request): JsonResponse
    {
        $q = mb_strtolower(trim($request->query->get('q', '')));
        if (strlen($q) < 2) {
            return new JsonResponse([]);
        }

        $token = $this->getToken();
        if (!$token) return new JsonResponse([]);

        $stops   = $this->getStops($token);
        $results = [];
        foreach ($stops as $s) {
            if (str_contains(mb_strtolower($s['name']), $q) || str_contains(mb_strtolower($s['addr']), $q) || str_starts_with((string)$s['id'], $q)) {
                $results[] = $s;
                if (count($results) >= 8) break;
            }
        }

        $response = new JsonResponse($results);
        $response->setCache(['public' => true, 'max_age' => 600]);
        return $response;
    }

    #[Route('/api/buses/parada/{stopId}', name: 'api_buses_parada')]
    public function apiParada(string $stopId): JsonResponse
    {
        // Validación: el stopId debe ser numérico
        if (!ctype_digit($stopId)) {
            return new JsonResponse(['error' => 'ID de parada no válido'], 400);
        }

        $token = $this->getToken();
        if (!$token) return new JsonResponse(['error' => 'Sin token EMT'], 500);

        $data = $this->getArrivals($stopId, $token);
        if (!$data || ($data['code'] ?? '') !== '00') {
            return new JsonResponse(['error' => $data['description'] ?? 'Error al consultar la parada'], 400);
        }

        $response = new JsonResponse([
            'arrivals' => $data['data'][0]['Arrive'] ?? [],
            'stopInfo' => $data['data'][0]['StopInfo'][0] ?? null,
        ]);
        $response->setCache(['public' => true, 'max_age' => 180]);
        return $response;
    }

    #[Route('/buses', name: 'app_buses')]
    public function index(Request $request): Response
    {
        $stopId   = trim($request->query->get('parada', ''));
        $arrivals = null;
        $stopInfo = null;
        $error    = null;

        if ($stopId !== '') {
            // Validación: el stopId debe ser numérico
            if (!ctype_digit($stopId)) {
                $error = 'El ID de parada debe ser numérico.';
            } else {
                $token = $this->getToken();
                if (!$token) {
                    $error = 'No se pudo obtener el token de la API EMT. Comprueba las credenciales en .env.local';
                } else {
                    $data = $this->getArrivals($stopId, $token);
                    if (!$data || ($data['code'] ?? '') !== '00') {
                        $error = $data['description'] ?? 'Error al consultar la parada.';
                    } else {
                        $arrivals = $data['data'][0]['Arrive']      ?? [];
                        $stopInfo = $data['data'][0]['StopInfo'][0] ?? null;

                        try {
                            $busqueda = new BusquedaParada();
                            $busqueda->setStopId($stopId);
                            $busqueda->setStopName($stopInfo['stopName'] ?? $stopInfo['name'] ?? null);
                            $busqueda->setAddress($stopInfo['postalAddress'] ?? $stopInfo['address'] ?? null);
                            $this->em->persist($busqueda);
                            $this->em->flush();
                        } catch (\Throwable $e) {
                            $this->logger->error('BusesController: error al guardar BusquedaParada en BD', [
                                'stopId'    => $stopId,
                                'exception' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }
        }

        return $this->render('buses/index.html.twig', [
            'stopId'   => $stopId,
            'arrivals' => $arrivals,
            'stopInfo' => $stopInfo,
            'error'    => $error,
        ]);
    }

    #[Route('/api/buses/recientes', name: 'api_buses_recientes')]
    public function recientes(): JsonResponse
    {
        $rows = $this->em->getRepository(BusquedaParada::class)
            ->createQueryBuilder('b')
            ->select('b.stopId, b.stopName, b.address, MAX(b.fechaHora) as lastSeen')
            ->groupBy('b.stopId, b.stopName, b.address')
            ->orderBy('lastSeen', 'DESC')
            ->setMaxResults(8)
            ->getQuery()
            ->getArrayResult();

        $response = new JsonResponse(array_map(fn($r) => [
            'id'   => $r['stopId'],
            'name' => $r['stopName'] ?? '',
            'addr' => $r['address'] ?? '',
        ], $rows));
        $response->setCache(['public' => true, 'max_age' => 900]);
        return $response;
    }
}
