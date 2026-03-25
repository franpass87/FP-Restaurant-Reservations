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
use function is_array;
use function is_string;
use function rest_ensure_response;
use function sanitize_key;

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
        register_rest_route(
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
        // Cattura eventuali output indesiderati da hook o snippet
        $this->responseBuilder->startOutputCapture();
        
        $range = $this->rangeResolver->resolve($request);

        $filters = [
            'range_start'      => $range['start'],
            'range_end'        => $range['end'],
            'include_inactive' => (bool) $request->get_param('include_inactive'),
        ];

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

        $items = $this->service->list($filters);

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

        // Cattura e scarta eventuali output indesiderati
        $this->responseBuilder->captureAndCleanOutput();
        
        return $this->responseBuilder->createJsonResponse($response, 200);
    }

    public function handleCreate(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        // Cattura eventuali output indesiderati
        $this->responseBuilder->startOutputCapture();
        
        $payload = $this->payloadCollector->collect($request);

        try {
            $model = $this->service->create($payload);
        } catch (InvalidArgumentException $exception) {
            $this->responseBuilder->captureAndCleanOutput();
            return new WP_Error('fp_resv_closure_invalid', $exception->getMessage(), ['status' => 400]);
        } catch (RuntimeException $exception) {
            $this->responseBuilder->captureAndCleanOutput();
            return new WP_Error('fp_resv_closure_error', $exception->getMessage(), ['status' => 500]);
        }

        // Usa direttamente il model creato invece di richiamarlo
        $response = $this->modelExporter->export($model);

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
        $can = Security::currentUserCanManage();

        // Permetti accesso anche a admin WordPress standard
        if (current_user_can('manage_options')) {
            return true;
        }

        return $can;
    }

}
