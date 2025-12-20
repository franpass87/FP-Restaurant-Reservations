<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations\Admin;

use DateTimeImmutable;
use function array_sum;
use function count;
use function round;
use function substr;
use function wp_timezone;

/**
 * Gestisce il calcolo delle statistiche.
 * Estratto da AdminREST.php per migliorare modularità.
 */
final class StatsHandler
{
    /**
     * Calcola statistiche base
     */
    public function calculateStats(array $reservations): array
    {
        $totalReservations = count($reservations);
        $totalGuests = 0;
        $statusCounts = [
            'pending' => 0,
            'confirmed' => 0,
            'visited' => 0,
            'no_show' => 0,
            'cancelled' => 0,
        ];
        
        foreach ($reservations as $resv) {
            $totalGuests += (int)($resv['party'] ?? 0);
            $status = (string)($resv['status'] ?? 'pending');
            if (isset($statusCounts[$status])) {
                $statusCounts[$status]++;
            }
        }
        
        return [
            'total_reservations' => $totalReservations,
            'total_guests' => $totalGuests,
            'by_status' => $statusCounts,
            'confirmed_percentage' => $totalReservations > 0 
                ? round(($statusCounts['confirmed'] / $totalReservations) * 100, 1) 
                : 0,
        ];
    }

    /**
     * Calcola statistiche dettagliate con breakdown temporale
     */
    public function calculateDetailedStats(array $reservations, string $rangeMode): array
    {
        $baseStats = $this->calculateStats($reservations);
        
        // Raggruppa per servizio (pranzo/cena basato su orario)
        $byService = [
            'lunch' => ['count' => 0, 'guests' => 0],
            'dinner' => ['count' => 0, 'guests' => 0],
            'other' => ['count' => 0, 'guests' => 0],
        ];
        
        // Raggruppa per giorno della settimana (solo per week/month)
        $byDayOfWeek = [];
        if ($rangeMode !== 'day') {
            $dayNames = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
            foreach ($dayNames as $day) {
                $byDayOfWeek[$day] = ['count' => 0, 'guests' => 0];
            }
        }
        
        // Media coperti per prenotazione
        $partySizes = [];
        
        foreach ($reservations as $resv) {
            $time = isset($resv['time']) ? substr((string)$resv['time'], 0, 5) : '00:00';
            $hour = (int)substr($time, 0, 2);
            $party = (int)($resv['party'] ?? 0);
            
            // Servizio
            if ($hour >= 12 && $hour < 17) {
                $byService['lunch']['count']++;
                $byService['lunch']['guests'] += $party;
            } elseif ($hour >= 19 && $hour < 24) {
                $byService['dinner']['count']++;
                $byService['dinner']['guests'] += $party;
            } else {
                $byService['other']['count']++;
                $byService['other']['guests'] += $party;
            }
            
            // Giorno settimana
            if ($rangeMode !== 'day' && isset($resv['date'])) {
                $date = new DateTimeImmutable($resv['date'], wp_timezone());
                $dayName = $date->format('l');
                if (isset($byDayOfWeek[$dayName])) {
                    $byDayOfWeek[$dayName]['count']++;
                    $byDayOfWeek[$dayName]['guests'] += $party;
                }
            }
            
            // Party sizes
            $partySizes[] = $party;
        }
        
        $result = array_merge($baseStats, [
            'by_service' => $byService,
            'average_party_size' => count($partySizes) > 0 
                ? round(array_sum($partySizes) / count($partySizes), 1) 
                : 0,
            'median_party_size' => count($partySizes) > 0 
                ? $this->calculateMedian($partySizes) 
                : 0,
        ]);
        
        if ($rangeMode !== 'day') {
            $result['by_day_of_week'] = $byDayOfWeek;
        }
        
        return $result;
    }

    /**
     * Calcola trend confrontando periodi diversi
     */
    public function calculateTrends(array $todayReservations, array $weekReservations, array $monthReservations): array
    {
        $todayCount = count($todayReservations);
        $weekCount = count($weekReservations);
        $monthCount = count($monthReservations);
        
        $weekAverage = $weekCount > 0 ? round($weekCount / 7, 1) : 0;
        $monthAverage = $monthCount > 0 ? round($monthCount / 30, 1) : 0;
        
        // Trend oggi vs media settimanale
        $dailyTrend = 'stable';
        if ($weekAverage > 0) {
            $difference = (($todayCount - $weekAverage) / $weekAverage) * 100;
            if ($difference > 10) {
                $dailyTrend = 'up';
            } elseif ($difference < -10) {
                $dailyTrend = 'down';
            }
        }
        
        // Trend settimanale vs mensile
        $weeklyTrend = 'stable';
        if ($monthAverage > 0) {
            $difference = (($weekAverage - $monthAverage) / $monthAverage) * 100;
            if ($difference > 10) {
                $weeklyTrend = 'up';
            } elseif ($difference < -10) {
                $weeklyTrend = 'down';
            }
        }
        
        return [
            'daily' => [
                'trend' => $dailyTrend,
                'count' => $todayCount,
                'average' => $weekAverage,
                'difference_percent' => $weekAverage > 0 
                    ? round((($todayCount - $weekAverage) / $weekAverage) * 100, 1) 
                    : 0,
            ],
            'weekly' => [
                'trend' => $weeklyTrend,
                'average' => $weekAverage,
                'month_average' => $monthAverage,
                'difference_percent' => $monthAverage > 0 
                    ? round((($weekAverage - $monthAverage) / $monthAverage) * 100, 1) 
                    : 0,
            ],
        ];
    }

    /**
     * Calcola la mediana di un array di numeri
     */
    public function calculateMedian(array $numbers): float
    {
        if (empty($numbers)) {
            return 0.0;
        }
        
        sort($numbers);
        $count = count($numbers);
        $middle = (int)floor($count / 2);
        
        if ($count % 2 === 0) {
            return (float)(($numbers[$middle - 1] + $numbers[$middle]) / 2);
        }
        
        return (float)$numbers[$middle];
    }
}
















