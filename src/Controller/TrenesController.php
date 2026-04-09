<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Doctrine\ORM\EntityManagerInterface;
use App\Entity\Tren;
use App\Entity\EstadoTren;

class TrenesController extends AbstractController
{
    private const API_BASE = 'https://retrasometro.com';

    private function curlGet(string $url, array $headers = []): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => 10,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json'], $headers),
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return $body ?: null;
    }

    private function curlPost(string $url): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => '',
            CURLOPT_TIMEOUT        => 5,
            CURLOPT_SSL_VERIFYPEER => false,
            CURLOPT_HTTPHEADER     => ['Content-Type: application/json', 'Accept: application/json'],
        ]);
        $body = curl_exec($ch);
        curl_close($ch);
        return $body ?: null;
    }

    private function getApiKey(): ?string
    {
        $raw = $this->curlPost(self::API_BASE . '/api/auth/request-key');
        if (!$raw) return null;
        $data = json_decode($raw, true);
        return ($data['ok'] ?? false) ? $data['apiKey'] : null;
    }

    private function fetchTrains(string $apiKey, string $search = '', int $minDelay = 0): ?array
    {
        $params = http_build_query(['search' => $search, 'min_delay' => $minDelay]);
        $raw = $this->curlGet(
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

        if ($search !== '') {
            $apiKey = $this->getApiKey();

            if (!$apiKey) {
                $error = 'No se pudo obtener clave temporal de retrasometro.com. Comprueba que el servidor tiene acceso a Internet (cURL).';
            } else {
                $data = $this->fetchTrains($apiKey, $search);

                if ($data === null) {
                    $error = 'Error de red: no se pudo conectar con la API de Retrasómetro.';
                } elseif (!isset($data['items'])) {
                    $msg = $data['message'] ?? json_encode($data);
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
                    // Enviar todos los trenes encontrados
                    if (!in_array($train, $allTrains)) {
                        array_unshift($allTrains, $train);
                    }

                    // --- GUARDADO EN BASE DE DATOS (Sin duplicados) ---
                    try {
                        // 1. Buscar o crear el Tren
                        $trenRepo = $em->getRepository(Tren::class);
                        $dbTren = $trenRepo->findOneBy(['codigoComercial' => $train['cod_comercial']]);
                        if (!$dbTren) {
                            $dbTren = new Tren();
                            $dbTren->setCodigoComercial($train['cod_comercial']);
                        }
                        $dbTren->setTipo($train['product_name'] ?? null);
                        $em->persist($dbTren);

                        // 2. Buscar o crear el Estado (Historial único por tren)
                        $estadoRepo = $em->getRepository(EstadoTren::class);
                        $dbEstado = $estadoRepo->findOneBy(['tren' => $dbTren]);
                        if (!$dbEstado) {
                            $dbEstado = new EstadoTren();
                            $dbEstado->setTren($dbTren);
                        }
                        
                        $dbEstado->setRetraso($train['ult_retraso'] ?? 0);
                        $dbEstado->setOrigen($train['origin_name'] ?? 'Desconocido');
                        $dbEstado->setDestino($train['destination_name'] ?? 'Desconocido');
                        $dbEstado->setRawData($train);
                        $dbEstado->setFechaHora(new \DateTime()); // Actualizar la hora de búsqueda

                        $em->persist($dbEstado);
                        $em->flush();
                    } catch (\Exception $e) {
                        // Error silencioso para no romper la experiencia de búsqueda
                    }
                }
            }
        }

        return $this->render('trenes/index.html.twig', [
            'searched'   => $search,
            'train'      => $train,
            'all_trains' => $allTrains,
            'error'      => $error,
        ]);
    }

}
