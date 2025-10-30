<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Closures;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use RuntimeException;
use FP\Resv\Core\Security;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use WP_REST_Server;
use function add_action;
use function is_array;
use function rest_ensure_response;
use function sanitize_key;
use function sanitize_text_field;
use function wp_timezone;

final class REST
{
    public function __construct(private readonly Service $service)
    {
    }

    public function register(): void
    {
        add_action('rest_api_init', [$this, 'registerRoutes']);
        
        // Hook AGGRESSIVO per pulire output spurio e forzare JSON corretto
        add_filter('rest_post_dispatch', [$this, 'forceCleanJsonResponse'], 999, 3);
    }
    
    /**
     * Forza una risposta JSON pulita rimuovendo output spurio
     * Questo filter viene eseguito DOPO che la risposta è stata preparata
     */
    public function forceCleanJsonResponse($response, $server, $request)
    {
        // Solo per i nostri endpoint closures
        $route = $request->get_route();
        if (!is_string($route) || strpos($route, '/fp-resv/v1/closures') === false) {
            return $response;
        }
        
        error_log('[FP Closures REST] forceCleanJsonResponse attivato per route: ' . $route);
        
        // Pulisci TUTTI gli output buffer aperti
        while (ob_get_level() > 0) {
            $captured = ob_get_clean();
            if ($captured && $captured !== '') {
                error_log('[FP Closures REST] ⚠️ OUTPUT SPURIO CATTURATO E RIMOSSO: "' . $captured . '"');
            }
        }
        
        // Verifica che la risposta sia valida
        if (!$response instanceof \WP_REST_Response) {
            error_log('[FP Closures REST] Response non è WP_REST_Response: ' . gettype($response));
            return $response;
        }
        
        $data = $response->get_data();
        error_log('[FP Closures REST] Response data type: ' . gettype($data));
        error_log('[FP Closures REST] Response data: ' . json_encode($data));
        
        return $response;
    }

    public function registerRoutes(): void
    {
        error_log('[FP Closures REST] registerRoutes() chiamato!');
        
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
        
        error_log('[FP Closures REST] Endpoint /closures registrato: ' . ($result ? 'SUCCESS' : 'FAILED'));

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
        error_log('[FP Closures REST] handleList() chiamato');
        
        // Cattura eventuali output indesiderati da hook o snippet
        ob_start();
        
        $range = $this->resolveRange($request);

        $filters = [
            'range_start'      => $range['start'],
            'range_end'        => $range['end'],
            'include_inactive' => (bool) $request->get_param('include_inactive'),
        ];
        
        error_log('[FP Closures REST] Filters: ' . wp_json_encode($filters));

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

        error_log('[FP Closures REST] Chiamata service->list()');
        $items = $this->service->list($filters);
        error_log('[FP Closures REST] Items ricevuti: ' . count($items));

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
            'range' => [
                'start' => $range['start']->format(DateTimeInterface::ATOM),
                'end'   => $range['end']->format(DateTimeInterface::ATOM),
            ],
            'items' => $items,
        ];
        
        error_log('[FP Closures REST] Response preparata con ' . count($items) . ' items');
        error_log('[FP Closures REST] Response JSON: ' . wp_json_encode($response));
        
        // Cattura e scarta eventuali output indesiderati
        $captured = ob_get_clean();
        if ($captured !== '' && $captured !== false) {
            error_log('[FP Closures REST] ⚠️ OUTPUT SPURIO CATTURATO: "' . $captured . '"');
        }
        
        // Crea risposta esplicita con header forzati
        $rest_response = new WP_REST_Response($response, 200);
        $rest_response->set_headers([
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
        
        return $rest_response;
    }

    public function handleCreate(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        error_log('[FP Closures REST] handleCreate() chiamato');
        
        // Cattura eventuali output indesiderati
        ob_start();
        
        $payload = $this->collectPayload($request);
        error_log('[FP Closures REST] Payload: ' . wp_json_encode($payload));

        try {
            $model = $this->service->create($payload);
            error_log('[FP Closures REST] Chiusura creata con ID: ' . $model->id);
        } catch (InvalidArgumentException $exception) {
            ob_end_clean();
            error_log('[FP Closures REST] InvalidArgumentException: ' . $exception->getMessage());
            return new WP_Error('fp_resv_closure_invalid', $exception->getMessage(), ['status' => 400]);
        } catch (RuntimeException $exception) {
            ob_end_clean();
            error_log('[FP Closures REST] RuntimeException: ' . $exception->getMessage());
            return new WP_Error('fp_resv_closure_error', $exception->getMessage(), ['status' => 500]);
        }

        // Usa direttamente il model creato invece di richiamarlo
        $response = $this->exportModelToArray($model);
        
        error_log('[FP Closures REST] Response finale: ' . wp_json_encode($response));
        
        // Cattura e scarta eventuali output indesiderati
        $captured = ob_get_clean();
        if ($captured !== '' && $captured !== false) {
            error_log('[FP Closures REST CREATE] ⚠️ OUTPUT SPURIO CATTURATO: "' . $captured . '"');
        }

        // Crea risposta esplicita con header forzati
        $rest_response = new WP_REST_Response($response, 201);
        $rest_response->set_headers([
            'Content-Type' => 'application/json; charset=UTF-8',
        ]);
        
        return $rest_response;
    }

    public function handleUpdate(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        $id = (int) $request->get_param('id');
        $payload = $this->collectPayload($request);

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
        $range = $this->resolveRange($request);

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
        error_log('[FP Closures REST] permissionCallback() chiamato');
        $can = Security::currentUserCanManage();
        error_log('[FP Closures REST] permissionCallback result: ' . ($can ? 'TRUE' : 'FALSE'));
        return $can;
    }

    /**
     * @return array<string, DateTimeImmutable>
     */
    private function resolveRange(WP_REST_Request $request): array
    {
        $timezone = wp_timezone();
        $start    = $this->parseDateParam($request->get_param('start') ?? $request->get_param('from'), $timezone);
        $end      = $this->parseDateParam($request->get_param('end') ?? $request->get_param('to'), $timezone);

        $body = $request->get_json_params();
        if (is_array($body)) {
            if ($start === null && isset($body['start'])) {
                $start = $this->parseDateParam($body['start'], $timezone);
            }
            if ($end === null && isset($body['end'])) {
                $end = $this->parseDateParam($body['end'], $timezone);
            }
        }

        if ($start === null) {
            $start = new DateTimeImmutable('today', $timezone);
        }

        if ($end === null) {
            $end = $start->add(new DateInterval('P30D'));
        }

        return [
            'start' => $start,
            'end'   => $end,
        ];
    }

    private function parseDateParam(mixed $value, DateTimeZone $timezone): ?DateTimeImmutable
    {
        if ($value instanceof DateTimeImmutable) {
            return $value->setTimezone($timezone);
        }

        if (!is_string($value) || $value === '') {
            return null;
        }

        $value = sanitize_text_field($value);

        $date = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, $value, $timezone);
        if ($date instanceof DateTimeImmutable) {
            return $date;
        }

        try {
            return new DateTimeImmutable($value, $timezone);
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Converte un Model in array per la risposta REST
     * 
     * @return array<string, mixed>
     */
    private function exportModelToArray(Model $model): array
    {
        return [
            'id'                => $model->id,
            'scope'             => $model->scope,
            'type'              => $model->type,
            'start_at'          => $model->startAt->format(DateTimeInterface::ATOM),
            'end_at'            => $model->endAt->format(DateTimeInterface::ATOM),
            'room_id'           => $model->roomId,
            'table_id'          => $model->tableId,
            'note'              => $model->note,
            'priority'          => $model->priority,
            'capacity_override' => $model->capacityOverride,
            'active'            => $model->active,
            'recurrence'        => $model->recurrence,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function collectPayload(WP_REST_Request $request): array
    {
        $params = $request->get_json_params();
        if (!is_array($params)) {
            $params = $request->get_body_params();
        }

        if (!is_array($params)) {
            $params = [];
        }

        $payload = [
            'scope'              => $params['scope'] ?? $request->get_param('scope'),
            'type'               => $params['type'] ?? $request->get_param('type'),
            'start_at'           => $params['start_at'] ?? $request->get_param('start_at'),
            'end_at'             => $params['end_at'] ?? $request->get_param('end_at'),
            'room_id'            => $params['room_id'] ?? $request->get_param('room_id'),
            'table_id'           => $params['table_id'] ?? $request->get_param('table_id'),
            'note'               => $params['note'] ?? $request->get_param('note'),
            'active'             => $params['active'] ?? $request->get_param('active'),
            'capacity_percent'   => $params['capacity_percent'] ?? $request->get_param('capacity_percent'),
            'unassigned_capacity'=> $params['unassigned_capacity'] ?? $request->get_param('unassigned_capacity'),
            'label'              => $params['label'] ?? $request->get_param('label'),
            'special_hours'      => $params['special_hours'] ?? $request->get_param('special_hours'),
        ];

        if (isset($params['recurrence']) && is_array($params['recurrence'])) {
            $payload['recurrence'] = $params['recurrence'];
        } elseif (is_array($request->get_param('recurrence'))) {
            $payload['recurrence'] = $request->get_param('recurrence');
        }

        return $payload;
    }
}
