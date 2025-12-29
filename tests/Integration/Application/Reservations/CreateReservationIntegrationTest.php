<?php

declare(strict_types=1);

namespace Tests\Integration\Application\Reservations;

use FP\Resv\Application\Reservations\CreateReservationUseCase;
use FP\Resv\Core\Exceptions\ValidationException;
use FP\Resv\Domain\Reservations\Models\Reservation;
use FP\Resv\Domain\Reservations\Repositories\ReservationRepositoryInterface;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeWpdb;

/**
 * Integration test for CreateReservationUseCase
 * 
 * Tests the full flow from Use Case to Repository
 */
final class CreateReservationIntegrationTest extends TestCase
{
    private FakeWpdb $wpdb;
    private CreateReservationUseCase $useCase;
    private ReservationRepositoryInterface $repository;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;
        
        // Setup WordPress options
        update_option('fp_resv_general', [
            'default_reservation_status' => 'pending',
            'restaurant_name' => 'Test Restaurant',
        ]);
        
        // Get real instances from container (would need proper container setup in real test)
        // For now, we'll test with mocked dependencies
        $this->markTestSkipped('Requires full container setup - to be implemented');
    }

    public function testCreateReservationFullFlow(): void
    {
        $this->markTestSkipped('Requires full container setup');
        
        $data = [
            'date' => '2025-12-25',
            'time' => '20:00',
            'party' => 4,
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'email' => 'mario.rossi@example.com',
            'phone' => '+39 123 456 7890',
            'meal' => 'dinner',
        ];
        
        $reservation = $this->useCase->execute($data);
        
        $this->assertInstanceOf(Reservation::class, $reservation);
        $this->assertGreaterThan(0, $reservation->getId());
        $this->assertEquals('2025-12-25', $reservation->getDate());
        $this->assertEquals('20:00', $reservation->getTime());
        $this->assertEquals(4, $reservation->getParty());
        
        // Verify reservation was saved
        $saved = $this->repository->findById($reservation->getId());
        $this->assertNotNull($saved);
        $this->assertEquals($reservation->getId(), $saved->getId());
    }

    public function testCreateReservationWithInvalidData(): void
    {
        $this->markTestSkipped('Requires full container setup');
        
        $data = [
            'date' => 'invalid-date',
            'time' => '20:00',
            'party' => 4,
        ];
        
        $this->expectException(ValidationException::class);
        $this->useCase->execute($data);
    }
}







