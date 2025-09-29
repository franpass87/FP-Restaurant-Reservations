<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reports;

use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function __;
use function add_action;
use function base64_encode;
use function current_user_can;
use function register_rest_route;
use function rest_ensure_response;
use function sanitize_text_field;

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
            '/reports/daily',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handleDailySummary'],
                'permission_callback' => [$this, 'checkPermissions'],
                'args'                => [
                    'start' => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                    'end' => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                ],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/reports/logs',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handleLogs'],
                'permission_callback' => [$this, 'checkPermissions'],
                'args'                => [
                    'channel' => [
                        'type'     => 'string',
                        'required' => true,
                    ],
                    'page' => [
                        'type'     => 'integer',
                        'required' => false,
                    ],
                    'per_page' => [
                        'type'     => 'integer',
                        'required' => false,
                    ],
                    'status' => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                    'from' => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                    'to' => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                    'search' => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                ],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/reports/export',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handleExport'],
                'permission_callback' => [$this, 'checkPermissions'],
                'args'                => [
                    'from' => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                    'to' => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                    'status' => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                    'format' => [
                        'type'     => 'string',
                        'required' => false,
                    ],
                ],
            ]
        );
    }

    public function handleDailySummary(WP_REST_Request $request): WP_REST_Response
    {
        $start = sanitize_text_field((string) $request->get_param('start'));
        $end   = $request->get_param('end');
        $end   = $end !== null ? sanitize_text_field((string) $end) : null;

        $data = $this->service->getDailySummary($start, $end);

        return rest_ensure_response([
            'summary' => $data,
        ]);
    }

    public function handleLogs(WP_REST_Request $request): WP_REST_Response
    {
        $channel = sanitize_text_field((string) $request->get_param('channel'));
        $args    = [
            'page'     => (int) $request->get_param('page'),
            'per_page' => (int) $request->get_param('per_page'),
            'status'   => sanitize_text_field((string) $request->get_param('status')),
            'from'     => sanitize_text_field((string) $request->get_param('from')),
            'to'       => sanitize_text_field((string) $request->get_param('to')),
            'search'   => sanitize_text_field((string) $request->get_param('search')),
        ];

        return rest_ensure_response($this->service->getLogs($channel, $args));
    }

    public function handleExport(WP_REST_Request $request): WP_REST_Response
    {
        $params = [
            'from'   => sanitize_text_field((string) $request->get_param('from')),
            'to'     => sanitize_text_field((string) $request->get_param('to')),
            'status' => sanitize_text_field((string) $request->get_param('status')),
            'format' => sanitize_text_field((string) $request->get_param('format')),
        ];

        $export = $this->service->exportReservations($params);

        return rest_ensure_response([
            'filename'  => $export['filename'],
            'mime_type' => $export['mime_type'],
            'format'    => $export['format'],
            'delimiter' => $export['delimiter'],
            'encoding'  => 'base64',
            'content'   => base64_encode($export['content']),
        ]);
    }

    public function checkPermissions(): bool|WP_Error
    {
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'fp_resv_forbidden',
                __('Non hai i permessi per visualizzare questi report.', 'fp-restaurant-reservations'),
                ['status' => 403]
            );
        }

        return true;
    }
}
