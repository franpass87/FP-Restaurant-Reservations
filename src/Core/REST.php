<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use DateTimeImmutable;
use DateTimeZone;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function __;
use function add_action;
use function current_user_can;
use function esc_html;
use function esc_html__;
use function get_bloginfo;
use function is_email;
use function is_string;
use function register_rest_route;
use function rest_ensure_response;
use function sanitize_email;
use function sanitize_text_field;
use function sprintf;

final class REST
{
    public static function init(): void
    {
        add_action('rest_api_init', [self::class, 'registerRoutes']);
    }

    public static function registerRoutes(): void
    {
        register_rest_route(
            'fp-resv/v1',
            '/email-test',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [self::class, 'handleEmailTest'],
                'permission_callback' => static fn (): bool => current_user_can('manage_options'),
                'args'                => [
                    'email' => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                    'note' => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                ],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/privacy/export',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [self::class, 'handlePrivacyExport'],
                'permission_callback' => static fn (): bool => current_user_can('manage_options'),
                'args'                => [
                    'email' => [
                        'type'     => 'string',
                        'required' => true,
                    ],
                ],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/privacy/delete',
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [self::class, 'handlePrivacyDelete'],
                'permission_callback' => static fn (): bool => current_user_can('manage_options'),
                'args'                => [
                    'email' => [
                        'type'     => 'string',
                        'required' => true,
                    ],
                ],
            ]
        );
    }

    public static function handleEmailTest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $email = $request->get_param('email');
        $email = is_string($email) ? sanitize_email($email) : '';
        if ($email === '' || !is_email($email)) {
            $email = sanitize_email((string) get_bloginfo('admin_email'));
        }

        $note = $request->get_param('note');
        $note = is_string($note) ? sanitize_text_field($note) : '';

        $mailer = ServiceContainer::getInstance()->get(Mailer::class);
        if (!$mailer instanceof Mailer) {
            return new WP_Error('fp_resv_mailer_unavailable', __('Mailer non disponibile.', 'fp-restaurant-reservations'), [
                'status' => 500,
            ]);
        }

        $timezone = new DateTimeZone('Europe/Rome');
        $start    = new DateTimeImmutable('now', $timezone);
        $end      = $start->modify('+90 minutes');

        $ics = ICS::generate([
            'start'      => $start,
            'end'        => $end,
            'timezone'   => $timezone->getName(),
            'summary'    => sprintf(__('Test prenotazione %s', 'fp-restaurant-reservations'), get_bloginfo('name')),
            'description'=> __('Evento di test generato dal plugin FP Restaurant Reservations.', 'fp-restaurant-reservations'),
            'location'   => get_bloginfo('name'),
            'organizer'  => 'MAILTO:' . $email,
        ]);

        $subject = sprintf(__('Test notifiche FP Reservations - %s', 'fp-restaurant-reservations'), get_bloginfo('name'));
        $message = '<p>' . sprintf(
            /* translators: %s admin email */
            __('Questa è una email di test inviata alle impostazioni notifica (%s).', 'fp-restaurant-reservations'),
            esc_html($email)
        ) . '</p>';

        if ($note !== '') {
            $message .= '<p><strong>' . esc_html__('Nota:', 'fp-restaurant-reservations') . '</strong> ' . esc_html($note) . '</p>';
        }

        $sent = $mailer->send(
            $email,
            $subject,
            $message,
            [],
            [],
            [
                'channel'       => 'email_test',
                'content_type'  => 'text/html',
                'ics_content'   => $ics,
                'ics_filename'  => 'fp-reservation-test.ics',
                'reservation_id'=> 0,
            ]
        );

        if (!$sent) {
            return new WP_Error(
                'fp_resv_mail_failed',
                __('Invio email non riuscito. Verifica le impostazioni e riprova.', 'fp-restaurant-reservations'),
                ['status' => 500]
            );
        }

        return rest_ensure_response([
            'sent_to' => $email,
            'note'    => $note,
            'message' => __('Email di test inviata con successo.', 'fp-restaurant-reservations'),
        ]);
    }

    public static function handlePrivacyExport(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $email = sanitize_email((string) $request->get_param('email'));
        if ($email === '' || !is_email($email)) {
            return new WP_Error(
                'fp_resv_invalid_email',
                __('Indirizzo email non valido.', 'fp-restaurant-reservations'),
                ['status' => 400]
            );
        }

        $privacy = ServiceContainer::getInstance()->get(Privacy::class);
        if (!$privacy instanceof Privacy) {
            return new WP_Error(
                'fp_resv_privacy_unavailable',
                __('Modulo privacy non disponibile.', 'fp-restaurant-reservations'),
                ['status' => 500]
            );
        }

        $export = $privacy->exportByEmail($email);
        if ($export === [] || (
            ($export['customer'] ?? null) === null
            && ($export['reservations'] ?? []) === []
            && ($export['surveys'] ?? []) === []
        )) {
            return new WP_Error(
                'fp_resv_privacy_not_found',
                __('Nessun dato trovato per l\'indirizzo fornito.', 'fp-restaurant-reservations'),
                ['status' => 404]
            );
        }

        $export['email'] = $email;

        return rest_ensure_response($export);
    }

    public static function handlePrivacyDelete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $email = sanitize_email((string) $request->get_param('email'));
        if ($email === '' || !is_email($email)) {
            return new WP_Error(
                'fp_resv_invalid_email',
                __('Indirizzo email non valido.', 'fp-restaurant-reservations'),
                ['status' => 400]
            );
        }

        $privacy = ServiceContainer::getInstance()->get(Privacy::class);
        if (!$privacy instanceof Privacy) {
            return new WP_Error(
                'fp_resv_privacy_unavailable',
                __('Modulo privacy non disponibile.', 'fp-restaurant-reservations'),
                ['status' => 500]
            );
        }

        $result = $privacy->anonymizeByEmail($email);
        if (($result['customer'] ?? 0) === 0 && ($result['reservations'] ?? 0) === 0 && ($result['surveys'] ?? 0) === 0) {
            return new WP_Error(
                'fp_resv_privacy_not_found',
                __('Nessun dato trovato per l\'indirizzo fornito.', 'fp-restaurant-reservations'),
                ['status' => 404]
            );
        }

        $result['email']   = $email;
        $result['message'] = __('Dati anonimizzati correttamente.', 'fp-restaurant-reservations');

        return rest_ensure_response($result);
    }
}
