<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Closures;

use DateTimeInterface;
use InvalidArgumentException;
use RuntimeException;
use FP\Resv\Core\Security;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function add_action;
use function add_filter;
use function current_user_can;
use function get_current_user_id;
use function is_array;
use function is_string;
use function is_user_logged_in;
use function rest_ensure_response;
use function sanitize_key;
use function wp_json_encode;

final class REST
{
    public function __construct(
        private readonly Service $service,
        private readonly ClosuresDateRangeResolver $rangeResolver,
        private readonly ClosuresPayloadCollector $payloadCollector,
        private readonly ClosuresModelExporter $modelExporter,
        private readonly ClosuresResponseBuilder $responseBuilder
    ) {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        
        // Hook AGGRESSIVO per pulire output spurio e forzare JSON corretto
        add_filter('rest_post_dispatch', [$this->responseBuilder, 'forceCleanJsonResponse'], 999, 3);
    }
    

    public function registerRoutes(): void
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP Closures REST] registerRoutes() chiamato!');
        }
        
        $result = register_rest_route(
            'fp-resv/v1',
            '/closures',
            [
                [
                    'methods'             => WP_REST_Server::READABLE,
                    'callback'            => [$this, 'handleList'],
                    'permission_callback' => [$this, 'permissionCallback'],
                ],
                [
                    'methods'             => WP_REST_Server::CREATABLE,
                    'callback'            => [$this, 'handleCreate'],
                    'permission_callback' => [$this, 'permissionCallback'],
                ],
            ]
        );
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP Closures REST] Endpoint /closures registrato: ' . ($result ? 'SUCCESS' : 'FAILED'));
        }

        register_rest_route(
            'fp-resv/v1',
            '/closures/(?P<id>\d+)',
            [
                [
                    'methods'             => WP_REST_Server::EDITABLE,
                    'callback'            => [$this, 'handleUpdate'],
                    'permission_callback' => [$this, 'permissionCallback'],
                ],
                [
                    'methods'             => WP_REST_Server::DELETABLE,
                    'callback'            => [$this, 'handleDelete'],
                    'permission_callback' => [$this, 'permissionCallback'],
                ],
            ]
        );

        register_rest_route(
            'fp-resv/v1',
            '/closures/preview',
            [
                'methods'             => WP_REST_Server::CREATABLE,
                'callback'            => [$this, 'handlePreview'],
                'permission_callback' => [$this, 'permissionCallback'],
            ]
        );
    }

    public function handleList(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP Closures REST] handleList() chiamato');
        }
        
        // Cattura eventuali output indesiderati da hook o snippet
        $this->responseBuilder->startOutputCapture();
        
        $range = $this->rangeResolver->resolve($request);

        $filters = [
            'range_start'      => $range['start'],
            'range_end'        => $range['end'],
            'include_inactive' => (bool) $request->get_param('include_inactive'),
        ];
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP Closures REST] Filters: ' . wp_json_encode($filters));
        }

        $scope = $request->get_param('scope');
        if (is_string($scope) && sanitize_key($scope) !== '') {
            $filters['scope'] = $scope;
        }

        $roomId = $request->get_param('room_id');
        if ($roomId !== null) {
            $filters['room_id'] = (int) $roomId;
        }

        $tableId = $request->get_param('table_id');
        if ($tableId !== null) {
            $filters['table_id'] = (int) $tableId;
        }

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP Closures REST] Chiamata service->list()');
        }
        $items = $this->service->list($filters);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP Closures REST] Items ricevuti: ' . count($items));
        }

        $expand = $request->get_param('expand');
        if ($expand === 'occurrences') {
            $preview = $this->service->preview($range['start'], $range['end'], $filters);
            $index   = [];
            foreach ($preview['events'] as $event) {
                $index[$event['id']][] = [
                    'start' => $event['start'],
                    'end'   => $event['end'],
                    'type'  => $event['type'],
                ];
            }

            foreach ($items as &$item) {
                $item['occurrences'] = $index[$item['id']] ?? [];
            }
            unset($item);
        }

        $response = [
            'range' => $this->responseBuilder->formatRange($range),
            'items' => $items,
        ];
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP Closures REST] Response preparata con ' . count($items) . ' items');
            error_log('[FP Closures REST] Response JSON: ' . wp_json_encode($response));
        }
        
        // Cattura e scarta eventuali output indesiderati
        $this->responseBuilder->captureAndCleanOutput();
        
        return $this->responseBuilder->createJsonResponse($response, 200);
    }

    public function handleCreate(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP Closures REST] handleCreate() chiamato');
        }
        
        // Cattura eventuali output indesiderati
        $this->responseBuilder->startOutputCapture();
        
        $payload = $this->payloadCollector->collect($request);
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP Closures REST] Payload: ' . wp_json_encode($payload));
        }

        try {
            $model = $this->service->create($payload);
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Closures REST] Chiusura creata con ID: ' . $model->id);
            }
        } catch (InvalidArgumentException $exception) {
            $this->responseBuilder->captureAndCleanOutput();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Closures REST] InvalidArgumentException: ' . $exception->getMessage());
            }
            return new WP_Error('fp_resv_closure_invalid', $exception->getMessage(), ['status' => 400]);
        } catch (RuntimeException $exception) {
            $this->responseBuilder->captureAndCleanOutput();
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Closures REST] RuntimeException: ' . $exception->getMessage());
            }
            return new WP_Error('fp_resv_closure_error', $exception->getMessage(), ['status' => 500]);
        }

        // Usa direttamente il model creato invece di richiamarlo
        $response = $this->modelExporter->export($model);
        
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP Closures REST] Response finale: ' . wp_json_encode($response));
        }
        
        // Cattura e scarta eventuali output indesiderati
        $this->responseBuilder->captureAndCleanOutput();

        return $this->responseBuilder->createJsonResponse($response, 201);
    }

    public function handleUpdate(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');
        $payload = $this->payloadCollector->collect($request);

        try {
            $model = $this->service->update($id, $payload);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('fp_resv_closure_invalid', $exception->getMessage(), ['status' => 400]);
        } catch (RuntimeException $exception) {
            return new WP_Error('fp_resv_closure_error', $exception->getMessage(), ['status' => 500]);
        }

        $result = $this->service->list([
            'id'                => $model->id,
            'include_inactive'  => true,
        ]);

        return rest_ensure_response($result[0] ?? []);
    }

    public function handleDelete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');

        try {
            $this->service->deactivate($id);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('fp_resv_closure_invalid', $exception->getMessage(), ['status' => 404]);
        } catch (RuntimeException $exception) {
            return new WP_Error('fp_resv_closure_error', $exception->getMessage(), ['status' => 500]);
        }

        return new WP_REST_Response(null, 204);
    }

    public function handlePreview(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $range = $this->rangeResolver->resolve($request);

        $filters = [];
        $scope = $request->get_param('scope');
        if (is_string($scope) && sanitize_key($scope) !== '') {
            $filters['scope'] = $scope;
        }

        $roomId = $request->get_param('room_id');
        if ($roomId !== null) {
            $filters['room_id'] = (int) $roomId;
        }

        $tableId = $request->get_param('table_id');
        if ($tableId !== null) {
            $filters['table_id'] = (int) $tableId;
        }

        try {
            $preview = $this->service->preview($range['start'], $range['end'], $filters);
        } catch (InvalidArgumentException $exception) {
            return new WP_Error('fp_resv_closure_invalid', $exception->getMessage(), ['status' => 400]);
        }

        return rest_ensure_response($preview);
    }

    private function permissionCallback(): bool
    {
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP Closures REST] permissionCallback() chiamato');
        }
        $can = Security::currentUserCanManage();
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log('[FP Closures REST] permissionCallback result: ' . ($can ? 'TRUE' : 'FALSE'));
            error_log('[FP Closures REST] User ID: ' . get_current_user_id());
            error_log('[FP Closures REST] Is user logged in: ' . (is_user_logged_in() ? 'YES' : 'NO'));
        }
        
        // TEMPORANEO: Permetti accesso anche a admin WordPress standard
        if (current_user_can('manage_options')) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Closures REST] Permesso via manage_options (admin)');
            }
            return true;
        }
        
        return $can;
    }

}
