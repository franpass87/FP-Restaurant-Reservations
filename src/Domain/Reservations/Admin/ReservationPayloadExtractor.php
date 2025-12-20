<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\Admin;

use InvalidArgumentException;
use WP_REST_Request;
use function filter_var;
use function in_array;
use function strtolower;
use function __;
use const FILTER_VALIDATE_EMAIL;

/**
 * Estrae e valida il payload di prenotazione dalle richieste REST.
 * Estratto da AdminREST per migliorare la manutenibilità.
 */
final class ReservationPayloadExtractor
{
    /**
     * Estrae il payload di prenotazione dalla richiesta.
     *
     * @return array<string, mixed>
     * @throws InvalidArgumentException
     */
    public function extract(WP_REST_Request $request): array
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP Resv Admin] extractReservationPayload() START');
            error_log('[FP Resv Admin] Request has body params: ' . ($request->get_body_params() ? 'YES' : 'NO'));
            error_log('[FP Resv Admin] Request has JSON params: ' . ($request->get_json_params() ? 'YES' : 'NO'));
        }
        
        $payload = [
            'date'       => $request->get_param('date') ?? '',
            'time'       => $request->get_param('time') ?? '',
            'party'      => $request->get_param('party') ?? 0,
            'meal'       => $request->get_param('meal') ?? '',
            'first_name' => $request->get_param('first_name') ?? '',
            'last_name'  => $request->get_param('last_name') ?? '',
            'email'      => $request->get_param('email') ?? '',
            'phone'      => $request->get_param('phone') ?? '',
            'notes'      => $request->get_param('notes') ?? '',
            'allergies'  => $request->get_param('allergies') ?? '',
            'language'   => $request->get_param('language') ?? '',
            'locale'     => $request->get_param('locale') ?? '',
            'location'   => $request->get_param('location') ?? '',
            'currency'   => $request->get_param('currency') ?? '',
            'utm_source' => $request->get_param('utm_source') ?? '',
            'utm_medium' => $request->get_param('utm_medium') ?? '',
            'utm_campaign' => $request->get_param('utm_campaign') ?? '',
            'status'     => $request->get_param('status') ?? null,
            'room_id'    => $request->get_param('room_id') ?? null,
            'table_id'   => $request->get_param('table_id') ?? null,
            'value'      => $request->get_param('value') ?? null,
            'allow_partial_contact' => true,
        ];

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP Resv Admin] Payload base costruito, email: ' . ($payload['email'] ?? 'EMPTY'));
        }

        if ($request->offsetExists('visited') && in_array(strtolower((string) $request->get_param('visited')), ['1', 'true', 'yes', 'on'], true)) {
            $payload['status'] = 'visited';
        }
        
        // Validazione opzionale email (solo se fornita)
        if (!empty($payload['email']) && !filter_var($payload['email'], FILTER_VALIDATE_EMAIL)) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Resv Admin] ERRORE: Email non valida: ' . $payload['email']);
            }
            throw new InvalidArgumentException(__('Email non valida', 'fp-restaurant-reservations'));
        }
        
        // NOTA: Nome, cognome, email e telefono sono opzionali per prenotazioni da backend
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP Resv Admin] extractReservationPayload() OK - payload valido (campi cliente opzionali)');
        }
        return $payload;
    }
}















