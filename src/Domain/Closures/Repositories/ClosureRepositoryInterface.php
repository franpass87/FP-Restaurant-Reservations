<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Closures\Repositories;

use FP\Resv\Domain\Closures\Models\Closure;

/**
 * Closure Repository Interface
 * 
 * Defines the contract for closure data access.
 *
 * @package FP\Resv\Domain\Closures\Repositories
 */
interface ClosureRepositoryInterface
{
    /**
     * Find a closure by ID
     * 
     * @param int $id Closure ID
     * @return Closure|null
     */
    public function findById(int $id): ?Closure;
    
    /**
     * Find closures by criteria
     * 
     * @param array<string, mixed> $criteria Search criteria
     * @param int $limit Maximum number of results
     * @param int $offset Offset for pagination
     * @return array<Closure>
     */
    public function findBy(array $criteria, int $limit = 100, int $offset = 0): array;
    
    /**
     * Save a closure (create or update)
     * 
     * @param Closure $closure Closure to save
     * @return Closure Saved closure with ID
     */
    public function save(Closure $closure): Closure;
    
    /**
     * Delete a closure
     * 
     * @param int $id Closure ID
     * @return bool Success status
     */
    public function delete(int $id): bool;
}










