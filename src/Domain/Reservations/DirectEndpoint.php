<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use WP_REST_Request;

// Importa le funzioni globali necessarie
use function add_action;
use function file_get_contents;
use function json_decode;
use function json_encode;
use function header;
use function http_response_code;
use function is_array;
use function ob_get_level;
use function ob_end_clean;
use function remove_all_actions;
use function remove_all_filters;
use function strpos;

/**
 * Endpoint diretto che bypassa completamente il sistema REST di WordPress
 * per evitare interferenze con output buffering
 */
final class DirectEndpoint
{
    private REST $restController;

    public function __construct(REST $restController)
    {
        $this->restController = $restController;
    }

    public function register(): void
    {
        // Hook MOLTO PRECOCE per intercettare la richiesta PRIMA di WordPress REST
        add_action('parse_request', [$this, 'handleDirectRequest'], 1);
    }

    public function handleDirectRequest(\WP $wp): void
    {
        // Controlla se è una richiesta al nostro endpoint diretto
        if (!isset($_SERVER['REQUEST_URI'])) {
            return;
        }

        $requestUri = $_SERVER['REQUEST_URI'];
        
        // Endpoint diretto: /wp-json/fp-resv/v1/reservations/direct
        if (strpos($requestUri, '/wp-json/fp-resv/v1/reservations/direct') === false) {
            return;
        }

        // Verifica che sia POST
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            $this->sendJsonError(405, 'fp_resv_method_not_allowed', 'Method not allowed');
            return;
        }

        // Log rimosso per evitare dipendenze circolari
        
        try {
            // Ottieni il body della richiesta
            $body = file_get_contents('php://input');
            $data = json_decode($body, true);

            if (!is_array($data)) {
                $this->sendJsonError(400, 'fp_resv_invalid_json', 'Invalid JSON');
                return;
            }

            // Crea un fake WP_REST_Request per riutilizzare la logica esistente
            $request = new WP_REST_Request('POST', '/fp-resv/v1/reservations');
            $request->set_body($body);
            $request->set_header('Content-Type', 'application/json');

            // Imposta i parametri
            foreach ($data as $key => $value) {
                $request->set_param($key, $value);
            }

            // Chiama il metodo del controller REST esistente
            // Nota: handleCreateReservation ora usa output diretto, quindi NON ritorna qui
            $this->restController->handleCreateReservation($request);

            // Se arriviamo qui, significa che non c'è stato output diretto
            $this->sendJsonError(500, 'fp_resv_no_output', 'No output generated');

        } catch (\Throwable $e) {
            $this->sendJsonError(500, 'fp_resv_server_error', $e->getMessage());
        }
    }

    private function sendJsonError(int $status, string $code, string $message): void
    {
        // Pulisce TUTTI i buffer
        while (ob_get_level()) {
            ob_end_clean();
        }

        // Previene qualsiasi altra azione di WordPress
        remove_all_actions('shutdown');
        remove_all_filters('rest_post_dispatch');

        header('Content-Type: application/json; charset=UTF-8');
        http_response_code($status);
        echo json_encode([
            'code' => $code,
            'message' => $message,
            'data' => ['status' => $status],
        ]);
        die();
    }
}

