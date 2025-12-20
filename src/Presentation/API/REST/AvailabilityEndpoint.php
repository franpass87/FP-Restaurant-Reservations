<?php

declare(strict_types=1);

namespace FP\Resv\Presentation\API\REST;

use DateTimeImmutable;
use FP\Resv\Application\Availability\GetAvailabilityUseCase;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Core\Services\SanitizerInterface;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Availability REST Endpoint
 * 
 * Thin controller for availability REST API operations.
 * Delegates business logic to Use Cases.
 *
 * @package FP\Resv\Presentation\API\REST
 */
final class AvailabilityEndpoint extends BaseEndpoint
{
    public function __construct(
        LoggerInterface $logger,
        private readonly GetAvailabilityUseCase $getAvailabilityUseCase,
        private readonly SanitizerInterface $sanitizer
    ) {
        parent::__construct($logger);
    }
    
    /**
     * Get availability for a date
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function getAvailability(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            // Sanitize and prepare criteria
            $criteria = [
                'date' => $this->sanitizer->textField((string) $this->getParam($request, 'date', '')),
                'party' => $this->sanitizer->integer($this->getParam($request, 'party', 0)),
            ];
            
            $meal = $this->getParam($request, 'meal');
            if ($meal !== null) {
                $criteria['meal'] = $this->sanitizer->textField((string) $meal);
            }
            
            $room = $this->getParam($request, 'room');
            if ($room !== null) {
                $criteria['room'] = $this->sanitizer->integer($room);
            }
            
            $eventId = $this->getParam($request, 'event_id');
            if ($eventId !== null) {
                $criteria['event_id'] = $this->sanitizer->integer($eventId);
            }
            
            // Validate required fields
            if (empty($criteria['date'])) {
                return $this->error('missing_date', 'Date parameter is required', 400);
            }
            
            if ($criteria['party'] <= 0) {
                return $this->error('invalid_party', 'Party size must be greater than 0', 400);
            }
            
            // Execute use case
            $availability = $this->getAvailabilityUseCase->execute($criteria);
            
            // Return response
            return $this->success($availability);
        } catch (\InvalidArgumentException $e) {
            return $this->error('invalid_criteria', $e->getMessage(), 400);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Get available days for a date range
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function getAvailableDays(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $from = $this->sanitizer->textField((string) $this->getParam($request, 'from', ''));
            $to = $this->sanitizer->textField((string) $this->getParam($request, 'to', ''));
            $meal = $this->getParam($request, 'meal');
            
            // Use current date as default for 'from'
            if (empty($from)) {
                $from = current_time('Y-m-d');
            }
            
            // Use 3 months from now as default for 'to'
            if (empty($to)) {
                $to = date('Y-m-d', strtotime('+3 months'));
            }
            
            // Get availability service from use case (via container)
            $container = \FP\Resv\Kernel\Bootstrap::container();
            $availabilityService = $container->get(\FP\Resv\Domain\Reservations\Services\AvailabilityServiceInterface::class);
            
            // Get available days
            $availableDays = $availabilityService->findAvailableDaysForAllMeals($from, $to);
            
            // Filter by meal if specified
            if ($meal !== null && $meal !== '') {
                $filtered = [];
                foreach ($availableDays as $date => $dayInfo) {
                    $mealAvailable = isset($dayInfo['meals'][$meal]) && $dayInfo['meals'][$meal];
                    $filtered[$date] = [
                        'available' => $mealAvailable,
                        'meal' => $meal,
                    ];
                }
                $availableDays = $filtered;
            }
            
            return $this->success([
                'from' => $from,
                'to' => $to,
                'days' => $availableDays,
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Get available time slots for a specific date, meal and party size
     * 
     * This endpoint is used by the frontend form to display available time slots.
     * Returns slots in the format expected by the JavaScript: { slots: [{ time, slot_start, available }] }
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function getAvailableSlots(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            // Sanitize and prepare criteria
            $criteria = [
                'date' => $this->sanitizer->textField((string) $this->getParam($request, 'date', '')),
                'party' => $this->sanitizer->integer($this->getParam($request, 'party', 0)),
            ];
            
            $meal = $this->getParam($request, 'meal');
            if ($meal !== null) {
                $criteria['meal'] = $this->sanitizer->textField((string) $meal);
            }
            
            // Validate required fields
            if (empty($criteria['date'])) {
                return $this->error('missing_date', 'Date parameter is required', 400);
            }
            
            if ($criteria['party'] <= 0) {
                return $this->error('invalid_party', 'Party size must be greater than 0', 400);
            }
            
            if (empty($criteria['meal'])) {
                return $this->error('missing_meal', 'Meal parameter is required', 400);
            }
            
            // Execute use case to get availability
            // Note: The service returns ['date', 'timezone', 'criteria', 'slots', 'meta']
            // We only need 'slots' for this endpoint
            $availability = $this->getAvailabilityUseCase->execute($criteria);
            
            // Transform availability data into slots format expected by frontend
            $cleanSlots = [];
            
            if (isset($availability['slots']) && is_array($availability['slots'])) {
                // Transform slots from backend format to frontend format
                // Backend format: ['label' => '19:00', 'start' => '2025-12-19T19:00:00+01:00', 'status' => 'available', 'capacity' => 40, ...]
                // Frontend format: ['time' => '19:00', 'slot_start' => '19:00', 'available' => true]
                foreach ($availability['slots'] as $slot) {
                    // Extract time from label (format: 'H:i') or from start datetime
                    $time = $slot['label'] ?? '';
                    if (empty($time) && isset($slot['start'])) {
                        // Parse ISO datetime and extract time
                        try {
                            $dateTime = new \DateTimeImmutable($slot['start']);
                            $time = $dateTime->format('H:i');
                        } catch (\Exception $e) {
                            $time = '';
                        }
                    }
                    
                    // Determine if slot is available based on status
                    $status = $slot['status'] ?? 'unknown';
                    $available = ($status === 'available');
                    
                    // Include ALL slots (available, full, blocked) - let frontend handle display
                    // Only exclude slots without valid time
                    if (!empty($time)) {
                        // Return ONLY the fields expected by frontend (time, slot_start, available)
                        // Explicitly exclude extra fields like capacity, status, date, meal, party
                        $cleanSlots[] = [
                            'time' => $time,
                            'slot_start' => $time, // Use same time for slot_start
                            'available' => $available,
                        ];
                    }
                }
            }
            
            // Create clean response with ONLY slots array
            // Do NOT include date, meal, party, criteria, timezone, meta from availability service
            $responseData = [
                'slots' => $cleanSlots,
            ];
            
            $response = $this->success($responseData);
            
            // Force clean data - ensure no extra fields are added
            // This prevents WordPress REST API or filters from adding extra fields
            $response->set_data($responseData);
            
            return $response;
        } catch (\InvalidArgumentException $e) {
            return $this->error('invalid_criteria', $e->getMessage(), 400);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
}










