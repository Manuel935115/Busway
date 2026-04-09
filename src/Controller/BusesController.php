<?php

namespace App\Controller;

use App\Entity\BusquedaParada;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class BusesController extends AbstractController
{
    private string $tokenFile;
    private string $stopsFile;

    public function __construct(private EntityManagerInterface $em)
    {
        $this->tokenFile = sys_get_temp_dir() . '/emt_token.json';
        $this->stopsFile = sys_get_temp_dir() . '/emt_stops.json';
    }

    // ── Token management ──────────────────────────────────────────────────────

    private function getToken(): ?string
    {
        if (file_exists($this->tokenFile)) {
            $data = json_decode(file_get_contents($this->tokenFile), true);
            if ($data && isset($data['token'], $data['expires']) && time() < $data['expires']) {
                return $data['token'];
            }
        }

        $email    = $_ENV['EMT_EMAIL']    ?? '';
        $password = $_ENV['EMT_PASSWORD'] ?? '';

        if (!$email || !$password) {
            return null;
        }

        $ch = curl_init('https://openapi.emtmadrid.es/v2/mobilitylabs/user/login/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_HTTPHEADER     => [
                'email: ' . $email,
                'password: ' . $password,
                'Content-Type: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) return null;

        $json = json_decode($response, true);
        $token = $json['data'][0]['accessToken'] ?? null;

        if ($token) {
            file_put_contents($this->tokenFile, json_encode([
                'token'   => $token,
                'expires' => time() + 3300,
            ]));
        }

        return $token;
    }

    // ── Arrivals call ─────────────────────────────────────────────────────────

    private function getArrivals(string $stopId, string $token): ?array
    {
        $url  = "https://openapi.emtmadrid.es/v2/transport/busemtmad/stops/{$stopId}/arrives/";
        $body = json_encode([
            'cultureInfo'                                  => 'ES',
            'Text_StopRequired_YN'                         => 'Y',
            'Text_EstimationsRequired_YN'                  => 'Y',
            'Text_IncidencesRequired_YN'                   => 'N',
            'DateTime_Referenced_Incidencies_YYYYMMDD'     => date('Ymd'),
        ]);

        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body,
            CURLOPT_HTTPHEADER     => [
                'accessToken: ' . $token,
                'Content-Type: application/json',
            ],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

        if (!$response) return null;
        return json_decode($response, true);
    }

    // ── Stops cache ───────────────────────────────────────────────────────────

    private function getStops(string $token): array
    {
        if (file_exists($this->stopsFile)) {
            $data = json_decode(file_get_contents($this->stopsFile), true);
            if ($data && isset($data['expires']) && time() < $data['expires']) {
                return $data['stops'];
            }
        }

        $ch = curl_init('https://openapi.emtmadrid.es/v2/transport/busemtmad/stops/arroundxy/-3.70325/40.41650/5000/');
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_HTTPHEADER     => ['accessToken: ' . $token],
        ]);
        $response = curl_exec($ch);
        curl_close($ch);

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

        file_put_contents($this->stopsFile, json_encode([
            'expires' => time() + 21600,
            'stops'   => $stops,
        ]));

        return $stops;
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

        return new JsonResponse($results);
    }

    #[Route('/api/buses/parada/{stopId}', name: 'api_buses_parada')]
    public function apiParada(string $stopId): JsonResponse
    {
        $token = $this->getToken();
        if (!$token) return new JsonResponse(['error' => 'Sin token EMT'], 500);

        $data = $this->getArrivals($stopId, $token);
        if (!$data || ($data['code'] ?? '') !== '00') {
            return new JsonResponse(['error' => $data['description'] ?? 'Error al consultar la parada'], 400);
        }

        return new JsonResponse([
            'arrivals' => $data['data'][0]['Arrive'] ?? [],
            'stopInfo' => $data['data'][0]['StopInfo'][0] ?? null,
        ]);
    }

    #[Route('/buses', name: 'app_buses')]
    public function index(Request $request): Response
    {
        $stopId   = trim($request->query->get('parada', ''));
        $arrivals = null;
        $stopInfo = null;
        $error    = null;

        if ($stopId !== '') {
            $token = $this->getToken();
            if (!$token) {
                $error = 'No se pudo obtener el token de la API EMT. Comprueba las credenciales en .env';
            } else {
                $data = $this->getArrivals($stopId, $token);
                if (!$data || ($data['code'] ?? '') !== '00') {
                    $error = $data['description'] ?? 'Error al consultar la parada.';
                } else {
                    $arrivals = $data['data'][0]['Arrive']    ?? [];
                    $stopInfo = $data['data'][0]['StopInfo'][0] ?? null;

                    try {
                        $busqueda = new BusquedaParada();
                        $busqueda->setStopId($stopId);
                        $busqueda->setStopName($stopInfo['stopName'] ?? $stopInfo['name'] ?? null);
                        $busqueda->setAddress($stopInfo['postalAddress'] ?? $stopInfo['address'] ?? null);
                        $this->em->persist($busqueda);
                        $this->em->flush();
                    } catch (\Throwable) {}
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

        return new JsonResponse(array_map(fn($r) => [
            'id'   => $r['stopId'],
            'name' => $r['stopName'] ?? '',
            'addr' => $r['address'] ?? '',
        ], $rows));
    }
}
