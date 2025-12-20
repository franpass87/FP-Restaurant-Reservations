<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Closures;

use WP_REST_Request;
use function is_array;

/**
 * Raccoglie e normalizza il payload dalle richieste REST.
 * Estratto da REST per migliorare la manutenibilità.
 */
final class ClosuresPayloadCollector
{
    /**
     * Raccoglie il payload dalla richiesta.
     *
     * @return array<string, mixed>
     */
    public function collect(WP_REST_Request $request): array
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















