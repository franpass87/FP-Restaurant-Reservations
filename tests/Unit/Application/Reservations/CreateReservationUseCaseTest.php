<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Reservations;

use FP\Resv\Application\Reservations\CreateReservationUseCase;
use FP\Resv\Core\Exceptions\ValidationException;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Core\Services\ValidatorInterface;
use FP\Resv\Domain\Reservations\Models\Reservation;
use FP\Resv\Domain\Reservations\Services\ReservationServiceInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test for CreateReservationUseCase
 */
final class CreateReservationUseCaseTest extends TestCase
{
    private ReservationServiceInterface&MockObject $reservationService;
    private ValidatorInterface&MockObject $validator;
    private LoggerInterface&MockObject $logger;
    private CreateReservationUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reservationService = $this->createMock(ReservationServiceInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->useCase = new CreateReservationUseCase(
            $this->reservationService,
            $this->validator,
            $this->logger
        );
    }

    public function testExecuteWithValidData(): void
    {
        $data = [
            'date' => '2025-12-20',
            'time' => '20:00',
            'party' => 4,
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'email' => 'mario.rossi@example.com',
            'meal' => 'dinner',
        ];

        $reservation = $this->createMock(Reservation::class);
        $reservation->method('getId')->willReturn(123);
        $reservation->method('getDate')->willReturn('2025-12-20');
        $reservation->method('getTime')->willReturn('20:00');
        $reservation->method('getParty')->willReturn(4);
        $reservation->method('getStatus')->willReturn('pending');

        // Setup validator to pass all validations
        $this->validator
            ->method('isRequired')
            ->willReturn(true);
        $this->validator
            ->method('isDate')
            ->willReturn(true);
        $this->validator
            ->method('isTime')
            ->willReturn(true);
        $this->validator
            ->method('isEmail')
            ->willReturn(true);

        $this->reservationService
            ->expects($this->once())
            ->method('create')
            ->with($data)
            ->willReturn($reservation);

        $this->logger
            ->expects($this->exactly(2))
            ->method('info');

        $result = $this->useCase->execute($data);

        $this->assertInstanceOf(Reservation::class, $result);
        $this->assertEquals(123, $result->getId());
    }

    public function testExecuteWithMissingDate(): void
    {
        $data = [
            'time' => '20:00',
            'party' => 4,
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'email' => 'mario.rossi@example.com',
        ];

        $this->validator
            ->method('isRequired')
            ->willReturnCallback(function ($value, $key = null) use ($data) {
                return isset($data['date']) && $data['date'] !== null;
            });

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Reservation validation failed');

        $this->useCase->execute($data);
    }

    public function testExecuteWithInvalidEmail(): void
    {
        $data = [
            'date' => '2025-12-20',
            'time' => '20:00',
            'party' => 4,
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'email' => 'invalid-email',
        ];

        $this->validator
            ->method('isRequired')
            ->willReturn(true);
        $this->validator
            ->method('isDate')
            ->willReturn(true);
        $this->validator
            ->method('isTime')
            ->willReturn(true);
        $this->validator
            ->method('isEmail')
            ->with('invalid-email')
            ->willReturn(false);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Reservation validation failed');

        $this->useCase->execute($data);
    }

    public function testExecuteWithInvalidPartySize(): void
    {
        $data = [
            'date' => '2025-12-20',
            'time' => '20:00',
            'party' => 0,
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'email' => 'mario.rossi@example.com',
        ];

        $this->validator
            ->method('isRequired')
            ->willReturn(true);
        $this->validator
            ->method('isDate')
            ->willReturn(true);
        $this->validator
            ->method('isTime')
            ->willReturn(true);
        $this->validator
            ->method('isEmail')
            ->willReturn(true);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Reservation validation failed');

        $this->useCase->execute($data);
    }
}




