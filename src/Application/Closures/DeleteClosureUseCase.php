<?php

declare(strict_types=1);

namespace FP\Resv\Application\Closures;

use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Domain\Closures\Repositories\ClosureRepositoryInterface;

/**
 * Delete Closure Use Case
 * 
 * Orchestrates the deletion of a restaurant closure.
 *
 * @package FP\Resv\Application\Closures
 */
final class DeleteClosureUseCase
{
    public function __construct(
        private readonly ClosureRepositoryInterface $closureRepository,
        private readonly LoggerInterface $logger
    ) {
    }
    
    /**
     * Execute the use case
     * 
     * @param int $id Closure ID
     * @return bool Success status
     */
    public function execute(int $id): bool
    {
        // Check if closure exists
        $closure = $this->closureRepository->findById($id);
        
        if ($closure === null) {
            $this->logger->warning('Attempted to delete non-existent closure', ['id' => $id]);
            return false;
        }
        
        // Log the deletion attempt
        $this->logger->info('Deleting closure', [
            'id' => $id,
            'title' => $closure->getTitle(),
        ]);
        
        // Delete via repository
        $deleted = $this->closureRepository->delete($id);
        
        if ($deleted) {
            $this->logger->info('Closure deleted', ['id' => $id]);
        } else {
            $this->logger->error('Failed to delete closure', ['id' => $id]);
        }
        
        return $deleted;
    }
}

