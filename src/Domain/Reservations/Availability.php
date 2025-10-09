<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use FP\Resv\Core\Logging;
use FP\Resv\Core\Metrics;
use FP\Resv\Domain\Settings\MealPlan;
use FP\Resv\Domain\Settings\Options;
use InvalidArgumentException;
use function __;
use function array_map;
use function array_slice;
use function array_sum;
use function explode;
use function floor;
use function in_array;
use function is_array;
use function json_decode;
use function max;
use function microtime;
use function min;
use function preg_match;
use function preg_split;
use function sanitize_key;
use function sprintf;
use function str_contains;
use function strtolower;
use function trim;
use function wp_cache_get;
use function wp_cache_set;
use const ARRAY_A;
use wpdb;

class Availability
{
    private const DEFAULT_TIMEZONE = 'Europe/Rome';

    private const DEFAULT_SCHEDULE = [
        'mon' => ['19:00-23:00'],
        'tue' => ['19:00-23:00'],
        'wed' => ['19:00-23:00'],
        'thu' => ['19:00-23:00'],
        'fri' => ['19:00-23:30'],
        'sat' => ['12:30-15:00', '19:00-23:30'],
        'sun' => ['12:30-15:00'],
    ];

    /** @var string[] */
    private const ACTIVE_STATUSES = ['pending', 'confirmed', 'seated'];

    /**
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $mealPlanCache = null;

    public function __construct(private readonly Options $options, private readonly wpdb $wpdb)
    {
    }

    /**
     * Find slots for a date range (optimized batch loading).
     * 
     * @param array<string, mixed> $criteria
     * @param DateTimeImmutable $from
     * @param DateTimeImmutable $to
     * @return array<string, array<string, mixed>>
     */
    public function findSlotsForDateRange(array $criteria, DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $stopTimer = Metrics::timer('availability.calculation_batch', [
            'party' => $criteria['party'] ?? 0,
            'days' => $from->diff($to)->days + 1,
        ]);

        $party = isset($criteria['party']) ? (int) $criteria['party'] : 0;
        $roomId = isset($criteria['room']) ? (int) $criteria['room'] : null;

        if ($roomId <= 0) {
            $roomId = null;
        }

        $timezone = $this->resolveTimezone();
        
        // Load data once for entire range
        $rooms = $this->loadRooms($roomId);
        $tables = $this->loadTables($roomId);
        $closures = $this->loadClosures($from, $to->setTime(23, 59, 59), $timezone);
        
        $mealKey = isset($criteria['meal']) ? sanitize_key((string) $criteria['meal']) : '';
        $mealSettings = $this->resolveMealSettings($mealKey);
        $turnoverMinutes = $mealSettings['turnover'];
        $bufferMinutes = $mealSettings['buffer'];

        $results = [];
        $current = $from;

        while ($current <= $to) {
            $dayStart = new DateTimeImmutable($current->format('Y-m-d') . ' 00:00:00', $timezone);
            $dayEnd = $dayStart->setTime(23, 59, 59);

            // Load reservations for this specific day
            $reservations = $this->loadReservations($dayStart, $dayEnd, $roomId, $turnoverMinutes, $bufferMinutes, $timezone);

            // Calculate slots using preloaded data
            $slots = $this->calculateSlotsForDay(
                $dayStart,
                array_merge($criteria, ['date' => $dayStart->format('Y-m-d')]),
                $rooms,
                $tables,
                $closures,
                $reservations,
                $mealSettings
            );

            $results[$dayStart->format('Y-m-d')] = $slots;
            $current = $current->add(new DateInterval('P1D'));
        }

        Metrics::gauge('availability.batch_days_processed', count($results));
        $stopTimer();

        return $results;
    }

    /**
     * Calculate slots for a single day using preloaded data.
     * 
     * @param array<string, mixed> $criteria
     * @param array<int, array{id:int, capacity:int}> $rooms
     * @param array<int, array{id:int, room_id:int, capacity:int, seats_min:int, seats_max:int, join_group:string|null}> $tables
     * @param array<int, array<string, mixed>> $closures
     * @param array<int, array{id:int, party:int, table_id:int|null, room_id:int|null, window_start:DateTimeImmutable, window_end:DateTimeImmutable}> $reservations
     * @param array<string, mixed> $mealSettings
     * @return array<string, mixed>
     */
    private function calculateSlotsForDay(
        DateTimeImmutable $dayStart,
        array $criteria,
        array $rooms,
        array $tables,
        array $closures,
        array $reservations,
        array $mealSettings
    ): array {
        $party = (int) ($criteria['party'] ?? 0);
        $roomId = isset($criteria['room']) ? (int) $criteria['room'] : null;

        if ($roomId <= 0) {
            $roomId = null;
        }

        $timezone = $dayStart->getTimezone();
        $schedule = $this->resolveScheduleForDay($dayStart, $mealSettings['schedule']);

        if ($schedule === []) {
            $dayKey = strtolower($dayStart->format('D'));
            
            // Log diagnostico per capire perché non ci sono slot
            Logging::log('availability', 'Nessuna disponibilità - schedule vuoto', [
                'date' => $dayStart->format('Y-m-d'),
                'meal_key' => $criteria['meal'] ?? 'none',
                'meal_settings_schedule' => $mealSettings['schedule'],
                'day_key' => $dayKey,
            ]);
            
            return [
                'date' => $dayStart->format('Y-m-d'),
                'timezone' => $timezone->getName(),
                'criteria' => $this->normalizeCriteria($party, $roomId, $criteria),
                'slots' => [],
                'meta' => [
                    'has_availability' => false,
                    'reason' => __('Nessun turno configurato per la data selezionata.', 'fp-restaurant-reservations'),
                    'debug' => [
                        'day_key' => $dayKey,
                        'meal_key' => $criteria['meal'] ?? '',
                        'schedule_map_keys' => array_keys($mealSettings['schedule']),
                        'schedule_empty' => $mealSettings['schedule'] === [],
                        'message' => 'Lo schedule per il giorno ' . $dayKey . ' è vuoto. Giorni configurati: ' . implode(', ', array_keys($mealSettings['schedule'])),
                    ],
                ],
            ];
        }

        $slotInterval = $mealSettings['slot_interval'];
        $turnoverMinutes = $mealSettings['turnover'];
        $maxParallel = $mealSettings['max_parallel'];
        $waitlistEnabled = $this->options->getField('fp_resv_general', 'enable_waitlist', '0') === '1';
        $mergeStrategy = (string) $this->options->getField(
            'fp_resv_general',
            'merge_strategy',
            $this->options->getField('fp_resv_rooms', 'merge_strategy', 'smart')
        );
        $defaultRoomCap = max(
            1,
            (int) $this->options->getField(
                'fp_resv_general',
                'default_room_capacity',
                $this->options->getField('fp_resv_rooms', 'default_room_capacity', '40')
            )
        );

        $roomCapacities = $this->aggregateRoomCapacities($rooms, $tables, $defaultRoomCap);
        $slots = [];

        foreach ($schedule as $window) {
            $startMinute = $window['start'];
            $endMinute = $window['end'];

            for ($minute = $startMinute; $minute + $turnoverMinutes <= $endMinute; $minute += $slotInterval) {
                $slotStart = $dayStart->add(new DateInterval('PT' . $minute . 'M'));
                $slotEnd = $slotStart->add(new DateInterval('PT' . $turnoverMinutes . 'M'));

                $closureEffect = $this->evaluateClosures($closures, $slotStart, $slotEnd, $roomId);
                if ($closureEffect['status'] === 'blocked') {
                    $slots[] = $this->buildSlotPayload(
                        $slotStart,
                        $slotEnd,
                        'blocked',
                        0,
                        $party,
                        $waitlistEnabled,
                        $closureEffect['reasons'],
                        []
                    );
                    continue;
                }

                $availableTables = $this->filterAvailableTables($tables, $closureEffect['blocked_tables']);
                $hasPhysicalTables = $availableTables !== [];
                $overlapping = $this->filterOverlappingReservations($reservations, $slotStart, $slotEnd);
                $parallelCount = count($overlapping);
                $unassignedCapacity = 0;

                foreach ($overlapping as $reservation) {
                    if ($reservation['table_id'] !== null) {
                        unset($availableTables[$reservation['table_id']]);
                    } else {
                        $unassignedCapacity += $reservation['party'];
                    }
                }

                if ($parallelCount >= $maxParallel) {
                    $reasons = $closureEffect['reasons'];
                    $reasons[] = __('Limite di prenotazioni parallele raggiunto per lo slot selezionato.', 'fp-restaurant-reservations');

                    $slots[] = $this->buildSlotPayload(
                        $slotStart,
                        $slotEnd,
                        'full',
                        0,
                        $party,
                        $waitlistEnabled,
                        $reasons,
                        []
                    );
                    continue;
                }

                $baseCapacity = $this->resolveCapacityForScope($roomCapacities, $roomId, $hasPhysicalTables);
                $allowedCapacity = $this->applyCapacityReductions(
                    $baseCapacity,
                    $availableTables,
                    0,
                    $closureEffect['capacity_percent']
                );
                $capacity = $this->applyCapacityReductions(
                    $baseCapacity,
                    $availableTables,
                    $unassignedCapacity,
                    $closureEffect['capacity_percent']
                );

                if ($mealSettings['capacity_limit'] !== null) {
                    $allowedCapacity = min($allowedCapacity, $mealSettings['capacity_limit']);
                    $capacity = min($capacity, $mealSettings['capacity_limit']);
                }

                $status = $this->determineStatus($capacity, $allowedCapacity, $party);
                $reasons = $closureEffect['reasons'];

                if ($status === 'full' && $waitlistEnabled) {
                    $reasons[] = __('Disponibile solo lista di attesa per questo orario.', 'fp-restaurant-reservations');
                }

                $suggestions = $hasPhysicalTables
                    ? $this->suggestTables($availableTables, $party, $mergeStrategy)
                    : [];

                $slots[] = $this->buildSlotPayload(
                    $slotStart,
                    $slotEnd,
                    $status,
                    $capacity,
                    $party,
                    $waitlistEnabled,
                    $reasons,
                    $suggestions
                );
            }
        }

        return [
            'date' => $dayStart->format('Y-m-d'),
            'timezone' => $timezone->getName(),
            'criteria' => $this->normalizeCriteria($party, $roomId, $criteria),
            'slots' => $slots,
            'meta' => [
                'has_availability' => $this->hasAvailability($slots),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @return array<string, mixed>
     */
    public function findSlots(array $criteria): array
    {
        $stopTimer = Metrics::timer('availability.calculation', [
            'party' => $criteria['party'] ?? 0,
            'meal' => $criteria['meal'] ?? 'default',
        ]);

        $dateString = isset($criteria['date']) ? trim((string) $criteria['date']) : '';
        $party      = isset($criteria['party']) ? (int) $criteria['party'] : 0;
        $roomId     = isset($criteria['room']) ? (int) $criteria['room'] : null;

        if ($dateString === '' || !$this->isValidDate($dateString)) {
            throw new InvalidArgumentException(__('La data richiesta non è valida.', 'fp-restaurant-reservations'));
        }

        if ($party <= 0) {
            throw new InvalidArgumentException(__('Il numero di coperti deve essere maggiore di zero.', 'fp-restaurant-reservations'));
        }

        if ($roomId !== null && $roomId <= 0) {
            $roomId = null;
        }

        $timezone = $this->resolveTimezone();
        $dayStart = new DateTimeImmutable($dateString . ' 00:00:00', $timezone);
        $dayEnd   = $dayStart->setTime(23, 59, 59);

        $mealKey      = isset($criteria['meal']) ? sanitize_key((string) $criteria['meal']) : '';
        $mealSettings = $this->resolveMealSettings($mealKey);
        $schedule     = $this->resolveScheduleForDay($dayStart, $mealSettings['schedule']);
        if ($schedule === []) {
            $dayKey = strtolower($dayStart->format('D'));
            
            // Log diagnostico per capire perché non ci sono slot
            Logging::log('availability', 'Nessuna disponibilità - schedule vuoto', [
                'date' => $dayStart->format('Y-m-d'),
                'meal_key' => $mealKey,
                'meal_settings_schedule' => $mealSettings['schedule'],
                'day_key' => $dayKey,
            ]);
            
            return [
                'date'      => $dayStart->format('Y-m-d'),
                'timezone'  => $timezone->getName(),
                'criteria'  => $this->normalizeCriteria($party, $roomId, $criteria),
                'slots'     => [],
                'meta'      => [
                    'has_availability' => false,
                    'reason'           => __('Nessun turno configurato per la data selezionata.', 'fp-restaurant-reservations'),
                    'debug' => [
                        'day_key' => $dayKey,
                        'meal_key' => $mealKey,
                        'schedule_map_keys' => array_keys($mealSettings['schedule']),
                        'schedule_empty' => $mealSettings['schedule'] === [],
                        'message' => 'Lo schedule per il giorno ' . $dayKey . ' è vuoto. Giorni configurati: ' . implode(', ', array_keys($mealSettings['schedule'])),
                    ],
                ],
            ];
        }

        $slotInterval    = $mealSettings['slot_interval'];
        $turnoverMinutes = $mealSettings['turnover'];
        $bufferMinutes   = $mealSettings['buffer'];
        $maxParallel     = $mealSettings['max_parallel'];
        $waitlistEnabled   = $this->options->getField('fp_resv_general', 'enable_waitlist', '0') === '1';
        $mergeStrategy     = (string) $this->options->getField(
            'fp_resv_general',
            'merge_strategy',
            $this->options->getField('fp_resv_rooms', 'merge_strategy', 'smart')
        );
        $defaultRoomCap    = max(
            1,
            (int) $this->options->getField(
                'fp_resv_general',
                'default_room_capacity',
                $this->options->getField('fp_resv_rooms', 'default_room_capacity', '40')
            )
        );

        $rooms      = $this->loadRooms($roomId);
        $tables     = $this->loadTables($roomId);
        $closures   = $this->loadClosures($dayStart, $dayEnd, $timezone);
        $reservations = $this->loadReservations($dayStart, $dayEnd, $roomId, $turnoverMinutes, $bufferMinutes, $timezone);

        $roomCapacities = $this->aggregateRoomCapacities($rooms, $tables, $defaultRoomCap);
        $slots          = [];

        foreach ($schedule as $window) {
            $startMinute = $window['start'];
            $endMinute   = $window['end'];

            for ($minute = $startMinute; $minute + $turnoverMinutes <= $endMinute; $minute += $slotInterval) {
                $slotStart = $dayStart->add(new DateInterval('PT' . $minute . 'M'));
                $slotEnd   = $slotStart->add(new DateInterval('PT' . $turnoverMinutes . 'M'));

                $closureEffect = $this->evaluateClosures($closures, $slotStart, $slotEnd, $roomId);
                if ($closureEffect['status'] === 'blocked') {
                    $slots[] = $this->buildSlotPayload(
                        $slotStart,
                        $slotEnd,
                        'blocked',
                        0,
                        $party,
                        $waitlistEnabled,
                        $closureEffect['reasons'],
                        []
                    );
                    continue;
                }

                $availableTables    = $this->filterAvailableTables($tables, $closureEffect['blocked_tables']);
                $hasPhysicalTables  = $availableTables !== [];
                $overlapping        = $this->filterOverlappingReservations($reservations, $slotStart, $slotEnd);
                $parallelCount      = count($overlapping);
                $unassignedCapacity = 0;

                foreach ($overlapping as $reservation) {
                    if ($reservation['table_id'] !== null) {
                        unset($availableTables[$reservation['table_id']]);
                    } else {
                        $unassignedCapacity += $reservation['party'];
                    }
                }

                if ($parallelCount >= $maxParallel) {
                    $reasons = $closureEffect['reasons'];
                    $reasons[] = __('Limite di prenotazioni parallele raggiunto per lo slot selezionato.', 'fp-restaurant-reservations');

                    $slots[] = $this->buildSlotPayload(
                        $slotStart,
                        $slotEnd,
                        'full',
                        0,
                        $party,
                        $waitlistEnabled,
                        $reasons,
                        []
                    );
                    continue;
                }

                $baseCapacity = $this->resolveCapacityForScope($roomCapacities, $roomId, $hasPhysicalTables);
                $allowedCapacity = $this->applyCapacityReductions(
                    $baseCapacity,
                    $availableTables,
                    0,
                    $closureEffect['capacity_percent']
                );
                $capacity = $this->applyCapacityReductions(
                    $baseCapacity,
                    $availableTables,
                    $unassignedCapacity,
                    $closureEffect['capacity_percent']
                );

                if ($mealSettings['capacity_limit'] !== null) {
                    $allowedCapacity = min($allowedCapacity, $mealSettings['capacity_limit']);
                    $capacity        = min($capacity, $mealSettings['capacity_limit']);
                }

                $status  = $this->determineStatus($capacity, $allowedCapacity, $party);
                $reasons = $closureEffect['reasons'];

                if ($status === 'full' && $waitlistEnabled) {
                    $reasons[] = __('Disponibile solo lista di attesa per questo orario.', 'fp-restaurant-reservations');
                }

                $suggestions = $hasPhysicalTables
                    ? $this->suggestTables($availableTables, $party, $mergeStrategy)
                    : [];

                $slots[] = $this->buildSlotPayload(
                    $slotStart,
                    $slotEnd,
                    $status,
                    $capacity,
                    $party,
                    $waitlistEnabled,
                    $reasons,
                    $suggestions
                );
            }
        }

        $result = [
            'date'     => $dayStart->format('Y-m-d'),
            'timezone' => $timezone->getName(),
            'criteria' => $this->normalizeCriteria($party, $roomId, $criteria),
            'slots'    => $slots,
            'meta'     => [
                'has_availability' => $this->hasAvailability($slots),
            ],
        ];

        Metrics::gauge('availability.slots_found', count($slots), [
            'date' => $dateString,
            'party' => $party,
        ]);

        $stopTimer();

        return $result;
    }

    private function isValidDate(string $value): bool
    {
        if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $value)) {
            return false;
        }

        $dt = DateTimeImmutable::createFromFormat('Y-m-d', $value);

        return $dt instanceof DateTimeImmutable;
    }

    private function resolveTimezone(): DateTimeZone
    {
        $tz = (string) $this->options->getField('fp_resv_general', 'restaurant_timezone', self::DEFAULT_TIMEZONE);

        try {
            return new DateTimeZone($tz !== '' ? $tz : self::DEFAULT_TIMEZONE);
        } catch (
            \Exception $e
        ) {
            return new DateTimeZone(self::DEFAULT_TIMEZONE);
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function resolveMealSettings(string $mealKey): array
    {
        $defaultScheduleRaw = (string) $this->options->getField('fp_resv_general', 'service_hours_definition', '');
        $scheduleMap        = $this->parseScheduleDefinition($defaultScheduleRaw);
        
        // Valori di default: se i campi sono vuoti, usa i default invece di convertire a 0
        $slotIntervalRaw = $this->options->getField('fp_resv_general', 'slot_interval_minutes', '15');
        $slotInterval = ($slotIntervalRaw !== '' && $slotIntervalRaw !== null) ? max(5, (int) $slotIntervalRaw) : 15;
        
        $turnoverRaw = $this->options->getField('fp_resv_general', 'table_turnover_minutes', '120');
        $turnoverMinutes = ($turnoverRaw !== '' && $turnoverRaw !== null) ? max($slotInterval, (int) $turnoverRaw) : 120;
        
        $bufferRaw = $this->options->getField('fp_resv_general', 'buffer_before_minutes', '15');
        $bufferMinutes = ($bufferRaw !== '' && $bufferRaw !== null) ? max(0, (int) $bufferRaw) : 15;
        
        $maxParallelRaw = $this->options->getField('fp_resv_general', 'max_parallel_parties', '8');
        $maxParallel = ($maxParallelRaw !== '' && $maxParallelRaw !== null) ? max(1, (int) $maxParallelRaw) : 8;
        
        $capacityLimit      = null;

        // Log diagnostico sempre attivo
        Logging::log('availability', 'resolveMealSettings chiamato', [
            'meal_key' => $mealKey,
            'default_schedule_raw' => $defaultScheduleRaw,
            'default_schedule_map' => $scheduleMap,
            'slot_interval' => $slotInterval,
            'turnover_minutes' => $turnoverMinutes,
            'buffer_minutes' => $bufferMinutes,
            'max_parallel' => $maxParallel,
        ]);

        // Debug logging aggiuntivo se WP_DEBUG è abilitato
        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[FP-RESV] resolveMealSettings - mealKey: %s, default schedule raw: %s, default schedule map: %s',
                $mealKey,
                $defaultScheduleRaw,
                wp_json_encode($scheduleMap) ?: 'empty'
            ));
        }

        if ($mealKey !== '') {
            $plan = $this->getMealPlan();
            
            Logging::log('availability', 'Meal plan caricato', [
                'meal_key' => $mealKey,
                'plan_keys' => array_keys($plan),
                'meal_exists' => isset($plan[$mealKey]),
            ]);
            
            if (defined('WP_DEBUG') && WP_DEBUG) {
                error_log(sprintf(
                    '[FP-RESV] resolveMealSettings - meal plan: %s',
                    wp_json_encode($plan) ?: 'empty'
                ));
            }
            
            if (isset($plan[$mealKey])) {
                $meal = $plan[$mealKey];
                
                Logging::log('availability', 'Meal trovato', [
                    'meal_key' => $mealKey,
                    'has_hours_definition' => !empty($meal['hours_definition']),
                    'hours_definition' => $meal['hours_definition'] ?? null,
                ]);
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        '[FP-RESV] resolveMealSettings - selected meal: %s',
                        wp_json_encode($meal) ?: 'empty'
                    ));
                }
                
                if (!empty($meal['hours_definition'])) {
                    $mealSchedule = $this->parseScheduleDefinition((string) $meal['hours_definition']);
                    
                    Logging::log('availability', 'Schedule del meal parsato', [
                        'meal_key' => $mealKey,
                        'meal_schedule' => $mealSchedule,
                        'is_empty' => $mealSchedule === [],
                    ]);
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log(sprintf(
                            '[FP-RESV] resolveMealSettings - meal schedule: %s',
                            wp_json_encode($mealSchedule) ?: 'empty'
                        ));
                    }
                    if ($mealSchedule !== []) {
                        $scheduleMap = $mealSchedule;
                    }
                } else {
                    Logging::log('availability', 'WARNING: meal senza hours_definition', [
                        'meal_key' => $mealKey,
                        'using_default' => true,
                    ]);
                    
                    if (defined('WP_DEBUG') && WP_DEBUG) {
                        error_log('[FP-RESV] resolveMealSettings - WARNING: meal has no hours_definition, using default schedule');
                    }
                }

                if (!empty($meal['slot_interval'])) {
                    $slotInterval = max(5, (int) $meal['slot_interval']);
                }

                if (!empty($meal['turnover'])) {
                    $turnoverMinutes = max($slotInterval, (int) $meal['turnover']);
                }

                if (!empty($meal['buffer'])) {
                    $bufferMinutes = max(0, (int) $meal['buffer']);
                }

                if (!empty($meal['max_parallel'])) {
                    $maxParallel = max(1, (int) $meal['max_parallel']);
                }

                if (!empty($meal['capacity'])) {
                    $capacityLimit = max(1, (int) $meal['capacity']);
                }
            } else {
                Logging::log('availability', 'WARNING: meal_key non trovato nel plan', [
                    'meal_key' => $mealKey,
                    'available_keys' => array_keys($plan),
                ]);
                
                if (defined('WP_DEBUG') && WP_DEBUG) {
                    error_log(sprintf(
                        '[FP-RESV] resolveMealSettings - WARNING: meal key "%s" not found in meal plan',
                        $mealKey
                    ));
                }
            }
        }

        if ($turnoverMinutes < $slotInterval) {
            $turnoverMinutes = $slotInterval;
        }

        $result = [
            'schedule'       => $scheduleMap,
            'slot_interval'  => $slotInterval,
            'turnover'       => $turnoverMinutes,
            'buffer'         => $bufferMinutes,
            'max_parallel'   => $maxParallel,
            'capacity_limit' => $capacityLimit,
        ];

        Logging::log('availability', 'resolveMealSettings risultato finale', [
            'meal_key' => $mealKey,
            'schedule_days' => array_keys($scheduleMap),
            'schedule_empty' => $scheduleMap === [],
            'result' => $result,
        ]);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[FP-RESV] resolveMealSettings - final result: %s',
                wp_json_encode($result) ?: 'empty'
            ));
        }

        return $result;
    }

    /**
     * @param array<string, array<int, array{start:int,end:int}>> $scheduleMap
     *
     * @return array<int, array{start:int,end:int}>
     */
    private function resolveScheduleForDay(DateTimeImmutable $day, array $scheduleMap): array
    {
        $dayKey = strtolower($day->format('D'));
        $schedule = $scheduleMap[$dayKey] ?? [];

        Logging::log('availability', 'resolveScheduleForDay', [
            'date' => $day->format('Y-m-d'),
            'day_key' => $dayKey,
            'schedule_for_day' => $schedule,
            'schedule_is_empty' => $schedule === [],
            'available_days' => array_keys($scheduleMap),
        ]);

        if (defined('WP_DEBUG') && WP_DEBUG) {
            error_log(sprintf(
                '[FP-RESV] resolveScheduleForDay - date: %s, dayKey: %s, schedule: %s, full scheduleMap: %s',
                $day->format('Y-m-d'),
                $dayKey,
                wp_json_encode($schedule) ?: 'empty',
                wp_json_encode($scheduleMap) ?: 'empty'
            ));
        }

        return $schedule;
    }

    /**
     * @return array<int, array{start:int,end:int}>
     */
    private function parseScheduleDefinition(string $raw): array
    {
        $schedule = [];
        $lines    = $raw !== '' ? preg_split('/\n/', $raw) : false;

        if ($lines === false || $lines === []) {
            return $this->normalizeSchedule(self::DEFAULT_SCHEDULE);
        }

        foreach ($lines as $line) {
            $line = trim((string) $line);
            if ($line === '') {
                continue;
            }

            if (!str_contains($line, '=')) {
                continue;
            }

            [$day, $ranges] = array_map('trim', explode('=', $line, 2));
            $day            = strtolower($day);

            $segments = preg_split('/[|,]/', $ranges) ?: [];
            foreach ($segments as $segment) {
                $segment = trim($segment);
                if (!preg_match('/^(\d{2}):(\d{2})-(\d{2}):(\d{2})$/', $segment, $matches)) {
                    continue;
                }

                $start = ((int) $matches[1] * 60) + (int) $matches[2];
                $end   = ((int) $matches[3] * 60) + (int) $matches[4];
                if ($end <= $start) {
                    continue;
                }

                $schedule[$day][] = [
                    'start' => $start,
                    'end'   => $end,
                ];
            }
        }

        if ($schedule === []) {
            return $this->normalizeSchedule(self::DEFAULT_SCHEDULE);
        }

        return $schedule;
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private function getMealPlan(): array
    {
        if ($this->mealPlanCache !== null) {
            return $this->mealPlanCache;
        }

        $definition = (string) $this->options->getField('fp_resv_general', 'frontend_meals', '');
        $parsed     = MealPlan::parse($definition);
        $this->mealPlanCache = MealPlan::indexByKey($parsed);

        return $this->mealPlanCache;
    }

    /**
     * @param array<string, string[]> $definition
     *
     * @return array<string, array<int, array{start:int,end:int}>>
     */
    private function normalizeSchedule(array $definition): array
    {
        $normalized = [];
        foreach ($definition as $day => $segments) {
            foreach ($segments as $segment) {
                if (!preg_match('/^(\d{2}):(\d{2})-(\d{2}):(\d{2})$/', $segment, $matches)) {
                    continue;
                }

                $start = ((int) $matches[1] * 60) + (int) $matches[2];
                $end   = ((int) $matches[3] * 60) + (int) $matches[4];
                if ($end <= $start) {
                    continue;
                }

                $normalized[$day][] = [
                    'start' => $start,
                    'end'   => $end,
                ];
            }
        }

        return $normalized;
    }

    /**
     * @return array<int, array{id:int, capacity:int}>
     */
    private function loadRooms(?int $roomId): array
    {
        $cacheKey = 'fp_resv_rooms_' . ($roomId ?? 'all');
        $cached = wp_cache_get($cacheKey, 'fp_resv');

        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $table = $this->wpdb->prefix . 'fp_rooms';
        $where = 'active = 1';
        if ($roomId !== null) {
            $where .= $this->wpdb->prepare(' AND id = %d', $roomId);
        }

        $rows = $this->wpdb->get_results("SELECT id, capacity FROM {$table} WHERE {$where}", ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $rooms = [];
        foreach ($rows as $row) {
            $rooms[(int) $row['id']] = [
                'id'       => (int) $row['id'],
                'capacity' => max(0, (int) $row['capacity']),
            ];
        }

        wp_cache_set($cacheKey, $rooms, 'fp_resv', 300); // 5 minutes

        return $rooms;
    }

    /**
     * @return array<int, array{
     *     id:int,
     *     room_id:int,
     *     capacity:int,
     *     seats_min:int,
     *     seats_max:int,
     *     join_group:string|null
     * }>
     */
    private function loadTables(?int $roomId): array
    {
        $cacheKey = 'fp_resv_tables_' . ($roomId ?? 'all');
        $cached = wp_cache_get($cacheKey, 'fp_resv');

        if ($cached !== false && is_array($cached)) {
            return $cached;
        }

        $table = $this->wpdb->prefix . 'fp_tables';
        $where = 'active = 1';
        if ($roomId !== null) {
            $where .= $this->wpdb->prepare(' AND room_id = %d', $roomId);
        }

        $rows = $this->wpdb->get_results("SELECT id, room_id, seats_min, seats_std, seats_max, join_group FROM {$table} WHERE {$where}", ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $tables = [];
        foreach ($rows as $row) {
            $seatsMin = max(0, (int) ($row['seats_min'] ?? 0));
            $seatsStd = max($seatsMin, (int) ($row['seats_std'] ?? 0));
            $seatsMax = max($seatsStd, (int) ($row['seats_max'] ?? 0));
            $capacity = $seatsMax > 0 ? $seatsMax : ($seatsStd > 0 ? $seatsStd : $seatsMin);

            $tables[(int) $row['id']] = [
                'id'         => (int) $row['id'],
                'room_id'    => (int) $row['room_id'],
                'capacity'   => max(0, $capacity),
                'seats_min'  => $seatsMin > 0 ? $seatsMin : 1,
                'seats_max'  => $seatsMax > 0 ? $seatsMax : max(1, $capacity),
                'join_group' => $row['join_group'] !== null ? trim((string) $row['join_group']) : null,
            ];
        }

        wp_cache_set($cacheKey, $tables, 'fp_resv', 300); // 5 minutes

        return $tables;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function loadClosures(DateTimeImmutable $dayStart, DateTimeImmutable $dayEnd, DateTimeZone $timezone): array
    {
        $table = $this->wpdb->prefix . 'fp_closures';
        $sql   = $this->wpdb->prepare(
            "SELECT id, scope, room_id, table_id, type, start_at, end_at, recurrence_json, capacity_override_json FROM {$table} WHERE active = 1 AND ((start_at <= %s AND end_at >= %s) OR recurrence_json IS NOT NULL AND recurrence_json <> '')",
            $dayEnd->format('Y-m-d H:i:s'),
            $dayStart->format('Y-m-d H:i:s')
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $closures = [];
        foreach ($rows as $row) {
            $start = new DateTimeImmutable((string) $row['start_at'], $timezone);
            $end   = new DateTimeImmutable((string) $row['end_at'], $timezone);

            $recurrence = null;
            if (!empty($row['recurrence_json'])) {
                $decoded = json_decode((string) $row['recurrence_json'], true);
                $recurrence = is_array($decoded) ? $decoded : null;
            }

            $capacityOverride = null;
            if (!empty($row['capacity_override_json'])) {
                $decoded = json_decode((string) $row['capacity_override_json'], true);
                if (is_array($decoded)) {
                    $capacityOverride = $decoded;
                }
            }

            $closures[] = [
                'id'                 => (int) $row['id'],
                'scope'              => (string) $row['scope'],
                'room_id'            => $row['room_id'] !== null ? (int) $row['room_id'] : null,
                'table_id'           => $row['table_id'] !== null ? (int) $row['table_id'] : null,
                'type'               => (string) $row['type'],
                'start'              => $start,
                'end'                => $end,
                'recurrence'         => $recurrence,
                'capacity_override'  => $capacityOverride,
            ];
        }

        return $closures;
    }

    /**
     * @return array<int, array{
     *     id:int,
     *     party:int,
     *     table_id:int|null,
     *     room_id:int|null,
     *     window_start:DateTimeImmutable,
     *     window_end:DateTimeImmutable
     * }>
     */
    private function loadReservations(
        DateTimeImmutable $dayStart,
        DateTimeImmutable $dayEnd,
        ?int $roomId,
        int $turnoverMinutes,
        int $bufferMinutes,
        DateTimeZone $timezone
    ): array {
        $table    = $this->wpdb->prefix . 'fp_reservations';
        $statuses = "'" . implode("','", self::ACTIVE_STATUSES) . "'";
        $sql      = $this->wpdb->prepare(
            "SELECT id, party, room_id, table_id, time FROM {$table} WHERE date = %s AND status IN ({$statuses})",
            $dayStart->format('Y-m-d')
        );

        $rows = $this->wpdb->get_results($sql, ARRAY_A);
        if (!is_array($rows)) {
            return [];
        }

        $reservations = [];
        foreach ($rows as $row) {
            if ($roomId !== null && $row['room_id'] !== null && (int) $row['room_id'] !== $roomId) {
                continue;
            }

            $time       = (string) $row['time'];
            $start      = new DateTimeImmutable($dayStart->format('Y-m-d') . ' ' . $time, $timezone);
            $windowFrom = $start->sub(new DateInterval('PT' . $bufferMinutes . 'M'));
            $windowTo   = $start->add(new DateInterval('PT' . ($turnoverMinutes + $bufferMinutes) . 'M'));

            $reservations[] = [
                'id'           => (int) $row['id'],
                'party'        => max(1, (int) $row['party']),
                'table_id'     => $row['table_id'] !== null ? (int) $row['table_id'] : null,
                'room_id'      => $row['room_id'] !== null ? (int) $row['room_id'] : null,
                'window_start' => $windowFrom,
                'window_end'   => $windowTo,
            ];
        }

        return $reservations;
    }

    /**
     * @param array<int, array{id:int, capacity:int}> $rooms
     * @param array<int, array{id:int, room_id:int, capacity:int, seats_min:int, seats_max:int, join_group:string|null}> $tables
     *
     * @return array<int, array{capacity:int, table_capacity:int}>
     */
    private function aggregateRoomCapacities(array $rooms, array $tables, int $defaultRoomCap): array
    {
        $capacities = [];
        foreach ($rooms as $room) {
            $capacities[$room['id']] = [
                'capacity'       => max($room['capacity'], $defaultRoomCap),
                'table_capacity' => 0,
            ];
        }

        foreach ($tables as $table) {
            $roomId = $table['room_id'];
            if (!isset($capacities[$roomId])) {
                $capacities[$roomId] = [
                    'capacity'       => $defaultRoomCap,
                    'table_capacity' => 0,
                ];
            }

            $capacities[$roomId]['table_capacity'] += $table['capacity'];
            $capacities[$roomId]['capacity'] = max(
                $capacities[$roomId]['capacity'],
                $capacities[$roomId]['table_capacity']
            );
        }

        return $capacities;
    }

    /**
     * @param array<int, array{id:int, room_id:int, capacity:int, seats_min:int, seats_max:int, join_group:string|null}> $tables
     *
     * @return array<int, array{id:int, room_id:int, capacity:int, seats_min:int, seats_max:int, join_group:string|null}>
     */
    private function filterAvailableTables(array $tables, array $blockedTables): array
    {
        if ($blockedTables === []) {
            return $tables;
        }

        foreach ($blockedTables as $tableId) {
            unset($tables[$tableId]);
        }

        return $tables;
    }

    /**
     * @param array<int, array{id:int, party:int, table_id:int|null, room_id:int|null, window_start:DateTimeImmutable, window_end:DateTimeImmutable}> $reservations
     *
     * @return array<int, array{id:int, party:int, table_id:int|null, room_id:int|null, window_start:DateTimeImmutable, window_end:DateTimeImmutable}>
     */
    private function filterOverlappingReservations(array $reservations, DateTimeImmutable $slotStart, DateTimeImmutable $slotEnd): array
    {
        $overlapping = [];
        foreach ($reservations as $reservation) {
            if ($reservation['window_start'] < $slotEnd && $reservation['window_end'] > $slotStart) {
                $overlapping[] = $reservation;
            }
        }

        return $overlapping;
    }

    /**
     * @param array<int, array{capacity:int, table_capacity:int}> $roomCapacities
     */
    private function resolveCapacityForScope(array $roomCapacities, ?int $roomId, bool $hasTables): int
    {
        if ($roomId !== null) {
            $capacity = $roomCapacities[$roomId] ?? ['capacity' => 0, 'table_capacity' => 0];

            return $hasTables ? max($capacity['table_capacity'], 0) : max($capacity['capacity'], 0);
        }

        $total = 0;
        foreach ($roomCapacities as $capacity) {
            $total += $hasTables ? $capacity['table_capacity'] : $capacity['capacity'];
        }

        return $total;
    }

    /**
     * @param array<int, array{id:int, room_id:int, capacity:int, seats_min:int, seats_max:int, join_group:string|null}> $tables
     */
    private function applyCapacityReductions(int $baseCapacity, array $tables, int $unassignedCapacity, int $capacityPercent): int
    {
        $tableCapacity = array_sum(array_map(static fn (array $table): int => $table['capacity'], $tables));
        $capacity      = $tables === [] ? $baseCapacity : $tableCapacity;

        $capacity = max(0, $capacity - $unassignedCapacity);

        if ($capacityPercent < 100) {
            $capacity = (int) floor($capacity * ($capacityPercent / 100));
        }

        return max(0, $capacity);
    }

    private function determineStatus(int $capacity, int $allowedCapacity, int $party): string
    {
        if ($capacity <= 0 || $capacity < $party) {
            return 'full';
        }

        $normalizedAllowed = max($allowedCapacity, 0);
        if ($normalizedAllowed === 0) {
            return 'available';
        }

        $ratio = max(0, min(1, $capacity / $normalizedAllowed));
        if ($ratio <= 0.25) {
            return 'limited';
        }

        return 'available';
    }

    /**
     * @param array<int, array{id:int, room_id:int, capacity:int, seats_min:int, seats_max:int, join_group:string|null}> $availableTables
     *
     * @return array<int, array{tables:int[], seats:int, type:string}>
     */
    private function suggestTables(array $availableTables, int $party, string $mergeStrategy): array
    {
        $suggestions = [];

        foreach ($availableTables as $table) {
            if ($party >= $table['seats_min'] && $party <= $table['seats_max']) {
                $suggestions[] = [
                    'tables' => [$table['id']],
                    'seats'  => $table['seats_max'],
                    'type'   => 'single',
                ];
            }
        }

        if ($suggestions !== []) {
            return array_slice($this->sortSuggestions($suggestions), 0, 3);
        }

        if ($mergeStrategy !== 'smart') {
            return [];
        }

        $groups = [];
        foreach ($availableTables as $table) {
            $groupKey = $table['join_group'] ?: 'room_' . $table['room_id'];
            $groups[$groupKey][] = $table;
        }

        foreach ($groups as $tablesGroup) {
            $sorted = $this->sortTablesByCapacity($tablesGroup);
            $set    = [];
            $total  = 0;
            foreach ($sorted as $table) {
                $set[] = $table;
                $total += $table['seats_max'];
                if ($total >= $party) {
                    $suggestions[] = [
                        'tables' => array_map(static fn (array $t): int => $t['id'], $set),
                        'seats'  => $total,
                        'type'   => 'merge',
                    ];
                    break;
                }
            }
        }

        return array_slice($this->sortSuggestions($suggestions), 0, 3);
    }

    /**
     * @param array<int, array{tables:int[], seats:int, type:string}> $suggestions
     *
     * @return array<int, array{tables:int[], seats:int, type:string}>
     */
    private function sortSuggestions(array $suggestions): array
    {
        usort(
            $suggestions,
            static function (array $a, array $b): int {
                if ($a['seats'] === $b['seats']) {
                    return count($a['tables']) <=> count($b['tables']);
                }

                return $a['seats'] <=> $b['seats'];
            }
        );

        return $suggestions;
    }

    /**
     * @param array<int, array{id:int, room_id:int, capacity:int, seats_min:int, seats_max:int, join_group:string|null}> $tables
     *
     * @return array<int, array{id:int, room_id:int, capacity:int, seats_min:int, seats_max:int, join_group:string|null}>
     */
    private function sortTablesByCapacity(array $tables): array
    {
        usort(
            $tables,
            static function (array $a, array $b): int {
                if ($a['seats_max'] === $b['seats_max']) {
                    return $a['capacity'] <=> $b['capacity'];
                }

                return $a['seats_max'] <=> $b['seats_max'];
            }
        );

        return $tables;
    }

    /**
     * @param array<int, array<string, mixed>> $closures
     *
     * @return array{status:string, blocked_tables:int[], capacity_percent:int, reasons:string[]}
     */
    private function evaluateClosures(array $closures, DateTimeImmutable $slotStart, DateTimeImmutable $slotEnd, ?int $roomId): array
    {
        $blockedTables   = [];
        $capacityPercent = 100;
        $status          = 'open';
        $reasons         = [];

        foreach ($closures as $closure) {
            if (!$this->closureApplies($closure, $slotStart, $slotEnd, $roomId)) {
                continue;
            }

            if ($closure['capacity_override'] !== null && isset($closure['capacity_override']['percent'])) {
                $percent = (int) $closure['capacity_override']['percent'];
                $capacityPercent = min($capacityPercent, max(0, min(100, $percent)));
                $reasons[] = sprintf(
                    __('Capienza ridotta al %d%% da una regola di orario speciale.', 'fp-restaurant-reservations'),
                    $capacityPercent
                );
                continue;
            }

            if ($closure['scope'] === 'table' && $closure['table_id'] !== null) {
                $blockedTables[] = $closure['table_id'];
                $reasons[]       = __('Tavolo non disponibile per chiusura programmata.', 'fp-restaurant-reservations');
                continue;
            }

            if ($closure['scope'] === 'room' && $roomId !== null && $closure['room_id'] !== null && $closure['room_id'] !== $roomId) {
                continue;
            }

            $status    = 'blocked';
            $reasons[] = __('Slot non prenotabile per chiusura programmata.', 'fp-restaurant-reservations');
        }

        return [
            'status'          => $status,
            'blocked_tables'  => $blockedTables,
            'capacity_percent'=> $capacityPercent,
            'reasons'         => $reasons,
        ];
    }

    private function closureApplies(array $closure, DateTimeImmutable $slotStart, DateTimeImmutable $slotEnd, ?int $roomId): bool
    {
        if ($closure['scope'] === 'room' && $roomId !== null && $closure['room_id'] !== null && $closure['room_id'] !== $roomId) {
            return false;
        }

        if ($closure['scope'] === 'table' && $closure['table_id'] === null) {
            return false;
        }

        if ($closure['recurrence'] !== null) {
            return $this->recurringClosureApplies($closure, $slotStart, $slotEnd);
        }

        return $closure['start'] < $slotEnd && $closure['end'] > $slotStart;
    }

    private function recurringClosureApplies(array $closure, DateTimeImmutable $slotStart, DateTimeImmutable $slotEnd): bool
    {
        $recurrence = $closure['recurrence'];
        $type       = strtolower((string) ($recurrence['type'] ?? ''));
        $until      = isset($recurrence['until']) ? trim((string) $recurrence['until']) : '';
        $from       = isset($recurrence['from']) ? trim((string) $recurrence['from']) : '';

        if ($from !== '') {
            $fromDate = new DateTimeImmutable($from . ' 00:00:00', $slotStart->getTimezone());
            if ($slotStart < $fromDate) {
                return false;
            }
        }

        if ($until !== '') {
            $untilDate = new DateTimeImmutable($until . ' 23:59:59', $slotStart->getTimezone());
            if ($slotStart > $untilDate) {
                return false;
            }
        }

        $dayKey = strtolower($slotStart->format('D'));

        switch ($type) {
            case 'weekly':
                $days = $recurrence['days'] ?? [];
                $days = is_array($days) ? array_map(static fn ($day): string => strtolower((string) $day), $days) : [];
                if (!in_array($dayKey, $days, true) && !in_array((string) $slotStart->format('N'), $days, true)) {
                    return false;
                }
                break;
            case 'daily':
                // Daily applies to all days within the from/until window.
                break;
            case 'monthly':
                $days = $recurrence['days'] ?? [];
                $days = is_array($days) ? $days : [];
                $dayOfMonth = (int) $slotStart->format('j');
                if ($days !== [] && !in_array($dayOfMonth, array_map('intval', $days), true)) {
                    return false;
                }
                break;
            default:
                return false;
        }

        $startTime = $closure['start']->format('H:i:s');
        $endTime   = $closure['end']->format('H:i:s');

        $occurrenceStart = new DateTimeImmutable($slotStart->format('Y-m-d') . ' ' . $startTime, $slotStart->getTimezone());
        $occurrenceEnd   = new DateTimeImmutable($slotStart->format('Y-m-d') . ' ' . $endTime, $slotStart->getTimezone());

        if ($occurrenceEnd <= $occurrenceStart) {
            $occurrenceEnd = $occurrenceEnd->add(new DateInterval('P1D'));
        }

        return $occurrenceStart < $slotEnd && $occurrenceEnd > $slotStart;
    }

    /**
     * @param array<int, array{tables:int[], seats:int, type:string}> $slots
     */
    private function hasAvailability(array $slots): bool
    {
        foreach ($slots as $slot) {
            if (!is_array($slot) || !isset($slot['status'])) {
                continue;
            }

            if (in_array($slot['status'], ['available', 'limited'], true)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $criteria
     *
     * @return array<string, mixed>
     */
    private function normalizeCriteria(int $party, ?int $roomId, array $criteria): array
    {
        $normalized = [
            'party' => $party,
        ];

        if ($roomId !== null) {
            $normalized['room'] = $roomId;
        }

        if (isset($criteria['meal']) && $criteria['meal'] !== '') {
            $normalized['meal'] = (string) $criteria['meal'];
        }

        if (isset($criteria['event_id'])) {
            $normalized['event_id'] = (int) $criteria['event_id'];
        }

        return $normalized;
    }

    /**
     * @param array<int, array{tables:int[], seats:int, type:string}> $suggestions
     *
     * @return array<string, mixed>
     */
    private function buildSlotPayload(
        DateTimeImmutable $start,
        DateTimeImmutable $end,
        string $status,
        int $capacity,
        int $party,
        bool $waitlist,
        array $reasons,
        array $suggestions
    ): array {
        return [
            'start'              => $start->format(DateTimeInterface::ATOM),
            'end'                => $end->format(DateTimeInterface::ATOM),
            'label'              => $start->format('H:i'),
            'status'             => $status,
            'available_capacity' => $capacity,
            'requested_party'    => $party,
            'waitlist_available' => $waitlist && $status === 'full',
            'reasons'            => $reasons,
            'suggested_tables'   => $suggestions,
        ];
    }
}
