<?php

declare(strict_types=1);

namespace FP\Resv\Domain\QA;

use FP\Resv\Core\Helpers;
use FP\Resv\Core\RateLimiter;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function __;
use function absint;
use function add_action;
use function current_user_can;
use function register_rest_route;
use function rest_ensure_response;
use function rest_sanitize_boolean;

final class REST
{
    public function __construct(private readonly Seeder $seeder)
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
            '/qa/seed',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handleSeed'],
                'permission_callback' => [$this, 'checkPermissions'],
                'args'                => [
                    'days' => [
                        'type'     => 'integer',
                        'required' => false,
                    ],
                    'dry_run' => [
                        'type'     => 'boolean',
                        'required' => false,
                    ],
                ],
            ]
        );
    }

    public function handleSeed(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $days   = absint((int) $request->get_param('days'));
        $dryRun = rest_sanitize_boolean($request->get_param('dry_run'));

        $ip = Helpers::clientIp();
        if (!RateLimiter::allow('qa_seed:' . $ip, 3, 300)) {
            return new WP_Error(
                'fp_resv_rate_limited',
                __('Hai effettuato troppe richieste di seed. Riprova tra qualche minuto.', 'fp-restaurant-reservations'),
                ['status' => 429]
            );
        }

        $summary = $this->seeder->seed($days > 0 ? $days : 14, $dryRun);

        return rest_ensure_response([
            'seeded'  => !$dryRun,
            'summary' => $summary,
        ]);
    }

    public function checkPermissions(): bool|WP_Error
    {
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'fp_resv_forbidden',
                __('Non hai i permessi per eseguire il seed QA.', 'fp-restaurant-reservations'),
                ['status' => 403]
            );
        }

        return true;
    }
}
