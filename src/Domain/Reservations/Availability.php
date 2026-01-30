<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Reservations;

use DateInterval;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use FP\Resv\Core\Logging;
use FP\Resv\Core\Metrics;
use FP\Resv\Domain\Reservations\Availability\CapacityResolver;
use FP\Resv\Domain\Reservations\Availability\ClosureEvaluator;
use FP\Resv\Domain\Reservations\Availability\DataLoader;
use FP\Resv\Domain\Reservations\Availability\ReservationFilter;
use FP\Resv\Domain\Reservations\Availability\ScheduleParser;
use FP\Resv\Domain\Reservations\Availability\SlotPayloadBuilder;
use FP\Resv\Domain\Reservations\Availability\SlotStatusDeterminer;
use FP\Resv\Domain\Reservations\Availability\TableSuggester;
use FP\Resv\Domain\Reservations\ReservationStatuses;
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
    private const ACTIVE_STATUSES = ReservationStatuses::ACTIVE_FOR_AVAILABILITY;

    /**
     * @var array<string, array<string, mixed>>|null
     */
    private ?array $mealPlanCache = null;

    public function __construct(
        private readonly Options $options,
        private readonly wpdb $wpdb,
        private readonly DataLoader $dataLoader,
        private readonly ClosureEvaluator $closureEvaluator,
        private readonly TableSuggester $tableSuggester,
        private readonly ScheduleParser $scheduleParser,
        private readonly CapacityResolver $capacityResolver,
        private readonly SlotStatusDeterminer $statusDeterminer,
        private readonly SlotPayloadBuilder $payloadBuilder,
        private readonly ReservationFilter $reservationFilter
    ) {
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
        $rooms = $this->dataLoader->loadRooms($roomId);
        $tables = $this->dataLoader->loadTables($roomId);
        $closures = $this->dataLoader->loadClosures($from, $to->setTime(23, 59, 59), $timezone);
        
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
            $reservations = $this->dataLoader->loadReservations($dayStart, $dayEnd, $roomId, $turnoverMinutes, $bufferMinutes, $timezone);

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
        $mealKey = $criteria['meal'] ?? '';
        
        // Check if the meal is a special opening (starts with 'special_')
        $isSpecialMeal = str_starts_with($mealKey, 'special_');
        $specialOpenings = $this->closureEvaluator->getSpecialOpenings($closures, $dayStart);
        $specialCapacityLimit = null;
        $isSpecialOpening = false;
        $schedule = [];
        
        // If requesting a special meal, find and use its specific slots
        if ($isSpecialMeal && $specialOpenings !== []) {
            foreach ($specialOpenings as $opening) {
                // Match by meal_key if available
                if (isset($opening['meal_key']) && $opening['meal_key'] === $mealKey) {
                    $schedule = $opening['slots'] ?? [];
                    $specialCapacityLimit = $opening['capacity'] ?? null;
                    $isSpecialOpening = true;
                    break;
                }
            }
            
            // Fallback: if no exact match, use all special openings' slots
            if ($schedule === []) {
                foreach ($specialOpenings as $opening) {
                    $schedule = array_merge($schedule, $opening['slots'] ?? []);
                    if ($specialCapacityLimit === null || ($opening['capacity'] ?? PHP_INT_MAX) < $specialCapacityLimit) {
                        $specialCapacityLimit = $opening['capacity'] ?? null;
                    }
                }
                $isSpecialOpening = $schedule !== [];
            }
        }
        
        // For non-special meals or if no special slots found, use regular schedule
        if ($schedule === []) {
            $schedule = $this->scheduleParser->resolveScheduleForDay($dayStart, $mealSettings['schedule']);
            
            // Check for special openings if schedule is still empty
            if ($schedule === [] && $specialOpenings !== []) {
                foreach ($specialOpenings as $opening) {
                    $schedule = array_merge($schedule, $opening['slots'] ?? []);
                    if ($specialCapacityLimit === null || ($opening['capacity'] ?? PHP_INT_MAX) < $specialCapacityLimit) {
                        $specialCapacityLimit = $opening['capacity'] ?? null;
                    }
                }
                $isSpecialOpening = $schedule !== [];
            }
        }

        if ($schedule === []) {
            $dayKey = strtolower($dayStart->format('D'));
            
            // Nessuna disponibilità
            
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

        $roomCapacities = $this->capacityResolver->aggregateRoomCapacities($rooms, $tables, $defaultRoomCap);
        $slots = [];

        foreach ($schedule as $window) {
            $startMinute = $window['start'];
            $endMinute = $window['end'];

            for ($minute = $startMinute; $minute + $turnoverMinutes <= $endMinute; $minute += $slotInterval) {
                $slotStart = $dayStart->add(new DateInterval('PT' . $minute . 'M'));
                $slotEnd = $slotStart->add(new DateInterval('PT' . $turnoverMinutes . 'M'));

                $closureEffect = $this->closureEvaluator->evaluateClosures($closures, $slotStart, $slotEnd, $roomId);
                if ($closureEffect['status'] === 'blocked') {
                    $slots[] = $this->payloadBuilder->build(
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

                $availableTables = $this->reservationFilter->filterAvailableTables($tables, $closureEffect['blocked_tables']);
                $hasPhysicalTables = $availableTables !== [];
                $overlapping = $this->reservationFilter->filterOverlapping($reservations, $slotStart, $slotEnd);
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

                    $slots[] = $this->payloadBuilder->build(
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

                $baseCapacity = $this->capacityResolver->resolveForScope($roomCapacities, $roomId, $hasPhysicalTables);
                $allowedCapacity = $this->capacityResolver->applyReductions(
                    $baseCapacity,
                    $availableTables,
                    0,
                    $closureEffect['capacity_percent']
                );
                $capacity = $this->capacityResolver->applyReductions(
                    $baseCapacity,
                    $availableTables,
                    $unassignedCapacity,
                    $closureEffect['capacity_percent']
                );

                // Apply capacity limit from meal settings
                if ($mealSettings['capacity_limit'] !== null) {
                    $allowedCapacity = min($allowedCapacity, $mealSettings['capacity_limit']);
                    $capacity = min($capacity, $mealSettings['capacity_limit']);
                }
                
                // Apply special opening capacity limit if applicable
                if ($isSpecialOpening && $specialCapacityLimit !== null) {
                    $allowedCapacity = min($allowedCapacity, $specialCapacityLimit);
                    $capacity = min($capacity, $specialCapacityLimit);
                }

                $status = $this->statusDeterminer->determine($capacity, $allowedCapacity, $party);
                $reasons = $closureEffect['reasons'];
                
                // Add note if this is a special opening
                if ($isSpecialOpening && $specialOpenings !== []) {
                    $openingLabel = $specialOpenings[0]['label'] ?? '';
                    if ($openingLabel !== '') {
                        $reasons[] = sprintf(__('Apertura speciale: %s', 'fp-restaurant-reservations'), $openingLabel);
                    }
                }

                if ($status === 'full' && $waitlistEnabled) {
                    $reasons[] = __('Disponibile solo lista di attesa per questo orario.', 'fp-restaurant-reservations');
                }

                $suggestions = $hasPhysicalTables
                    ? $this->tableSuggester->suggestTables($availableTables, $party, $mergeStrategy)
                    : [];

                $slots[] = $this->payloadBuilder->build(
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

        // FILTRO SLOT PASSATI: Rimuove slot nel passato per la data di oggi
        $now = new DateTimeImmutable('now', $timezone);
        $today = $now->format('Y-m-d');
        $requestedDate = $dayStart->format('Y-m-d');
        
        if ($requestedDate === $today) {
            // Se la data richiesta è oggi, filtra gli slot passati
            $slots = array_values(array_filter($slots, function ($slot) use ($now, $timezone) {
                if (!isset($slot['start'])) {
                    return true; // Mantieni slot senza orario di inizio (non dovrebbe accadere)
                }
                
                // Parsing della stringa data-ora dello slot con timezone corretto
                try {
                    $slotDateTime = new DateTimeImmutable($slot['start'], $timezone);
                    // Mantieni solo slot futuri
                    return $slotDateTime > $now;
                } catch (\Exception $e) {
                    return true; // In caso di errore parsing, mantieni lo slot
                }
            }));
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
     * Find available days for all configured meals in a date range.
     * Returns an array of dates with availability information.
     * 
     * @param string|DateTimeImmutable $from Date string (Y-m-d) or DateTimeImmutable
     * @param string|DateTimeImmutable $to Date string (Y-m-d) or DateTimeImmutable
     * @return array<string, array{available:bool, meals:array<string, bool>}>
     */
    public function findAvailableDaysForAllMeals(string|DateTimeImmutable $from, string|DateTimeImmutable $to): array
    {
        $timezone = $this->resolveTimezone();
        
        // Convert strings to DateTimeImmutable with correct timezone
        if (is_string($from)) {
            $from = new DateTimeImmutable($from . ' 00:00:00', $timezone);
        }
        if (is_string($to)) {
            $to = new DateTimeImmutable($to . ' 23:59:59', $timezone);
        }
        
        $stopTimer = Metrics::timer('availability.days_check', [
            'days' => $from->diff($to)->days + 1,
        ]);
        $meals = $this->getMealPlan();
        
        // Load closures for the entire range to check for special openings
        $closures = $this->dataLoader->loadClosures($from, $to, $timezone);
        
        // Se non ci sono meal configurati, usa lo schedule di default
        $mealsToCheck = [];
        if (empty($meals)) {
            $mealsToCheck['default'] = [];
        } else {
            foreach ($meals as $mealKey => $mealData) {
                $mealsToCheck[$mealKey] = $mealData;
            }
        }

        $results = [];
        $current = $from;

        while ($current <= $to) {
            $dateKey = $current->format('Y-m-d');
            $dayKey = strtolower($current->format('D'));
            $hasAnyAvailability = false;
            $mealAvailability = [];

            // Controlla ogni meal per questo giorno
            foreach ($mealsToCheck as $mealKey => $mealData) {
                $mealSettings = $this->resolveMealSettings($mealKey);
                $schedule = $this->scheduleParser->resolveScheduleForDay($current, $mealSettings['schedule']);
                
                // Un giorno è disponibile per un meal se ha almeno uno schedule configurato
                $isAvailable = !empty($schedule);
                $mealAvailability[$mealKey] = $isAvailable;
                
                if ($isAvailable) {
                    $hasAnyAvailability = true;
                }
            }
            
            // Check for special openings on this day
            $specialOpenings = $this->closureEvaluator->getSpecialOpenings($closures, $current);
            if ($specialOpenings !== []) {
                $hasAnyAvailability = true;
                // Add special openings as available "meals"
                foreach ($specialOpenings as $opening) {
                    $specialMealKey = $opening['meal_key'] ?? 'special';
                    $mealAvailability[$specialMealKey] = true;
                }
            }

            $results[$dateKey] = [
                'available' => $hasAnyAvailability,
                'meals' => $mealAvailability,
            ];

            $current = $current->add(new DateInterval('P1D'));
        }

        $stopTimer();

        return $results;
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
        $schedule     = $this->scheduleParser->resolveScheduleForDay($dayStart, $mealSettings['schedule']);
        
        // Load closures early to check for special openings
        $closures = $this->dataLoader->loadClosures($dayStart, $dayEnd, $timezone);
        
        // Check for special openings if schedule is empty
        $specialOpenings = $this->closureEvaluator->getSpecialOpenings($closures, $dayStart);
        $specialCapacityLimit = null;
        $isSpecialOpening = false;
        
        if ($schedule === [] && $specialOpenings !== []) {
            // Use slots from special openings
            foreach ($specialOpenings as $opening) {
                $schedule = array_merge($schedule, $opening['slots']);
                if ($specialCapacityLimit === null || $opening['capacity'] < $specialCapacityLimit) {
                    $specialCapacityLimit = $opening['capacity'];
                }
            }
            $isSpecialOpening = true;
        }
        
        if ($schedule === []) {
            $dayKey = strtolower($dayStart->format('D'));
            
            // Nessuna disponibilità
            
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

        $rooms      = $this->dataLoader->loadRooms($roomId);
        $tables     = $this->dataLoader->loadTables($roomId);
        // Note: $closures already loaded above for special openings check
        $reservations = $this->dataLoader->loadReservations($dayStart, $dayEnd, $roomId, $turnoverMinutes, $bufferMinutes, $timezone);

        $roomCapacities = $this->capacityResolver->aggregateRoomCapacities($rooms, $tables, $defaultRoomCap);
        $slots          = [];

        foreach ($schedule as $window) {
            $startMinute = $window['start'];
            $endMinute   = $window['end'];

            for ($minute = $startMinute; $minute + $turnoverMinutes <= $endMinute; $minute += $slotInterval) {
                $slotStart = $dayStart->add(new DateInterval('PT' . $minute . 'M'));
                $slotEnd   = $slotStart->add(new DateInterval('PT' . $turnoverMinutes . 'M'));

                $closureEffect = $this->closureEvaluator->evaluateClosures($closures, $slotStart, $slotEnd, $roomId);
                if ($closureEffect['status'] === 'blocked') {
                    $slots[] = $this->payloadBuilder->build(
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

                $availableTables    = $this->reservationFilter->filterAvailableTables($tables, $closureEffect['blocked_tables']);
                $hasPhysicalTables  = $availableTables !== [];
                $overlapping        = $this->reservationFilter->filterOverlapping($reservations, $slotStart, $slotEnd);
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

                    $slots[] = $this->payloadBuilder->build(
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

                $baseCapacity = $this->capacityResolver->resolveForScope($roomCapacities, $roomId, $hasPhysicalTables);
                $allowedCapacity = $this->capacityResolver->applyReductions(
                    $baseCapacity,
                    $availableTables,
                    0,
                    $closureEffect['capacity_percent']
                );
                $capacity = $this->capacityResolver->applyReductions(
                    $baseCapacity,
                    $availableTables,
                    $unassignedCapacity,
                    $closureEffect['capacity_percent']
                );

                if ($mealSettings['capacity_limit'] !== null) {
                    $allowedCapacity = min($allowedCapacity, $mealSettings['capacity_limit']);
                    $capacity        = min($capacity, $mealSettings['capacity_limit']);
                }
                
                // Apply special opening capacity limit if applicable
                if ($isSpecialOpening && $specialCapacityLimit !== null) {
                    $allowedCapacity = min($allowedCapacity, $specialCapacityLimit);
                    $capacity = min($capacity, $specialCapacityLimit);
                }

                $status  = $this->statusDeterminer->determine($capacity, $allowedCapacity, $party);
                $reasons = $closureEffect['reasons'];
                
                // Add note if this is a special opening
                if ($isSpecialOpening && $specialOpenings !== []) {
                    $openingLabel = $specialOpenings[0]['label'] ?? '';
                    if ($openingLabel !== '') {
                        $reasons[] = sprintf(__('Apertura speciale: %s', 'fp-restaurant-reservations'), $openingLabel);
                    }
                }

                if ($status === 'full' && $waitlistEnabled) {
                    $reasons[] = __('Disponibile solo lista di attesa per questo orario.', 'fp-restaurant-reservations');
                }

                $suggestions = $hasPhysicalTables
                    ? $this->tableSuggester->suggestTables($availableTables, $party, $mergeStrategy)
                    : [];

                $slots[] = $this->payloadBuilder->build(
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

        // FILTRO SLOT PASSATI: Rimuove slot nel passato per la data di oggi
        $now = new DateTimeImmutable('now', $timezone);
        $today = $now->format('Y-m-d');
        $requestedDate = $dayStart->format('Y-m-d');
        
        if ($requestedDate === $today) {
            // Se la data richiesta è oggi, filtra gli slot passati
            $slots = array_values(array_filter($slots, function ($slot) use ($now, $timezone) {
                if (!isset($slot['start'])) {
                    return true; // Mantieni slot senza orario di inizio (non dovrebbe accadere)
                }
                
                // Parsing della stringa data-ora dello slot con timezone corretto
                try {
                    $slotDateTime = new DateTimeImmutable($slot['start'], $timezone);
                    // Mantieni solo slot futuri
                    return $slotDateTime > $now;
                } catch (\Exception $e) {
                    return true; // In caso di errore parsing, mantieni lo slot
                }
            }));
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
        $scheduleMap        = $this->scheduleParser->parseScheduleDefinition($defaultScheduleRaw);
        
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

        // Log disabilitati per evitare errori

        if ($mealKey !== '') {
            $plan = $this->getMealPlan();
            
            // Log disabilitati
            
            if (isset($plan[$mealKey])) {
                $meal = $plan[$mealKey];
                
                // Log disabilitati
                
                if (!empty($meal['hours_definition'])) {
                    $mealSchedule = $this->scheduleParser->parseScheduleDefinition((string) $meal['hours_definition']);
                    
                    // Log disabilitati
                    if ($mealSchedule !== []) {
                        $scheduleMap = $mealSchedule;
                    }
                } else {
                    // Usa schedule di default
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
                // Meal key non trovato, usa default
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

        // Log disabilitati

        // Log disabilitati

        return $result;
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

}
