<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Reservations;

use FP\Resv\Application\Reservations\UpdateReservationUseCase;
use FP\Resv\Core\Exceptions\ValidationException;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Core\Services\ValidatorInterface;
use FP\Resv\Domain\Reservations\Models\Reservation;
use FP\Resv\Domain\Reservations\Services\ReservationServiceInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test for UpdateReservationUseCase
 */
final class UpdateReservationUseCaseTest extends TestCase
{
    private ReservationServiceInterface&MockObject $reservationService;
    private ValidatorInterface&MockObject $validator;
    private LoggerInterface&MockObject $logger;
    private UpdateReservationUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->reservationService = $this->createMock(ReservationServiceInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->useCase = new UpdateReservationUseCase(
            $this->reservationService,
            $this->validator,
            $this->logger
        );
    }

    public function testExecuteWithValidData(): void
    {
        $id = 123;
        $data = [
            'date' => '2025-12-21',
            'time' => '21:00',
            'party' => 6,
        ];

        $reservation = $this->createMock(Reservation::class);
        $reservation->method('getId')->willReturn($id);
        $reservation->method('getDate')->willReturn('2025-12-21');
        $reservation->method('getTime')->willReturn('21:00');
        $reservation->method('getParty')->willReturn(6);
        $reservation->method('getStatus')->willReturn('confirmed');

        $this->validator
            ->method('isDate')
            ->willReturn(true);
        $this->validator
            ->method('isTime')
            ->willReturn(true);

        $this->reservationService
            ->expects($this->once())
            ->method('update')
            ->with($id, $data)
            ->willReturn($reservation);

        $this->logger
            ->expects($this->exactly(2))
            ->method('info');

        $result = $this->useCase->execute($id, $data);

        $this->assertInstanceOf(Reservation::class, $result);
        $this->assertEquals($id, $result->getId());
    }

    public function testExecuteWithServiceThrowingException(): void
    {
        $id = 999;
        $data = ['date' => '2025-12-21'];

        $this->validator
            ->method('isDate')
            ->willReturn(true);

        $this->reservationService
            ->expects($this->once())
            ->method('update')
            ->with($id, $data)
            ->willThrowException(new ValidationException('Reservation not found'));

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Reservation not found');

        $this->useCase->execute($id, $data);
    }

    public function testExecuteWithInvalidDate(): void
    {
        $id = 123;
        $data = ['date' => 'invalid-date'];

        $this->validator
            ->method('isDate')
            ->with('invalid-date')
            ->willReturn(false);

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Reservation validation failed');

        $this->useCase->execute($id, $data);
    }
}

