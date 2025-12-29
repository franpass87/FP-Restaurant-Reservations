<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\API\REST;

use FP\Resv\Application\Reservations\CreateReservationUseCase;
use FP\Resv\Application\Reservations\DeleteReservationUseCase;
use FP\Resv\Application\Reservations\UpdateReservationUseCase;
use FP\Resv\Core\Exceptions\ValidationException;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Core\Services\SanitizerInterface;
use FP\Resv\Domain\Reservations\Models\Reservation;
use FP\Resv\Domain\Reservations\Repositories\ReservationRepositoryInterface;
use FP\Resv\Presentation\API\REST\ReservationsEndpoint;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Test for ReservationsEndpoint
 */
final class ReservationsEndpointTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private CreateReservationUseCase&MockObject $createUseCase;
    private UpdateReservationUseCase&MockObject $updateUseCase;
    private DeleteReservationUseCase&MockObject $deleteUseCase;
    private ReservationRepositoryInterface&MockObject $repository;
    private SanitizerInterface&MockObject $sanitizer;
    private ReservationsEndpoint $endpoint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->createUseCase = $this->createMock(CreateReservationUseCase::class);
        $this->updateUseCase = $this->createMock(UpdateReservationUseCase::class);
        $this->deleteUseCase = $this->createMock(DeleteReservationUseCase::class);
        $this->repository = $this->createMock(ReservationRepositoryInterface::class);
        $this->sanitizer = $this->createMock(SanitizerInterface::class);

        $this->endpoint = new ReservationsEndpoint(
            $this->logger,
            $this->createUseCase,
            $this->updateUseCase,
            $this->deleteUseCase,
            $this->repository,
            $this->sanitizer
        );
    }

    public function testCreateWithValidData(): void
    {
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_json_params')->willReturn([
            'date' => '2025-12-20',
            'time' => '20:00',
            'party' => 4,
            'first_name' => 'Mario',
            'last_name' => 'Rossi',
            'email' => 'mario.rossi@example.com',
        ]);

        $reservation = $this->createMock(Reservation::class);
        $reservation->method('getId')->willReturn(123);
        $reservation->method('getDate')->willReturn('2025-12-20');
        $reservation->method('getTime')->willReturn('20:00');
        $reservation->method('getParty')->willReturn(4);
        $reservation->method('getStatus')->willReturn('pending');

        $this->sanitizer
            ->method('textField')
            ->willReturnArgument(0);
        $this->sanitizer
            ->method('integer')
            ->willReturnArgument(0);
        $this->sanitizer
            ->method('email')
            ->willReturnArgument(0);

        $this->createUseCase
            ->expects($this->once())
            ->method('execute')
            ->willReturn($reservation);

        $result = $this->endpoint->create($request);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertEquals(201, $result->get_status());
    }

    public function testCreateWithValidationError(): void
    {
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_json_params')->willReturn([
            'date' => 'invalid',
        ]);

        $this->sanitizer
            ->method('textField')
            ->willReturnArgument(0);

        $this->createUseCase
            ->expects($this->once())
            ->method('execute')
            ->willThrowException(new ValidationException('Validation failed', ['date' => 'Invalid date']));

        $result = $this->endpoint->create($request);

        $this->assertInstanceOf(WP_Error::class, $result);
    }

    public function testUpdateWithValidData(): void
    {
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_param')->willReturnMap([
            ['id', 123],
        ]);
        $request->method('get_json_params')->willReturn([
            'date' => '2025-12-21',
            'time' => '21:00',
        ]);

        $reservation = $this->createMock(Reservation::class);
        $reservation->method('getId')->willReturn(123);
        $reservation->method('getDate')->willReturn('2025-12-21');
        $reservation->method('getTime')->willReturn('21:00');
        $reservation->method('getParty')->willReturn(4);
        $reservation->method('getStatus')->willReturn('confirmed');

        $this->sanitizer
            ->method('textField')
            ->willReturnArgument(0);
        $this->sanitizer
            ->method('integer')
            ->willReturn(123);

        $this->updateUseCase
            ->expects($this->once())
            ->method('execute')
            ->with(123, $this->anything())
            ->willReturn($reservation);

        $result = $this->endpoint->update($request);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
    }

    public function testDeleteWithValidId(): void
    {
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_param')->willReturnMap([
            ['id', 123],
        ]);

        $this->sanitizer
            ->method('integer')
            ->willReturn(123);

        $this->deleteUseCase
            ->expects($this->once())
            ->method('execute')
            ->with(123)
            ->willReturn(true);

        $result = $this->endpoint->delete($request);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
    }
}







