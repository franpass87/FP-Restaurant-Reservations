<?php

declare(strict_types=1);

namespace FP\Resv\Presentation\API\REST;

use FP\Resv\Application\Events\CreateEventUseCase;
use FP\Resv\Application\Events\DeleteEventUseCase;
use FP\Resv\Application\Events\UpdateEventUseCase;
use FP\Resv\Core\Exceptions\ValidationException;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Core\Services\SanitizerInterface;
use FP\Resv\Domain\Events\Repositories\EventRepositoryInterface;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Events REST Endpoint
 * 
 * Thin controller for event REST API operations.
 * Delegates business logic to Use Cases.
 *
 * @package FP\Resv\Presentation\API\REST
 */
final class EventsEndpoint extends BaseEndpoint
{
    public function __construct(
        LoggerInterface $logger,
        private readonly CreateEventUseCase $createUseCase,
        private readonly UpdateEventUseCase $updateUseCase,
        private readonly DeleteEventUseCase $deleteUseCase,
        private readonly EventRepositoryInterface $repository,
        private readonly SanitizerInterface $sanitizer
    ) {
        parent::__construct($logger);
    }
    
    /**
     * Create an event
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            // Sanitize input
            $data = $this->sanitizeEventData($request->get_json_params() ?? []);
            
            // Execute use case
            $event = $this->createUseCase->execute($data);
            
            // Return response
            return $this->success([
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'start_date' => $event->getStartDate()->format('Y-m-d H:i:s'),
                'end_date' => $event->getEndDate()->format('Y-m-d H:i:s'),
                'max_capacity' => $event->getMaxCapacity(),
                'message' => 'Event created successfully',
            ], 201);
        } catch (ValidationException $e) {
            return $this->handleValidationException($e);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Update an event
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $id = (int) $this->getParam($request, 'id');
            
            if ($id <= 0) {
                return $this->error('invalid_id', 'Invalid event ID', 400);
            }
            
            // Sanitize input
            $data = $this->sanitizeEventData($request->get_json_params() ?? []);
            
            // Execute use case
            $event = $this->updateUseCase->execute($id, $data);
            
            // Return response
            return $this->success([
                'id' => $event->getId(),
                'title' => $event->getTitle(),
                'start_date' => $event->getStartDate()->format('Y-m-d H:i:s'),
                'end_date' => $event->getEndDate()->format('Y-m-d H:i:s'),
                'max_capacity' => $event->getMaxCapacity(),
                'message' => 'Event updated successfully',
            ]);
        } catch (ValidationException $e) {
            return $this->handleValidationException($e);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Delete an event
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function delete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $id = (int) $this->getParam($request, 'id');
            
            if ($id <= 0) {
                return $this->error('invalid_id', 'Invalid event ID', 400);
            }
            
            // Execute use case
            $success = $this->deleteUseCase->execute($id);
            
            if (!$success) {
                return $this->error('not_found', 'Event not found', 404);
            }
            
            // Return response
            return $this->success([
                'id' => $id,
                'message' => 'Event deleted successfully',
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Get an event
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $id = (int) $this->getParam($request, 'id');
            
            if ($id <= 0) {
                return $this->error('invalid_id', 'Invalid event ID', 400);
            }
            
            $event = $this->repository->findById($id);
            
            if ($event === null) {
                return $this->error('not_found', 'Event not found', 404);
            }
            
            return $this->success($event->toArray());
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * List events
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function list(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $criteria = [];
            
            if ($request->get_param('is_active') !== null) {
                $criteria['is_active'] = (bool) $request->get_param('is_active');
            }
            
            if ($request->get_param('start_date_from')) {
                $criteria['start_date_from'] = $this->sanitizer->textField($request->get_param('start_date_from'));
            }
            
            if ($request->get_param('start_date_to')) {
                $criteria['start_date_to'] = $this->sanitizer->textField($request->get_param('start_date_to'));
            }
            
            $limit = (int) ($request->get_param('per_page') ?? 100);
            $offset = (int) ($request->get_param('offset') ?? 0);
            
            $events = $this->repository->findBy($criteria, $limit, $offset);
            
            return $this->success([
                'events' => array_map(fn($event) => $event->toArray(), $events),
                'count' => count($events),
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Sanitize event data
     * 
     * @param array<string, mixed> $data Raw data
     * @return array<string, mixed> Sanitized data
     */
    private function sanitizeEventData(array $data): array
    {
        $sanitized = [];
        
        if (isset($data['title'])) {
            $sanitized['title'] = $this->sanitizer->textField((string) $data['title']);
        }
        
        if (isset($data['description'])) {
            $sanitized['description'] = $this->sanitizer->textarea((string) $data['description']);
        }
        
        if (isset($data['start_date'])) {
            $sanitized['start_date'] = $this->sanitizer->textField((string) $data['start_date']);
        }
        
        if (isset($data['end_date'])) {
            $sanitized['end_date'] = $this->sanitizer->textField((string) $data['end_date']);
        }
        
        if (isset($data['max_capacity'])) {
            $sanitized['max_capacity'] = $this->sanitizer->integer($data['max_capacity']);
        }
        
        if (isset($data['is_active'])) {
            $sanitized['is_active'] = (bool) $data['is_active'];
        }
        
        return $sanitized;
    }
}
