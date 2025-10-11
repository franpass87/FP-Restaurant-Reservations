<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Events;

use FP\Resv\Core\DataLayer;
use FP\Resv\Core\Helpers;
use FP\Resv\Core\RateLimiter;
use FP\Resv\Core\Roles;
use InvalidArgumentException;
use RuntimeException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function __;
use function absint;
use function add_action;
use function apply_filters;
use function current_user_can;
use function is_string;
use function register_rest_route;
use function rest_ensure_response;
use function sanitize_text_field;
use function wp_verify_nonce;

final class REST
{
    public function __construct(private readonly Service $service)
    {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
    }

    public function registerRoutes(): void
    {
        register_rest_route(
            'fp-resv/v1',
            '/events',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handleListEvents'],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/events/(?P<id>\d+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handleGetEvent'],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/events/(?P<id>\d+)/tickets',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handleBookEvent'],
                'permission_callback' => '__return_true',
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/events/(?P<id>\d+)/tickets',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handleListTickets'],
                'permission_callback' => static fn (): bool => current_user_can(Roles::MANAGE_RESERVATIONS),
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/events/(?P<id>\d+)/tickets/export',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handleExportTickets'],
                'permission_callback' => static fn (): bool => current_user_can(Roles::MANAGE_RESERVATIONS),
            ]
        );
    }

    public function handleListEvents(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $limit = absint($request->get_param('limit'));
        if ($limit <= 0) {
            $limit = 10;
        }

        $events = $this->service->listUpcoming($limit);

        return rest_ensure_response([
            'events' => $events,
        ]);
    }

    public function handleGetEvent(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $eventId = absint($request->get_param('id'));
        if ($eventId <= 0) {
            return new WP_Error('fp_resv_invalid_event', __('Evento non valido.', 'fp-restaurant-reservations'), ['status' => 400]);
        }

        $event = $this->service->getEvent($eventId);
        if ($event === null) {
            return new WP_Error('fp_resv_event_not_found', __('Evento non trovato.', 'fp-restaurant-reservations'), ['status' => 404]);
        }

        return rest_ensure_response(['event' => $event]);
    }

    public function handleBookEvent(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        // Cerca il nonce in ordine: body JSON params, body params, poi header
        $jsonParams = $request->get_json_params();
        $nonce = null;
        
        // Prima prova a leggere dal body JSON
        if (is_array($jsonParams) && isset($jsonParams['fp_resv_nonce'])) {
            $nonce = $jsonParams['fp_resv_nonce'];
        }
        
        // Poi dai parametri normali
        if (!is_string($nonce) || $nonce === '') {
            $nonce = $request->get_param('fp_resv_nonce');
        }
        if (!is_string($nonce) || $nonce === '') {
            $nonce = $request->get_param('_wpnonce');
        }
        
        // Solo come ultimo fallback usa l'header
        if (!is_string($nonce)) {
            $nonce = $request->get_header('X-WP-Nonce');
        }

        if (!is_string($nonce) || !wp_verify_nonce($nonce, 'fp_resv_events')) {
            return new WP_Error('fp_resv_invalid_nonce', __('Verifica di sicurezza non superata.', 'fp-restaurant-reservations'), ['status' => 403]);
        }

        $ip = Helpers::clientIp();
        if (!RateLimiter::allow('event:' . $ip, 5, 300)) {
            return new WP_Error('fp_resv_events_rate_limited', __('Hai effettuato troppe richieste, riprova più tardi.', 'fp-restaurant-reservations'), ['status' => 429]);
        }

        $eventId = absint($request->get_param('id'));
        if ($eventId <= 0) {
            return new WP_Error('fp_resv_invalid_event', __('Evento non valido.', 'fp-restaurant-reservations'), ['status' => 400]);
        }

        $payload = [
            'first_name'   => $this->param($request, 'first_name'),
            'last_name'    => $this->param($request, 'last_name'),
            'email'        => $this->param($request, 'email'),
            'phone'        => $this->param($request, 'phone'),
            'notes'        => $this->param($request, 'notes'),
            'quantity'     => absint($request->get_param('quantity') ?? 1),
            'category'     => $this->param($request, 'category'),
            'language'     => $this->param($request, 'language') ?: 'it',
            'locale'       => $this->param($request, 'locale') ?: 'it_IT',
            'location'     => $this->param($request, 'location') ?: 'event',
            'currency'     => $this->param($request, 'currency') ?: 'EUR',
            'utm_source'   => $this->param($request, 'utm_source'),
            'utm_medium'   => $this->param($request, 'utm_medium'),
            'utm_campaign' => $this->param($request, 'utm_campaign'),
        ];

        try {
            $result = $this->service->bookEvent($eventId, $payload);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('fp_resv_invalid_event', $exception->getMessage(), ['status' => 404]);
        } catch (RuntimeException $exception) {
            return new WP_Error('fp_resv_event_booking_failed', $exception->getMessage(), ['status' => 400]);
        } catch (\Throwable $exception) {
            return new WP_Error(
                'fp_resv_event_error',
                __('Si è verificato un errore durante la prenotazione dell\'evento.', 'fp-restaurant-reservations'),
                [
                    'status'  => 500,
                    'details' => apply_filters('fp_resv_debug_error', null, $exception),
                ]
            );
        }

        $tracking = DataLayer::consume();
        if ($tracking !== []) {
            $result['tracking'] = $tracking;
        }

        $response = rest_ensure_response($result);
        if ($response instanceof WP_REST_Response) {
            $response->set_status(201);
        }

        return $response;
    }

    public function handleListTickets(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $eventId = absint($request->get_param('id'));
        if ($eventId <= 0) {
            return new WP_Error('fp_resv_invalid_event', __('Evento non valido.', 'fp-restaurant-reservations'), ['status' => 400]);
        }

        $tickets = $this->service->listTickets($eventId);

        return rest_ensure_response(['tickets' => $tickets]);
    }

    public function handleExportTickets(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $eventId = absint($request->get_param('id'));
        if ($eventId <= 0) {
            return new WP_Error('fp_resv_invalid_event', __('Evento non valido.', 'fp-restaurant-reservations'), ['status' => 400]);
        }

        $csv = $this->service->exportTicketsCsv($eventId);
        $response = new WP_REST_Response($csv);
        $response->set_headers([
            'Content-Type'        => 'text/csv; charset=utf-8',
            'Content-Disposition' => 'attachment; filename="fp-resv-event-' . $eventId . '-tickets.csv"',
        ]);

        return $response;
    }

    private function param(WP_REST_Request $request, string $key): string
    {
        $value = $request->get_param($key);
        if (!is_string($value)) {
            return '';
        }

        return sanitize_text_field($value);
    }
}
