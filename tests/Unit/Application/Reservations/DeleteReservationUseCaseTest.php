<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Reservations;

use FP\Resv\Application\Reservations\DeleteReservationUseCase;
use FP\Resv\Core\Exceptions\ValidationException;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Domain\Reservations\Repositories\ReservationRepositoryInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test for DeleteReservationUseCase
 */
final class DeleteReservationUseCaseTest extends TestCase
{
    private ReservationRepositoryInterface&MockObject $repository;
    private LoggerInterface&MockObject $logger;
    private DeleteReservationUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->repository = $this->createMock(ReservationRepositoryInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->useCase = new DeleteReservationUseCase(
            $this->repository,
            $this->logger
        );
    }

    public function testExecuteWithValidId(): void
    {
        $id = 123;

        $reservation = $this->createMock(\FP\Resv\Domain\Reservations\Models\Reservation::class);

        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn($reservation);

        $this->repository
            ->expects($this->once())
            ->method('delete')
            ->with($id)
            ->willReturn(true);

        $this->logger
            ->expects($this->exactly(2))
            ->method('info');

        $result = $this->useCase->execute($id);

        $this->assertTrue($result);
    }

    public function testExecuteWithNonExistentReservation(): void
    {
        $id = 999;

        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn(null);

        $this->logger
            ->expects($this->once())
            ->method('warning');

        $this->repository
            ->expects($this->never())
            ->method('delete');

        $result = $this->useCase->execute($id);

        $this->assertFalse($result);
    }

    public function testExecuteWithDeleteFailure(): void
    {
        $id = 123;

        $reservation = $this->createMock(\FP\Resv\Domain\Reservations\Models\Reservation::class);

        $this->repository
            ->expects($this->once())
            ->method('findById')
            ->with($id)
            ->willReturn($reservation);

        $this->repository
            ->expects($this->once())
            ->method('delete')
            ->with($id)
            ->willReturn(false);

        $this->logger
            ->expects($this->once())
            ->method('info');
        $this->logger
            ->expects($this->once())
            ->method('error');

        $result = $this->useCase->execute($id);

        $this->assertFalse($result);
    }
}

