<?php

declare(strict_types=1);

namespace FP\Resv\Presentation\API\REST;

use FP\Resv\Application\Reservations\CreateReservationUseCase;
use FP\Resv\Application\Reservations\DeleteReservationUseCase;
use FP\Resv\Application\Reservations\UpdateReservationUseCase;
use FP\Resv\Core\Exceptions\ValidationException;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Core\Services\SanitizerInterface;
use FP\Resv\Domain\Reservations\Repositories\ReservationRepositoryInterface;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Reservations REST Endpoint
 * 
 * Thin controller for reservation REST API operations.
 * Delegates business logic to Use Cases.
 *
 * @package FP\Resv\Presentation\API\REST
 */
final class ReservationsEndpoint extends BaseEndpoint
{
    public function __construct(
        LoggerInterface $logger,
        private readonly CreateReservationUseCase $createUseCase,
        private readonly UpdateReservationUseCase $updateUseCase,
        private readonly DeleteReservationUseCase $deleteUseCase,
        private readonly ReservationRepositoryInterface $repository,
        private readonly SanitizerInterface $sanitizer
    ) {
        parent::__construct($logger);
    }
    
    /**
     * Create a reservation
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        // Clean any output buffers to prevent non-JSON output
        if (ob_get_level() > 0) {
            ob_clean();
        }
        
        try {
            // Get request data - try JSON params first, then body params, then all params
            $rawData = $request->get_json_params();
            if (empty($rawData)) {
                $rawData = $request->get_body_params();
            }
            if (empty($rawData)) {
                $rawData = $request->get_params();
            }
            
            // Log raw data for debugging
            $this->logger->debug('Frontend reservation creation - raw data', [
                'raw_data' => $rawData,
                'json_params' => $request->get_json_params(),
                'body_params' => $request->get_body_params(),
                'all_params' => $request->get_params(),
            ]);
            
            // Sanitize input
            $data = $this->sanitizeReservationData($rawData ?? []);
            
            // Log sanitized data
            $this->logger->debug('Frontend reservation creation - sanitized data', [
                'sanitized_data' => $data,
            ]);
            
            // Validate required fields before executing use case
            $requiredFields = ['date', 'time', 'party', 'first_name', 'last_name', 'email'];
            $missingFields = [];
            foreach ($requiredFields as $field) {
                if (empty($data[$field])) {
                    $missingFields[] = $field;
                }
            }
            
            if (!empty($missingFields)) {
                $this->logger->warning('Frontend reservation creation - missing required fields', [
                    'missing_fields' => $missingFields,
                    'received_data' => $data,
                ]);
                return $this->error('missing_fields', 'Missing required fields: ' . implode(', ', $missingFields), 400);
            }
            
            // Ensure party is an integer
            if (isset($data['party'])) {
                $data['party'] = (int) $data['party'];
            }
            
            // Ensure time is in correct format (HH:MM)
            if (isset($data['time'])) {
                $time = trim((string) $data['time']);
                // Remove seconds if present (HH:MM:SS -> HH:MM)
                if (preg_match('/^(\d{2}):(\d{2})(?::\d{2})?$/', $time, $matches)) {
                    $data['time'] = $matches[1] . ':' . $matches[2];
                }
            }
            
            // Ensure meal has a default value if not provided
            if (empty($data['meal'])) {
                $data['meal'] = 'dinner';
            }
            
            // Execute use case
            $this->logger->info('Frontend reservation creation - executing use case', [
                'final_data' => $data,
                'has_marketing_consent' => isset($data['marketing_consent']),
                'has_profiling_consent' => isset($data['profiling_consent']),
                'marketing_consent_value' => $data['marketing_consent'] ?? 'not set',
                'profiling_consent_value' => $data['profiling_consent'] ?? 'not set',
            ]);
            
            $reservation = $this->createUseCase->execute($data);
            
            $this->logger->info('Frontend reservation creation - success', [
                'reservation_id' => $reservation->getId(),
            ]);
            
            // Return response
            return $this->success([
                'id' => $reservation->getId(),
                'date' => $reservation->getDate(),
                'time' => $reservation->getTime(),
                'party' => $reservation->getParty(),
                'status' => $reservation->getStatus(),
                'message' => 'Reservation created successfully',
            ], 201);
        } catch (ValidationException $e) {
            // Clean output buffer before returning error
            if (ob_get_level() > 0) {
                ob_clean();
            }
            return $this->handleValidationException($e);
        } catch (\Throwable $e) {
            // Clean output buffer before returning error
            if (ob_get_level() > 0) {
                ob_clean();
            }
            
            // Log full exception details for debugging
            $this->logger->error('Frontend reservation creation - exception caught', [
                'exception' => $e::class,
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
            ]);
            
            return $this->handleException($e);
        }
    }
    
    /**
     * Update a reservation
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $id = (int) $this->getParam($request, 'id');
            
            if ($id <= 0) {
                return $this->error('invalid_id', 'Invalid reservation ID', 400);
            }
            
            // Sanitize input
            $data = $this->sanitizeReservationData($request->get_json_params() ?? []);
            
            // Execute use case
            $reservation = $this->updateUseCase->execute($id, $data);
            
            // Return response
            return $this->success([
                'id' => $reservation->getId(),
                'date' => $reservation->getDate(),
                'time' => $reservation->getTime(),
                'party' => $reservation->getParty(),
                'status' => $reservation->getStatus(),
                'message' => 'Reservation updated successfully',
            ]);
        } catch (ValidationException $e) {
            return $this->handleValidationException($e);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Delete a reservation
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function delete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $id = (int) $this->getParam($request, 'id');
            
            if ($id <= 0) {
                return $this->error('invalid_id', 'Invalid reservation ID', 400);
            }
            
            // Execute use case
            $success = $this->deleteUseCase->execute($id);
            
            if (!$success) {
                return $this->error('not_found', 'Reservation not found', 404);
            }
            
            // Return response
            return $this->success([
                'id' => $id,
                'message' => 'Reservation deleted successfully',
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Get a reservation
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $id = (int) $this->getParam($request, 'id');
            
            if ($id <= 0) {
                return $this->error('invalid_id', 'Invalid reservation ID', 400);
            }
            
            $reservation = $this->repository->findById($id);
            
            if ($reservation === null) {
                return $this->error('not_found', 'Reservation not found', 404);
            }
            
            return $this->success($reservation->toArray());
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Sanitize reservation data
     * 
     * Supports both prefixed (fp_resv_*) and non-prefixed field names for compatibility
     * 
     * @param array<string, mixed> $data Raw data
     * @return array<string, mixed> Sanitized data
     */
    private function sanitizeReservationData(array $data): array
    {
        $sanitized = [];
        
        // Helper function to get value with or without prefix
        $getValue = function (string $key, string $prefix = 'fp_resv_') use ($data): mixed {
            // Try prefixed first (frontend format)
            if (isset($data[$prefix . $key])) {
                return $data[$prefix . $key];
            }
            // Fallback to non-prefixed (backend format)
            return $data[$key] ?? null;
        };
        
        // Date
        $date = $getValue('date');
        if ($date !== null) {
            $sanitized['date'] = $this->sanitizer->textField((string) $date);
        }
        
        // Time
        $time = $getValue('time');
        if ($time !== null) {
            $sanitized['time'] = $this->sanitizer->textField((string) $time);
        }
        
        // Party
        $party = $getValue('party');
        if ($party !== null) {
            $sanitized['party'] = $this->sanitizer->integer($party);
        }
        
        // Meal
        $meal = $getValue('meal');
        if ($meal !== null) {
            $sanitized['meal'] = $this->sanitizer->textField((string) $meal);
        }
        
        // First name
        $firstName = $getValue('first_name');
        if ($firstName !== null) {
            $sanitized['first_name'] = $this->sanitizer->textField((string) $firstName);
        }
        
        // Last name
        $lastName = $getValue('last_name');
        if ($lastName !== null) {
            $sanitized['last_name'] = $this->sanitizer->textField((string) $lastName);
        }
        
        // Email
        $email = $getValue('email');
        if ($email !== null) {
            $sanitized['email'] = $this->sanitizer->email((string) $email);
        }
        
        // Phone - handle both fp_resv_phone and fp_resv_phone_cc + fp_resv_phone_local
        $phone = $getValue('phone');
        if ($phone === null || $phone === '') {
            // Try to construct phone from country code and local number
            $phoneCC = $getValue('phone_cc');
            $phoneLocal = $getValue('phone_local');
            if ($phoneCC !== null && $phoneLocal !== null && $phoneLocal !== '') {
                // Remove + from phoneCC if present
                $phoneCC = ltrim((string) $phoneCC, '+');
                $phone = '+' . $phoneCC . ' ' . trim((string) $phoneLocal);
            }
        }
        if ($phone !== null && $phone !== '') {
            // Clean phone number - remove any extra spaces
            $phone = preg_replace('/\s+/', ' ', trim((string) $phone));
            $sanitized['phone'] = $this->sanitizer->textField($phone);
        }
        
        // Status
        $status = $getValue('status');
        if ($status !== null) {
            $sanitized['status'] = $this->sanitizer->textField((string) $status);
        }
        
        // Notes
        $notes = $getValue('notes');
        if ($notes !== null) {
            $sanitized['notes'] = $this->sanitizer->textField((string) $notes);
        }
        
        // Allergies
        $allergies = $getValue('allergies');
        if ($allergies !== null) {
            $sanitized['allergies'] = $this->sanitizer->textField((string) $allergies);
        }
        
        // Marketing consent (from frontend: fp_resv_marketing_consent)
        $marketingConsent = $getValue('marketing_consent');
        if ($marketingConsent !== null) {
            $sanitized['marketing_consent'] = $this->sanitizer->boolean($marketingConsent);
        }
        
        // Profiling consent (from frontend: fp_resv_profiling_consent, if exists)
        $profilingConsent = $getValue('profiling_consent');
        if ($profilingConsent !== null) {
            $sanitized['profiling_consent'] = $this->sanitizer->boolean($profilingConsent);
        }
        
        return $sanitized;
    }
}










