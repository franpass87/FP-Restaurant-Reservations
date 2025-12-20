<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\Admin;

use DateInterval;
use DateTimeImmutable;
use FP\Resv\Domain\Reservations\Admin\AgendaHandler;
use FP\Resv\Domain\Reservations\Admin\StatsHandler;
use FP\Resv\Domain\Reservations\Repository;
use Throwable;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;
use function array_map;
use function error_log;
use function is_array;
use function rest_ensure_response;
use function sprintf;
use function wp_timezone;
use function __;

/**
 * Gestisce l'endpoint REST per l'overview dashboard.
 * Estratto da AdminREST per migliorare la manutenibilità.
 */
final class OverviewHandler
{
    public function __construct(
        private readonly Repository $reservations,
        private readonly AgendaHandler $agendaHandler,
        private readonly StatsHandler $statsHandler
    ) {
    }

    /**
     * Gestisce la richiesta di overview.
     */
    public function handle(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $timezone = wp_timezone();
            $today = new DateTimeImmutable('today', $timezone);
        
            // Oggi
            $todayRows = $this->reservations->findAgendaRange(
                $today->format('Y-m-d'),
                $today->format('Y-m-d')
            );
        
            // Questa settimana
            $weekStart = $today->modify('-' . ((int)$today->format('N') - 1) . ' days');
            $weekEnd = $weekStart->add(new DateInterval('P6D'));
            $weekRows = $this->reservations->findAgendaRange(
                $weekStart->format('Y-m-d'),
                $weekEnd->format('Y-m-d')
            );
        
            // Questo mese
            $monthStart = $today->modify('first day of this month');
            $monthEnd = $today->modify('last day of this month');
            $monthRows = $this->reservations->findAgendaRange(
                $monthStart->format('Y-m-d'),
                $monthEnd->format('Y-m-d')
            );

            // Mappa prenotazioni
            $todayReservations = array_map([$this->agendaHandler, 'mapAgendaReservation'], is_array($todayRows) ? $todayRows : []);
            $weekReservations = array_map([$this->agendaHandler, 'mapAgendaReservation'], is_array($weekRows) ? $weekRows : []);
            $monthReservations = array_map([$this->agendaHandler, 'mapAgendaReservation'], is_array($monthRows) ? $monthRows : []);

            $responseData = [
                'today' => [
                    'date' => $today->format('Y-m-d'),
                    'stats' => $this->statsHandler->calculateStats($todayReservations),
                ],
                'week' => [
                    'start' => $weekStart->format('Y-m-d'),
                    'end' => $weekEnd->format('Y-m-d'),
                    'stats' => $this->statsHandler->calculateStats($weekReservations),
                ],
                'month' => [
                    'start' => $monthStart->format('Y-m-d'),
                    'end' => $monthEnd->format('Y-m-d'),
                    'stats' => $this->statsHandler->calculateStats($monthReservations),
                ],
                'trends' => $this->statsHandler->calculateTrends($todayReservations, $weekReservations, $monthReservations),
            ];
            
            return rest_ensure_response($responseData);
        } catch (Throwable $e) {
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log('[FP Resv Overview] Errore critico: ' . $e->getMessage());
            }
            return new WP_Error(
                'fp_resv_overview_error',
                sprintf(__('Errore nel caricamento della panoramica: %s', 'fp-restaurant-reservations'), $e->getMessage()),
                ['status' => 500]
            );
        }
    }
}


