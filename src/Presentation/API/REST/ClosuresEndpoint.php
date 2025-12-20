<?php

declare(strict_types=1);

namespace FP\Resv\Presentation\API\REST;

use FP\Resv\Application\Closures\CreateClosureUseCase;
use FP\Resv\Application\Closures\DeleteClosureUseCase;
use FP\Resv\Application\Closures\UpdateClosureUseCase;
use FP\Resv\Core\Exceptions\ValidationException;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Core\Services\SanitizerInterface;
use FP\Resv\Domain\Closures\Repositories\ClosureRepositoryInterface;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Closures REST Endpoint
 * 
 * Thin controller for closure REST API operations.
 * Delegates business logic to Use Cases.
 *
 * @package FP\Resv\Presentation\API\REST
 */
final class ClosuresEndpoint extends BaseEndpoint
{
    public function __construct(
        LoggerInterface $logger,
        private readonly CreateClosureUseCase $createUseCase,
        private readonly UpdateClosureUseCase $updateUseCase,
        private readonly DeleteClosureUseCase $deleteUseCase,
        private readonly ClosureRepositoryInterface $repository,
        private readonly SanitizerInterface $sanitizer
    ) {
        parent::__construct($logger);
    }
    
    /**
     * Create a closure
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function create(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            // Sanitize input
            $data = $this->sanitizeClosureData($request->get_json_params() ?? []);
            
            // Execute use case
            $closure = $this->createUseCase->execute($data);
            
            // Return response
            return $this->success([
                'id' => $closure->getId(),
                'title' => $closure->getTitle(),
                'start_date' => $closure->getStartDate()->format('Y-m-d'),
                'end_date' => $closure->getEndDate()->format('Y-m-d'),
                'scope' => $closure->getScope(),
                'message' => 'Closure created successfully',
            ], 201);
        } catch (ValidationException $e) {
            return $this->handleValidationException($e);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Update a closure
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function update(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $id = (int) $this->getParam($request, 'id');
            
            if ($id <= 0) {
                return $this->error('invalid_id', 'Invalid closure ID', 400);
            }
            
            // Sanitize input
            $data = $this->sanitizeClosureData($request->get_json_params() ?? []);
            
            // Execute use case
            $closure = $this->updateUseCase->execute($id, $data);
            
            // Return response
            return $this->success([
                'id' => $closure->getId(),
                'title' => $closure->getTitle(),
                'start_date' => $closure->getStartDate()->format('Y-m-d'),
                'end_date' => $closure->getEndDate()->format('Y-m-d'),
                'scope' => $closure->getScope(),
                'message' => 'Closure updated successfully',
            ]);
        } catch (ValidationException $e) {
            return $this->handleValidationException($e);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Delete a closure
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function delete(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $id = (int) $this->getParam($request, 'id');
            
            if ($id <= 0) {
                return $this->error('invalid_id', 'Invalid closure ID', 400);
            }
            
            // Execute use case
            $success = $this->deleteUseCase->execute($id);
            
            if (!$success) {
                return $this->error('not_found', 'Closure not found', 404);
            }
            
            // Return response
            return $this->success([
                'id' => $id,
                'message' => 'Closure deleted successfully',
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Get a closure
     * 
     * @param WP_REST_Request $request Request object
     * @return WP_REST_Response|WP_Error
     */
    public function get(WP_REST_Request $request): WP_REST_Response|WP_Error
    {
        try {
            $id = (int) $this->getParam($request, 'id');
            
            if ($id <= 0) {
                return $this->error('invalid_id', 'Invalid closure ID', 400);
            }
            
            $closure = $this->repository->findById($id);
            
            if ($closure === null) {
                return $this->error('not_found', 'Closure not found', 404);
            }
            
            return $this->success($closure->toArray());
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * List closures
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
            
            if ($request->get_param('scope')) {
                $criteria['scope'] = $this->sanitizer->textField($request->get_param('scope'));
            }
            
            if ($request->get_param('room_id')) {
                $criteria['room_id'] = (int) $request->get_param('room_id');
            }
            
            if ($request->get_param('table_id')) {
                $criteria['table_id'] = (int) $request->get_param('table_id');
            }
            
            if ($request->get_param('start_date_from')) {
                $criteria['start_date_from'] = $this->sanitizer->textField($request->get_param('start_date_from'));
            }
            
            if ($request->get_param('start_date_to')) {
                $criteria['start_date_to'] = $this->sanitizer->textField($request->get_param('start_date_to'));
            }
            
            $limit = (int) ($request->get_param('per_page') ?? 100);
            $offset = (int) ($request->get_param('offset') ?? 0);
            
            $closures = $this->repository->findBy($criteria, $limit, $offset);
            
            return $this->success([
                'closures' => array_map(fn($closure) => $closure->toArray(), $closures),
                'count' => count($closures),
            ]);
        } catch (\Throwable $e) {
            return $this->handleException($e);
        }
    }
    
    /**
     * Sanitize closure data
     * 
     * @param array<string, mixed> $data Raw data
     * @return array<string, mixed> Sanitized data
     */
    private function sanitizeClosureData(array $data): array
    {
        $sanitized = [];
        
        if (isset($data['title'])) {
            $sanitized['title'] = $this->sanitizer->textField((string) $data['title']);
        }
        
        if (isset($data['start_date'])) {
            $sanitized['start_date'] = $this->sanitizer->textField((string) $data['start_date']);
        }
        
        if (isset($data['end_date'])) {
            $sanitized['end_date'] = $this->sanitizer->textField((string) $data['end_date']);
        }
        
        if (isset($data['scope'])) {
            $sanitized['scope'] = $this->sanitizer->textField((string) $data['scope']);
        }
        
        if (isset($data['room_id'])) {
            $sanitized['room_id'] = $this->sanitizer->integer($data['room_id']);
        }
        
        if (isset($data['table_id'])) {
            $sanitized['table_id'] = $this->sanitizer->integer($data['table_id']);
        }
        
        if (isset($data['is_recurring'])) {
            $sanitized['is_recurring'] = (bool) $data['is_recurring'];
        }
        
        if (isset($data['recurrence_rule'])) {
            $sanitized['recurrence_rule'] = $this->sanitizer->textField((string) $data['recurrence_rule']);
        }
        
        if (isset($data['is_active'])) {
            $sanitized['is_active'] = (bool) $data['is_active'];
        }
        
        return $sanitized;
    }
}
