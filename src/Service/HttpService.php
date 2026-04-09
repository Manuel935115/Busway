<?php

namespace App\Service;

use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class HttpService
{
    public function __construct(
        private LoggerInterface $logger,
        #[Autowire(param: 'cacert_path')]
        private string $cacertPath,
    ) {}

    /**
     * Realiza una petición GET con cURL.
     * Devuelve el body como string, o null si falla.
     *
     * @param array<string> $headers  Cabeceras en formato "Nombre: valor"
     */
    public function get(string $url, array $headers = [], int $timeout = 10): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CAINFO         => $this->cacertPath,
            CURLOPT_HTTPHEADER     => array_merge(['Accept: application/json'], $headers),
        ]);

        $body  = curl_exec($ch);
        $errno = curl_errno($ch);
        $error = curl_error($ch);
        curl_close($ch);

        if ($errno !== 0 || $body === false) {
            $this->logger->error('HttpService::get failed', [
                'url'   => $url,
                'errno' => $errno,
                'error' => $error,
            ]);
            return null;
        }

        return $body;
    }

    /**
     * Realiza una petición POST con cURL.
     * $body puede ser un string JSON o null para POST vacío.
     * Devuelve el body de respuesta, o null si falla.
     *
     * @param array<string> $headers
     */
    public function post(string $url, ?string $body = null, array $headers = [], int $timeout = 10): ?string
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => $body ?? '',
            CURLOPT_TIMEOUT        => $timeout,
            CURLOPT_SSL_VERIFYPEER => true,
            CURLOPT_CAINFO         => $this->cacertPath,
            CURLOPT_HTTPHEADER     => array_merge(['Content-Type: application/json', 'Accept: application/json'], $headers),
        ]);

        $response = curl_exec($ch);
        $errno    = curl_errno($ch);
        $error    = curl_error($ch);
        curl_close($ch);

        if ($errno !== 0 || $response === false) {
            $this->logger->error('HttpService::post failed', [
                'url'   => $url,
                'errno' => $errno,
                'error' => $error,
            ]);
            return null;
        }

        return $response;
    }
}
