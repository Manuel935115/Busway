<?php

namespace App\Controller;

use App\Service\HttpService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Tren;
use App\Entity\EstadoTren;
use Psr\Log\LoggerInterface;

class TrenesController extends AbstractController
{
    private const API_BASE = 'https://retrasometro.com';

    public function __construct(
        private HttpService $http,
        private LoggerInterface $logger,
    ) {}

    private function getApiKey(): ?string
    {
        $raw = $this->http->post(self::API_BASE . '/api/auth/request-key', null, [], 5);
        if (!$raw) return null;
        $data = json_decode($raw, true);
        return ($data['ok'] ?? false) ? $data['apiKey'] : null;
    }

    private function fetchTrains(string $apiKey, string $search = '', int $minDelay = 0): ?array
    {
        $params = http_build_query(['search' => $search, 'min_delay' => $minDelay]);
        $raw = $this->http->get(
            self::API_BASE . '/api/trains?' . $params,
            ['x-api-key: ' . $apiKey]
        );
        if (!$raw) return null;
        return json_decode($raw, true);
    }

    #[Route('/trenes', name: 'app_trenes')]
    public function index(Request $request, EntityManagerInterface $em): Response
    {
        $search = trim($request->query->get('tren', '') ?? '');
        $train  = null;
        $error  = null;
        $allTrains = [];

        // Cargar todos los trenes guardados en la BD (siempre disponibles)
        $estadoRepo = $em->getRepository(EstadoTren::class);
        $todosLosEstados = $estadoRepo->findAll();
        $trenes_guardados = array_map(function ($e) {
            return [
                'cod_comercial'         => $e->getTren()->getCodigoComercial(),
                'product_name'          => $e->getTren()->getTipo(),
                'origin_name'           => $e->getOrigen(),
                'destination_name'      => $e->getDestino(),
                'ult_retraso'           => $e->getRetraso() ?? 0,
                'des_corridor'          => null,
                'next_station_name'     => null,
                'previous_station_name' => null,
            ];
        }, $todosLosEstados);

        if ($search !== '') {
            // Validación: máximo 20 caracteres, solo letras, números y guion
            if (strlen($search) > 20 || !preg_match('/^[a-zA-Z0-9\-]+$/', $search)) {
                $error = 'Código de tren no válido. Usa solo letras, números y guiones (máx. 20 caracteres).';
            } else {
                $apiKey = $this->getApiKey();

                if (!$apiKey) {
                    $error = 'No se pudo obtener clave temporal de retrasometro.com. Comprueba que el servidor tiene acceso a Internet (cURL).';
                } else {
                    $data = $this->fetchTrains($apiKey, $search);

                    if ($data === null) {
                        $error = 'Error de red: no se pudo conectar con la API de Retrasómetro.';
                    } elseif (!isset($data['items'])) {
                        $msg   = $data['message'] ?? json_encode($data);
                        $error = 'Respuesta inesperada de la API: ' . $msg;
                    } elseif (empty($data['items'])) {
                        $error = "El código «{$search}» no existe o no corresponde a ningún tren en circulación en este momento.";
                    } else {
                        $train = null;
                        foreach ($data['items'] as $t) {
                            if (strtoupper($t['cod_comercial'] ?? '') === strtoupper($search)) {
                                $train = $t;
                                break;
                            }
                        }
                        if (!$train) {
                            $train = $data['items'][0];
                        }
                        $allTrains = $data['items'];
                        if (!in_array($train, $allTrains)) {
                            array_unshift($allTrains, $train);
                        }

                        // --- GUARDADO EN BASE DE DATOS ---
                        try {
                            $trenRepo = $em->getRepository(Tren::class);
                            $dbTren   = $trenRepo->findOneBy(['codigoComercial' => $train['cod_comercial']]);
                            if (!$dbTren) {
                                $dbTren = new Tren();
                                $dbTren->setCodigoComercial($train['cod_comercial']);
                            }
                            $dbTren->setTipo($train['product_name'] ?? null);
                            $em->persist($dbTren);

                            $dbEstado = $estadoRepo->findOneBy(['tren' => $dbTren]);
                            if (!$dbEstado) {
                                $dbEstado = new EstadoTren();
                                $dbEstado->setTren($dbTren);
                            }
                            $dbEstado->setRetraso($train['ult_retraso'] ?? 0);
                            $dbEstado->setOrigen($train['origin_name'] ?? 'Desconocido');
                            $dbEstado->setDestino($train['destination_name'] ?? 'Desconocido');
                            $dbEstado->setRawData($train);
                            $dbEstado->setFechaHora(new \DateTime());

                            $em->persist($dbEstado);
                            $em->flush();
                        } catch (\Exception $e) {
                            $this->logger->error('TrenesController: error al guardar en BD', [
                                'search'    => $search,
                                'exception' => $e->getMessage(),
                            ]);
                        }
                    }
                }
            }
        }

        return $this->render('trenes/index.html.twig', [
            'searched'         => $search,
            'train'            => $train,
            'all_trains'       => $allTrains,
            'trenes_guardados' => $trenes_guardados,
            'error'            => $error,
        ]);
    }
}
