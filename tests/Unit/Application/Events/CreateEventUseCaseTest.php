<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Events;

use FP\Resv\Application\Events\CreateEventUseCase;
use FP\Resv\Core\Exceptions\ValidationException;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Core\Services\ValidatorInterface;
use FP\Resv\Domain\Events\Models\Event;
use FP\Resv\Domain\Events\Services\EventServiceInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test for CreateEventUseCase
 */
final class CreateEventUseCaseTest extends TestCase
{
    private EventServiceInterface&MockObject $eventService;
    private ValidatorInterface&MockObject $validator;
    private LoggerInterface&MockObject $logger;
    private CreateEventUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->eventService = $this->createMock(EventServiceInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->useCase = new CreateEventUseCase(
            $this->eventService,
            $this->validator,
            $this->logger
        );
    }

    public function testExecuteWithValidData(): void
    {
        $data = [
            'title' => 'Evento Speciale',
            'date' => '2025-12-25',
            'start_time' => '19:00',
            'end_time' => '23:00',
            'description' => 'Descrizione evento',
        ];

        $event = $this->createMock(Event::class);
        $event->method('getId')->willReturn(123);
        $event->method('getTitle')->willReturn('Evento Speciale');
        $event->method('getDate')->willReturn('2025-12-25');

        $this->validator
            ->method('isRequired')
            ->willReturn(true);
        $this->validator
            ->method('isDate')
            ->willReturn(true);
        $this->validator
            ->method('isTime')
            ->willReturn(true);

        $this->eventService
            ->expects($this->once())
            ->method('create')
            ->with($data)
            ->willReturn($event);

        $this->logger
            ->expects($this->exactly(2))
            ->method('info');

        $result = $this->useCase->execute($data);

        $this->assertInstanceOf(Event::class, $result);
        $this->assertEquals(123, $result->getId());
    }

    public function testExecuteWithMissingTitle(): void
    {
        $data = [
            'date' => '2025-12-25',
        ];

        $this->validator
            ->method('isRequired')
            ->willReturnCallback(function ($value, $key = null) use ($data) {
                return isset($data['title']) && $data['title'] !== null;
            });

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Event validation failed');

        $this->useCase->execute($data);
    }
}




