<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Diagnostics;

use FP\Resv\Core\Plugin;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function __;
use function add_action;
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
            '/diagnostics/logs',
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
            '/diagnostics/export',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handleExport'],
                'permission_callback' => [$this, 'checkPermissions'],
                'args'                => [
                    'channel' => [
                        'type'     => 'string',
                        'required' => true,
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
                    'per_page' => [
                        'type'     => 'integer',
                        'required' => false,
                    ],
                ],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/diagnostics/email-preview/(?P<id>\d+)',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handleEmailPreview'],
                'permission_callback' => [$this, 'checkPermissions'],
                'args'                => [
                    'id' => [
                        'type'     => 'integer',
                        'required' => true,
                    ],
                ],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/diagnostics/refresh-cache',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handleRefreshCache'],
                'permission_callback' => [$this, 'checkPermissions'],
            ]
        );
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
        $channel = sanitize_text_field((string) $request->get_param('channel'));
        $args    = [
            'status'   => sanitize_text_field((string) $request->get_param('status')),
            'from'     => sanitize_text_field((string) $request->get_param('from')),
            'to'       => sanitize_text_field((string) $request->get_param('to')),
            'search'   => sanitize_text_field((string) $request->get_param('search')),
            'per_page' => (int) $request->get_param('per_page'),
        ];

        $export = $this->service->export($channel, $args);

        return rest_ensure_response([
            'filename'  => $export['filename'],
            'mime_type' => $export['mime_type'],
            'format'    => $export['format'],
            'delimiter' => $export['delimiter'],
            'encoding'  => 'base64',
            'content'   => base64_encode($export['content']),
        ]);
    }

    public function handleEmailPreview(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');
        if ($id <= 0) {
            return new WP_Error(
                'fp_resv_invalid_preview_id',
                __('Anteprima non valida.', 'fp-restaurant-reservations'),
                ['status' => 400]
            );
        }

        $preview = $this->service->getEmailPreview($id);
        if ($preview === null) {
            return new WP_Error(
                'fp_resv_preview_not_found',
                __('Nessuna email trovata per questa anteprima.', 'fp-restaurant-reservations'),
                ['status' => 404]
            );
        }

        return rest_ensure_response($preview);
    }

    public function handleRefreshCache(WP_REST_Request $request): WP_REST_Response
    {
        Plugin::forceRefreshAssets();
        
        return rest_ensure_response([
            'success' => true,
            'message' => __('Cache aggiornata con successo. Gli asset verranno ricaricati al prossimo refresh della pagina.', 'fp-restaurant-reservations'),
            'version' => Plugin::assetVersion(),
        ]);
    }

    public function checkPermissions(): bool|WP_Error
    {
        if (!current_user_can('manage_options')) {
            return new WP_Error(
                'fp_resv_forbidden',
                __('Non hai i permessi per visualizzare questi log.', 'fp-restaurant-reservations'),
                ['status' => 403]
            );
        }

        return true;
    }
}
