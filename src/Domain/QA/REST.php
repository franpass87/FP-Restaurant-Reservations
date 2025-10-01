<?php

declare(strict_types=1);

namespace FP\Resv\Domain\QA;

use FP\Resv\Core\Helpers;
use FP\Resv\Core\Security;
use FP\Resv\Core\RateLimiter;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function __;
use function absint;
use function add_action;
use function apply_filters;
use function is_array;
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
        $rateConfig = $this->resolveRateLimit($request);
        $limitResult = RateLimiter::check('qa_seed:' . $ip, $rateConfig['limit'], $rateConfig['seconds']);
        if (!$limitResult['allowed']) {
            $retryAfter = $limitResult['retry_after'] > 0 ? $limitResult['retry_after'] : $rateConfig['seconds'];

            $response = new WP_REST_Response(
                [
                    'code'    => 'fp_resv_rate_limited',
                    'message' => __('Hai effettuato troppe richieste di seed. Riprova tra qualche minuto.', 'fp-restaurant-reservations'),
                    'data'    => [
                        'status'      => 429,
                        'retry_after' => $retryAfter,
                    ],
                ],
                429
            );

            $response->set_headers([
                'Retry-After'   => (string) max(1, $retryAfter),
                'Cache-Control' => 'no-store, no-cache, must-revalidate, max-age=0',
            ]);

            return $response;
        }

        $summary = $this->seeder->seed($days > 0 ? $days : 14, $dryRun);

        return rest_ensure_response([
            'seeded'  => !$dryRun,
            'summary' => $summary,
        ]);
    }

    public function checkPermissions(): bool|WP_Error
    {
        if (!Security::currentUserCanManage()) {
            return new WP_Error(
                'fp_resv_forbidden',
                __('Non hai i permessi per eseguire il seed QA.', 'fp-restaurant-reservations'),
                ['status' => 403]
            );
        }

        return true;
    }

    /**
     * @return array{limit:int, seconds:int}
     */
    private function resolveRateLimit(WP_REST_Request $request): array
    {
        $config = apply_filters('fp_resv_rate_limit_qa_seed', [
            'limit'   => 3,
            'seconds' => 300,
        ], $request);

        $limit   = 3;
        $seconds = 300;

        if (is_array($config)) {
            if (isset($config['limit'])) {
                $limit = (int) $config['limit'];
            }

            if (isset($config['seconds'])) {
                $seconds = (int) $config['seconds'];
            }
        }

        if ($limit < 1) {
            $limit = 3;
        }

        if ($seconds < 1) {
            $seconds = 300;
        }

        return [
            'limit'   => $limit,
            'seconds' => $seconds,
        ];
    }
}
