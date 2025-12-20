<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\REST;

use FP\Resv\Core\Helpers;
use FP\Resv\Core\Metrics;
use FP\Resv\Core\RateLimiter;
use FP\Resv\Domain\Reservations\Availability;
use InvalidArgumentException;
use Throwable;
use function __;
use function absint;
use function current_time;
use function get_transient;
use function is_array;
use function is_string;
use function md5;
use function preg_match;
use function sanitize_text_field;
use function serialize;
use function set_transient;
use function strtolower;
use function wp_cache_get;
use function wp_cache_set;
use function wp_date;
use function wp_json_encode;
use DateTimeImmutable;
use function wp_rand;
use function wp_timezone;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

/**
 * Gestisce le richieste REST relative alla disponibilità.
 * Estratto da REST.php per migliorare modularità.
 */
final class AvailabilityHandler
{
    public function __construct(
        private readonly Availability $availability
    ) {
    }

    public function handleAvailability(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $ip = Helpers::clientIp();
        if (!RateLimiter::allow('availability:' . $ip, 30, 60)) {
            $payload = [
                'code'    => 'fp_resv_availability_rate_limited',
                'message' => __('Hai effettuato troppe richieste di disponibilità. Attendi qualche secondo e riprova.', 'fp-restaurant-reservations'),
                'data'    => [
                    'status'      => 429,
                    'retry_after' => 20,
                ],
            ];

            $response = new WP_REST_Response($payload, 429);
            $response->set_headers([
                'Retry-After'   => '20',
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]);

            Metrics::increment('availability.rate_limited');
            return $response;
        }

        $criteria = [
            'date'  => $request->get_param('date'),
            'party' => absint($request->get_param('party')),
        ];

        $meal = $request->get_param('meal');
        if ($meal !== null && $meal !== '') {
            $criteria['meal'] = sanitize_text_field((string) $meal);
        }

        $room = $request->get_param('room');
        if ($room !== null) {
            $criteria['room'] = absint($room);
        }

        $event = $request->get_param('event_id');
        if ($event !== null) {
            $criteria['event_id'] = absint($event);
        }

        $cacheKeyPayload = [
            'date'    => $criteria['date'],
            'party'   => $criteria['party'],
            'meal'    => $criteria['meal'] ?? '',
            'room'    => $criteria['room'] ?? '',
            'event'   => $criteria['event_id'] ?? '',
        ];

        $cacheKeyBase = wp_json_encode($cacheKeyPayload);
        if (!is_string($cacheKeyBase) || $cacheKeyBase === '') {
            $cacheKeyBase = serialize($cacheKeyPayload);
        }

        $cacheKey = 'fp_resv_avail_' . md5($cacheKeyBase);

        // Try wp_cache first (in-memory, faster)
        $wpCacheKey = 'fp_avail_' . md5($cacheKeyBase);
        $wpCached = wp_cache_get($wpCacheKey, 'fp_resv_api');
        
        if ($wpCached !== false && is_array($wpCached)) {
            Metrics::increment('availability.cache_hit', 1, ['type' => 'memory']);
            $response = rest_ensure_response($wpCached);
            if ($response instanceof WP_REST_Response) {
                $response->set_headers([
                    'Cache-Control'    => 'no-store, no-cache, must-revalidate, max-age=0',
                    'X-FP-Resv-Cache'  => 'hit-memory',
                ]);
            }
            return $response;
        }

        // Fallback to transient (database)
        $cached = get_transient($cacheKey);
        if (is_array($cached)) {
            Metrics::increment('availability.cache_hit', 1, ['type' => 'transient']);
            // Populate wp_cache for next request
            wp_cache_set($wpCacheKey, $cached, 'fp_resv_api', 10);
            
            $response = rest_ensure_response($cached);
            if ($response instanceof WP_REST_Response) {
                $response->set_headers([
                    'Cache-Control'    => 'no-store, no-cache, must-revalidate, max-age=0',
                    'X-FP-Resv-Cache'  => 'hit-transient',
                ]);
            }

            return $response;
        }

        Metrics::increment('availability.cache_miss');

        try {
            $result = $this->availability->findSlots($criteria);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error(
                'fp_resv_invalid_availability_params',
                $exception->getMessage(),
                ['status' => 400]
            );
        } catch (Throwable $exception) {
            return new WP_Error(
                'fp_resv_availability_error',
                __('Impossibile calcolare la disponibilità in questo momento.', 'fp-restaurant-reservations'),
                [
                    'status'  => 500,
                    'details' => defined('WP_DEBUG') && WP_DEBUG ? $exception->getMessage() : null,
                ]
            );
        }

        // Cache in both wp_cache (10s, memory) and transient (30-60s, DB fallback)
        wp_cache_set($wpCacheKey, $result, 'fp_resv_api', 10);
        set_transient($cacheKey, $result, wp_rand(30, 60));

        $response = rest_ensure_response($result);
        if ($response instanceof WP_REST_Response) {
            $response->set_headers([
                'Cache-Control'   => 'no-store, no-cache, must-revalidate, max-age=0',
                'X-FP-Resv-Cache' => 'miss',
            ]);
        }

        return $response;
    }

    public function handleAvailableDays(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            // Parametri
            $from = $request->get_param('from') ?: current_time('Y-m-d');
            $to = $request->get_param('to') ?: wp_date('Y-m-d', strtotime('+3 months'));
            $meal = $request->get_param('meal');

            // Usa la configurazione REALE dal meal plan invece di dati hardcoded
            $allDaysData = $this->availability->findAvailableDaysForAllMeals($from, $to);
            
            // Se è stato specificato un meal, filtra solo i giorni disponibili per quel meal
            if ($meal && is_string($meal) && $meal !== '') {
                $availableDays = [];
                foreach ($allDaysData as $date => $dayInfo) {
                    $mealAvailable = isset($dayInfo['meals'][$meal]) && $dayInfo['meals'][$meal];
                    $availableDays[$date] = [
                        'available' => $mealAvailable,
                        'meal' => $meal,
                    ];
                }
            } else {
                // Nessun meal specificato: ritorna tutti i giorni con info per ogni meal
                $availableDays = $allDaysData;
            }

            return new WP_REST_Response([
                'days' => $availableDays,
                'from' => $from,
                'to' => $to,
                'meal' => $meal,
            ], 200);

        } catch (\Exception $e) {
            // Log dell'errore per debug
            error_log('FP Resv REST API Error: ' . $e->getMessage());
            error_log('Stack trace: ' . $e->getTraceAsString());
            
            return new WP_Error(
                'fp_resv_availability_days_error',
                __('Errore nel recupero dei giorni disponibili.', 'fp-restaurant-reservations'),
                ['status' => 500]
            );
        }
    }

    public function handleAvailableSlots(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $date = $request->get_param('date');
            $meal = $request->get_param('meal');
            $party = absint($request->get_param('party'));

            if (!is_string($date) || !is_string($meal) || $party <= 0) {
                return new WP_Error(
                    'fp_resv_invalid_params',
                    __('Parametri non validi.', 'fp-restaurant-reservations'),
                    ['status' => 400]
                );
            }

            // Valida formato data
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $date)) {
                return new WP_Error(
                    'fp_resv_invalid_date_format',
                    __('Formato data non valido. Usare YYYY-MM-DD.', 'fp-restaurant-reservations'),
                    ['status' => 400]
                );
            }

            // Usa Availability per calcolare slot reali
            $timezone = wp_timezone();
            $dayStart = new DateTimeImmutable($date . ' 00:00:00', $timezone);
            $dayEnd = new DateTimeImmutable($date . ' 23:59:59', $timezone);
            
            $criteria = [
                'date' => $date,
                'meal' => $meal,
                'party' => $party,
            ];
            
            // Calcola slot per questa data
            $result = $this->availability->findSlotsForDateRange(
                $criteria,
                $dayStart,
                $dayEnd
            );
            
            // Estrai slot dal risultato (findSlotsForDateRange restituisce array[date => slots_array])
            if (!isset($result[$date]) || !is_array($result[$date])) {
                // Data non presente nel risultato o formato errato
                $slotsRaw = [];
            } else {
                $slotsRaw = $result[$date]['slots'] ?? [];
            }
            
            // Trasforma in formato compatibile frontend
            $slots = [];
            foreach ($slotsRaw as $slot) {
                if (!isset($slot['start'])) {
                    continue; // Skip slot senza start
                }
                
                // Parse start (backend restituisce ISO 8601 ATOM string)
                try {
                    $slotStart = new DateTimeImmutable($slot['start'], $timezone);
                } catch (\Exception $e) {
                    // Skip slot con formato datetime non valido
                    continue;
                }
                
                $slotTime = $slotStart->format('H:i');
                $slotStartFormatted = $slotStart->format('H:i:s');
                
                $status = $slot['status'] ?? 'unknown';
                $isAvailable = in_array($status, ['available', 'limited'], true);
                // Il backend usa 'available_capacity', non 'capacity'
                $capacity = isset($slot['available_capacity']) ? (int) $slot['available_capacity'] : 0;
                
                $slots[] = [
                    'time' => $slotTime,
                    'slot_start' => $slotStartFormatted,
                    'available' => $isAvailable,
                    'capacity' => $capacity,
                    'status' => $isAvailable ? 'available' : 'unavailable',
                ];
            }

            $response = new WP_REST_Response([
                'slots' => $slots,
                'date' => $date,
                'meal' => $meal,
                'party' => $party,
            ], 200);

            $response->set_headers([
                'Cache-Control' => 'public, max-age=60', // Cache per 1 minuto
            ]);

            return $response;
        } catch (\Exception $e) {
            return new WP_Error(
                'fp_resv_availability_slots_error',
                __('Errore nel recupero degli orari disponibili.', 'fp-restaurant-reservations'),
                ['status' => 500]
            );
        }
    }
}

