<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Availability;

use FP\Resv\Application\Availability\GetAvailabilityUseCase;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Domain\Reservations\Services\AvailabilityServiceInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test for GetAvailabilityUseCase
 */
final class GetAvailabilityUseCaseTest extends TestCase
{
    private AvailabilityServiceInterface&MockObject $availabilityService;
    private LoggerInterface&MockObject $logger;
    private GetAvailabilityUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->availabilityService = $this->createMock(AvailabilityServiceInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->useCase = new GetAvailabilityUseCase(
            $this->availabilityService,
            $this->logger
        );
    }

    public function testExecuteWithValidCriteria(): void
    {
        $criteria = [
            'date' => '2025-12-20',
            'party' => 4,
            'meal' => 'dinner',
        ];

        $expectedResult = [
            'date' => '2025-12-20',
            'slots' => [
                ['time' => '19:00', 'available' => true],
                ['time' => '20:00', 'available' => true],
                ['time' => '21:00', 'available' => false],
            ],
        ];

        $this->logger
            ->expects($this->exactly(2))
            ->method('debug');

        $this->availabilityService
            ->expects($this->once())
            ->method('findSlots')
            ->with($criteria)
            ->willReturn($expectedResult);

        $result = $this->useCase->execute($criteria);

        $this->assertEquals($expectedResult, $result);
        $this->assertArrayHasKey('date', $result);
        $this->assertArrayHasKey('slots', $result);
    }
}

