<?php

declare(strict_types=1);

namespace FP\Resv\Application\Closures;

use FP\Resv\Core\Exceptions\ValidationException;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Core\Services\ValidatorInterface;
use FP\Resv\Domain\Closures\Models\Closure;
use FP\Resv\Domain\Closures\Repositories\ClosureRepositoryInterface;
use DateTimeImmutable;

/**
 * Update Closure Use Case
 * 
 * Orchestrates the update of an existing restaurant closure.
 *
 * @package FP\Resv\Application\Closures
 */
final class UpdateClosureUseCase
{
    public function __construct(
        private readonly ClosureRepositoryInterface $closureRepository,
        private readonly ValidatorInterface $validator,
        private readonly LoggerInterface $logger
    ) {
    }
    
    /**
     * Execute the use case
     * 
     * @param int $id Closure ID
     * @param array<string, mixed> $data Update data
     * @return Closure Updated closure
     * @throws ValidationException If validation fails
     */
    public function execute(int $id, array $data): Closure
    {
        // Find existing closure
        $closure = $this->closureRepository->findById($id);
        
        if ($closure === null) {
            throw new \RuntimeException("Closure with ID {$id} not found");
        }
        
        // Validate update data
        $this->validate($data, $closure);
        
        // Log the update attempt
        $this->logger->info('Updating closure', [
            'id' => $id,
            'changes' => array_keys($data),
        ]);
        
        // Merge current data with updates
        $currentData = $closure->toArray();
        $mergedData = array_merge($currentData, $data);
        
        // Recreate closure from merged data
        $startDate = isset($mergedData['start_date'])
            ? DateTimeImmutable::createFromFormat('Y-m-d', $mergedData['start_date'])
            : $closure->getStartDate();
        $endDate = isset($mergedData['end_date'])
            ? DateTimeImmutable::createFromFormat('Y-m-d', $mergedData['end_date'])
            : $closure->getEndDate();
        
        $updatedClosure = new Closure(
            $mergedData['title'] ?? $closure->getTitle(),
            $startDate,
            $endDate,
            $mergedData['scope'] ?? $closure->getScope()
        );
        
        $updatedClosure->setId($id);
        
        if (isset($mergedData['room_id'])) {
            $updatedClosure->setRoomId((int) $mergedData['room_id']);
        }
        
        if (isset($mergedData['table_id'])) {
            $updatedClosure->setTableId((int) $mergedData['table_id']);
        }
        
        if (isset($mergedData['is_recurring'])) {
            $updatedClosure->setRecurring(
                (bool) $mergedData['is_recurring'],
                $mergedData['recurrence_rule'] ?? null
            );
        }
        
        if (isset($mergedData['is_active'])) {
            $updatedClosure->setActive((bool) $mergedData['is_active']);
        }
        
        // Save via repository
        $savedClosure = $this->closureRepository->save($updatedClosure);
        
        // Log success
        $this->logger->info('Closure updated', [
            'id' => $savedClosure->getId(),
        ]);
        
        return $savedClosure;
    }
    
    /**
     * Validate update data
     * 
     * @param array<string, mixed> $data Update data
     * @param Closure $closure Existing closure
     * @return void
     * @throws ValidationException If validation fails
     */
    private function validate(array $data, Closure $closure): void
    {
        $errors = [];
        
        // Validate dates if provided
        if (isset($data['start_date']) && !$this->validator->isDate($data['start_date'])) {
            $errors['start_date'] = 'Invalid start date format';
        }
        
        if (isset($data['end_date']) && !$this->validator->isDate($data['end_date'])) {
            $errors['end_date'] = 'Invalid end date format';
        }
        
        // Validate date range
        $startDate = isset($data['start_date'])
            ? DateTimeImmutable::createFromFormat('Y-m-d', $data['start_date'])
            : $closure->getStartDate();
        $endDate = isset($data['end_date'])
            ? DateTimeImmutable::createFromFormat('Y-m-d', $data['end_date'])
            : $closure->getEndDate();
        
        if ($startDate && $endDate && $endDate < $startDate) {
            $errors['end_date'] = 'End date must be on or after start date';
        }
        
        // Validate scope
        $validScopes = ['all', 'room', 'table'];
        if (isset($data['scope']) && !in_array($data['scope'], $validScopes, true)) {
            $errors['scope'] = 'Scope must be one of: ' . implode(', ', $validScopes);
        }
        
        if ($errors !== []) {
            throw new ValidationException('Closure update validation failed', $errors);
        }
    }
}
