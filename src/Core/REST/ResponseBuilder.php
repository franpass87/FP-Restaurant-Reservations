<?php

declare(strict_types=1);

namespace FP\Resv\Core\REST;

use WP_Error;
use WP_REST_Response;
use function __;
use function rest_ensure_response;

/**
 * Utility centralizzata per costruire risposte REST API consistenti.
 * Riduce duplicazione e standardizza formattazione risposte.
 */
final class ResponseBuilder
{
    /**
     * Crea una risposta di successo.
     *
     * @param mixed $data Dati da includere nella risposta
     * @param int $code Codice HTTP (default: 200)
     * @param array<string, mixed> $meta Metadati aggiuntivi
     * @return WP_REST_Response Risposta REST
     */
    public static function success(mixed $data, int $code = 200, array $meta = []): WP_REST_Response
    {
        $response = [
            'success' => true,
            'data' => $data,
        ];

        if ($meta !== []) {
            $response['meta'] = $meta;
        }

        return rest_ensure_response($response);
    }

    /**
     * Crea una risposta di errore.
     *
     * @param string $message Messaggio di errore
     * @param int $code Codice HTTP (default: 400)
     * @param array<string, mixed> $data Dati aggiuntivi
     * @param string $code_error Codice errore personalizzato
     * @return WP_Error Errore REST
     */
    public static function error(string $message, int $code = 400, array $data = [], string $code_error = 'rest_error'): WP_Error
    {
        return new WP_Error($code_error, $message, [
            'status' => $code,
            'data' => $data,
        ]);
    }

    /**
     * Crea una risposta paginata.
     *
     * @param array<int, mixed> $items Elementi della pagina
     * @param int $total Numero totale di elementi
     * @param int $page Numero pagina corrente (1-based)
     * @param int $perPage Elementi per pagina
     * @param array<string, mixed> $meta Metadati aggiuntivi
     * @return WP_REST_Response Risposta REST paginata
     */
    public static function paginated(array $items, int $total, int $page, int $perPage, array $meta = []): WP_REST_Response
    {
        $totalPages = (int) ceil($total / $perPage);

        $response = [
            'success' => true,
            'data' => $items,
            'pagination' => [
                'total' => $total,
                'total_pages' => $totalPages,
                'current_page' => $page,
                'per_page' => $perPage,
                'has_next' => $page < $totalPages,
                'has_prev' => $page > 1,
            ],
        ];

        if ($meta !== []) {
            $response['meta'] = $meta;
        }

        return rest_ensure_response($response);
    }

    /**
     * Crea una risposta con validazione errori.
     *
     * @param array<string, array<int, string>> $errors Errori di validazione per campo
     * @param int $code Codice HTTP (default: 422)
     * @return WP_Error Errore REST con errori di validazione
     */
    public static function validationError(array $errors, int $code = 422): WP_Error
    {
        $message = __('Errore di validazione.', 'fp-restaurant-reservations');

        return new WP_Error('rest_validation_error', $message, [
            'status' => $code,
            'errors' => $errors,
        ]);
    }

    /**
     * Crea una risposta "non autorizzato".
     *
     * @param string $message Messaggio personalizzato (opzionale)
     * @return WP_Error Errore REST 401
     */
    public static function unauthorized(string $message = ''): WP_Error
    {
        if ($message === '') {
            $message = __('Non autorizzato.', 'fp-restaurant-reservations');
        }

        return new WP_Error('rest_forbidden', $message, [
            'status' => 401,
        ]);
    }

    /**
     * Crea una risposta "non trovato".
     *
     * @param string $message Messaggio personalizzato (opzionale)
     * @return WP_Error Errore REST 404
     */
    public static function notFound(string $message = ''): WP_Error
    {
        if ($message === '') {
            $message = __('Risorsa non trovata.', 'fp-restaurant-reservations');
        }

        return new WP_Error('rest_not_found', $message, [
            'status' => 404,
        ]);
    }

    /**
     * Crea una risposta "errore server".
     *
     * @param string $message Messaggio personalizzato (opzionale)
     * @param array<string, mixed> $data Dati aggiuntivi
     * @return WP_Error Errore REST 500
     */
    public static function serverError(string $message = '', array $data = []): WP_Error
    {
        if ($message === '') {
            $message = __('Errore interno del server.', 'fp-restaurant-reservations');
        }

        return new WP_Error('rest_server_error', $message, [
            'status' => 500,
            'data' => $data,
        ]);
    }

    /**
     * Crea una risposta "conflitto" (409).
     *
     * @param string $message Messaggio personalizzato
     * @param array<string, mixed> $data Dati aggiuntivi
     * @return WP_Error Errore REST 409
     */
    public static function conflict(string $message, array $data = []): WP_Error
    {
        return new WP_Error('rest_conflict', $message, [
            'status' => 409,
            'data' => $data,
        ]);
    }
}
















