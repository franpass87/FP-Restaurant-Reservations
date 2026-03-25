<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Closures;

use DateTimeInterface;
use WP_REST_Response;
use function ob_get_clean;
use function ob_get_level;
use function ob_start;
use function strpos;

/**
 * Costruisce risposte REST pulite per le chiusure.
 * Estratto da REST per migliorare la manutenibilità.
 */
final class ClosuresResponseBuilder
{
    /**
     * Forza una risposta JSON pulita rimuovendo output spurio.
     */
    public function forceCleanJsonResponse($response, $server, $request)
    {
        // Solo per i nostri endpoint closures
        $route = $request->get_route();
        if (!is_string($route) || strpos($route, '/fp-resv/v1/closures') === false) {
            return $response;
        }

        // Pulisci TUTTI gli output buffer aperti
        while (ob_get_level() > 0) {
            ob_get_clean();
        }

        // Verifica che la risposta sia valida
        if (!$response instanceof \WP_REST_Response) {
            return $response;
        }

        return $response;
    }

    /**
     * Cattura e rimuove output spurio.
     */
    public function captureAndCleanOutput(): void
    {
        ob_get_clean();
    }

    /**
     * Avvia la cattura output.
     */
    public function startOutputCapture(): void
    {
        ob_start();
    }

    /**
     * Crea una risposta REST con header JSON.
     *
     * @param array<string, mixed> $data
     */
    public function createJsonResponse(array $data, int $status = 200): WP_REST_Response
    {
        $response = new WP_REST_Response($data, $status);
        $response->set_headers([
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);

        return $response;
    }

    /**
     * Formatta il range per la risposta.
     *
     * @param array<string, \DateTimeImmutable> $range
     * @return array<string, string>
     */
    public function formatRange(array $range): array
    {
        return [
            'start' => $range['start']->format(DateTimeInterface::ATOM),
            'end'   => $range['end']->format(DateTimeInterface::ATOM),
        ];
    }
}















