<?php

declare(strict_types=1);

namespace FP\Resv\Application\Availability;

use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Domain\Reservations\Services\AvailabilityServiceInterface;

/**
 * Get Availability Use Case
 * 
 * Orchestrates the retrieval of availability information.
 *
 * @package FP\Resv\Application\Availability
 */
final class GetAvailabilityUseCase
{
    public function __construct(
        private readonly AvailabilityServiceInterface $availabilityService,
        private readonly LoggerInterface $logger
    ) {
    }
    
    /**
     * Execute the use case
     * 
     * @param array<string, mixed> $criteria Search criteria (date, meal, party, etc.)
     * @return array<string, mixed> Availability data
     */
    public function execute(array $criteria): array
    {
        $this->logger->debug('Getting availability', [
            'criteria' => $criteria,
        ]);
        
        // Delegate to domain service
        $availability = $this->availabilityService->findSlots($criteria);
        
        $this->logger->debug('Availability retrieved', [
            'slots_count' => count($availability['slots'] ?? []),
        ]);
        
        return $availability;
    }
}










