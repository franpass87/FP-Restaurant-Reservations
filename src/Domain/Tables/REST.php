<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Tables;

use InvalidArgumentException;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function add_action;
use function count;
use function current_user_can;
use function is_array;
use function register_rest_route;
use function rest_ensure_response;
use function sanitize_text_field;
use function wp_unslash;

final class REST
{
    public function __construct(private readonly LayoutService $layout)
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
            '/tables/overview',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handleOverview'],
                'permission_callback' => [$this, 'canManage'],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/tables/rooms',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handleCreateRoom'],
                'permission_callback' => [$this, 'canManage'],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/tables/rooms/(?P<id>\d+)',
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'handleUpdateRoom'],
                'permission_callback' => [$this, 'canManage'],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/tables/rooms/(?P<id>\d+)',
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'handleDeleteRoom'],
                'permission_callback' => [$this, 'canManage'],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/tables',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handleCreateTable'],
                'permission_callback' => [$this, 'canManage'],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/tables/(?P<id>\d+)',
            [
                'methods'             => WP_REST_Server::EDITABLE,
                'callback'            => [$this, 'handleUpdateTable'],
                'permission_callback' => [$this, 'canManage'],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/tables/(?P<id>\d+)',
            [
                'methods'             => WP_REST_Server::DELETABLE,
                'callback'            => [$this, 'handleDeleteTable'],
                'permission_callback' => [$this, 'canManage'],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/tables/positions',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handlePositions'],
                'permission_callback' => [$this, 'canManage'],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/tables/merge',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handleMerge'],
                'permission_callback' => [$this, 'canManage'],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/tables/split',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handleSplit'],
                'permission_callback' => [$this, 'canManage'],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/tables/suggest',
            [
                'methods'             => WP_REST_Server::READABLE,
                'callback'            => [$this, 'handleSuggest'],
                'permission_callback' => [$this, 'canManage'],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/tables/bulk',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handleBulkCreateTables'],
                'permission_callback' => [$this, 'canManage'],
            ]
        );
    }

    public function handleOverview(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return $this->wrapOperation(function (): array {
            return $this->layout->getOverview();
        });
    }

    public function handleCreateRoom(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return $this->wrapOperation(function () use ($request): array {
            $data = $request->get_json_params();
            if (!is_array($data) || $data === []) {
                // Fallback: accetta anche form-data / query params quando il body JSON viene rimosso dall'hosting
                $data = $request->get_params();
            }
            $data = $this->preparePayload($data);
            return $this->layout->saveRoom($data);
        });
    }

    public function handleUpdateRoom(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $roomId = (int) $request->get_param('id');

        return $this->wrapOperation(function () use ($request, $roomId): array {
            $data = $request->get_json_params();
            if (!is_array($data) || $data === []) {
                $data = $request->get_params();
            }
            $data = $this->preparePayload($data);
            return $this->layout->saveRoom($data, $roomId);
        });
    }

    public function handleDeleteRoom(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $roomId = (int) $request->get_param('id');

        return $this->wrapOperation(function () use ($roomId): array {
            $this->layout->deleteRoom($roomId);

            return ['deleted' => true];
        });
    }

    public function handleCreateTable(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return $this->wrapOperation(function () use ($request): array {
            $data = $request->get_json_params();
            if (!is_array($data) || $data === []) {
                $data = $request->get_params();
            }
            $data = $this->preparePayload($data);
            return $this->layout->saveTable($data);
        });
    }

    public function handleUpdateTable(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $tableId = (int) $request->get_param('id');

        return $this->wrapOperation(function () use ($request, $tableId): array {
            $data = $request->get_json_params();
            if (!is_array($data) || $data === []) {
                $data = $request->get_params();
            }
            $data = $this->preparePayload($data);
            return $this->layout->saveTable($data, $tableId);
        });
    }

    public function handleDeleteTable(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $tableId = (int) $request->get_param('id');

        return $this->wrapOperation(function () use ($tableId): array {
            $this->layout->deleteTable($tableId);

            return ['deleted' => true];
        });
    }

    public function handlePositions(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return $this->wrapOperation(function () use ($request): array {
            $params = $request->get_json_params();
            $positions = [];
            if (is_array($params) && isset($params['positions']) && is_array($params['positions'])) {
                foreach ($params['positions'] as $entry) {
                    if (!is_array($entry)) {
                        continue;
                    }

                    $positions[] = [
                        'id' => (int) ($entry['id'] ?? 0),
                        'x'  => isset($entry['x']) ? (float) $entry['x'] : 0.0,
                        'y'  => isset($entry['y']) ? (float) $entry['y'] : 0.0,
                    ];
                }
            }

            $this->layout->updatePositions($positions);

            return ['updated' => count($positions)];
        });
    }

    public function handleMerge(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return $this->wrapOperation(function () use ($request): array {
            $params = $request->get_json_params();
            $ids = [];
            if (is_array($params) && isset($params['table_ids']) && is_array($params['table_ids'])) {
                foreach ($params['table_ids'] as $id) {
                    $ids[] = (int) $id;
                }
            }

            $code = null;
            if (is_array($params) && isset($params['code'])) {
                $code = sanitize_text_field((string) $params['code']);
            }

            return $this->layout->mergeTables($ids, $code);
        });
    }

    public function handleSplit(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return $this->wrapOperation(function () use ($request): array {
            $params = $request->get_json_params();
            $ids = [];
            if (is_array($params) && isset($params['table_ids']) && is_array($params['table_ids'])) {
                foreach ($params['table_ids'] as $id) {
                    $ids[] = (int) $id;
                }
            }

            $this->layout->splitTables($ids);

            return ['split' => count($ids)];
        });
    }

    public function handleSuggest(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return $this->wrapOperation(function () use ($request): array {
            $criteria = [
                'party'               => (int) $request->get_param('party'),
                'room_id'             => $request->get_param('room_id') !== null ? (int) $request->get_param('room_id') : null,
                'include_inactive'    => (bool) $request->get_param('include_inactive'),
                'include_unavailable' => (bool) $request->get_param('include_unavailable'),
                'max_tables'          => $request->get_param('max_tables') !== null ? (int) $request->get_param('max_tables') : null,
            ];

            return $this->layout->suggest($criteria);
        });
    }

    public function handleBulkCreateTables(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        return $this->wrapOperation(function () use ($request): array {
            $data = $request->get_json_params();
            if (!is_array($data) || $data === []) {
                $data = $request->get_params();
            }
            $data = $this->preparePayload($data);
            $result = $this->layout->createTablesBulk($data);
            $created = isset($result['created']) && is_array($result['created']) ? $result['created'] : $result;
            $skipped = isset($result['skipped']) && is_array($result['skipped']) ? $result['skipped'] : [];
            return [
                'created' => $created,
                'count'   => count($created),
                'skipped' => array_values($skipped),
            ];
        });
    }

    private function canManage(): bool
    {
        return current_user_can('manage_options');
    }

    /**
     * @param callable():array<string, mixed> $operation
     */
    private function wrapOperation(callable $operation): WP_REST_Response|WP_Error
    {
        try {
            $result = $operation();
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('fp_resv_tables_invalid', $exception->getMessage(), ['status' => 400]);
        } catch (\Throwable $exception) {
            return new WP_Error('fp_resv_tables_error', $exception->getMessage(), ['status' => 500]);
        }

        return rest_ensure_response($result);
    }

    /**
     * @param mixed $params
     * @return array<string, mixed>
     */
    private function preparePayload(mixed $params): array
    {
        if (!is_array($params)) {
            return [];
        }

        $payload = [];
        foreach ($params as $key => $value) {
            $payload[$key] = is_string($value) ? sanitize_text_field(wp_unslash($value)) : $value;
        }

        return $payload;
    }
}
