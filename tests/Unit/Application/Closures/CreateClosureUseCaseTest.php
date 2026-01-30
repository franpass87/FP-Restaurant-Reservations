<?php

declare(strict_types=1);

namespace Tests\Unit\Application\Closures;

use FP\Resv\Application\Closures\CreateClosureUseCase;
use FP\Resv\Core\Exceptions\ValidationException;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Core\Services\ValidatorInterface;
use FP\Resv\Domain\Closures\Models\Closure;
use FP\Resv\Domain\Closures\Services\ClosureServiceInterface;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;

/**
 * Test for CreateClosureUseCase
 */
final class CreateClosureUseCaseTest extends TestCase
{
    private ClosureServiceInterface&MockObject $closureService;
    private ValidatorInterface&MockObject $validator;
    private LoggerInterface&MockObject $logger;
    private CreateClosureUseCase $useCase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->closureService = $this->createMock(ClosureServiceInterface::class);
        $this->validator = $this->createMock(ValidatorInterface::class);
        $this->logger = $this->createMock(LoggerInterface::class);

        $this->useCase = new CreateClosureUseCase(
            $this->closureService,
            $this->validator,
            $this->logger
        );
    }

    public function testExecuteWithValidData(): void
    {
        $data = [
            'date' => '2025-12-25',
            'reason' => 'Chiusura per festività',
        ];

        $closure = $this->createMock(Closure::class);
        $closure->method('getId')->willReturn(123);
        $closure->method('getDate')->willReturn('2025-12-25');
        $closure->method('getReason')->willReturn('Chiusura per festività');

        $this->validator
            ->method('isRequired')
            ->willReturn(true);
        $this->validator
            ->method('isDate')
            ->willReturn(true);

        $this->closureService
            ->expects($this->once())
            ->method('create')
            ->with($data)
            ->willReturn($closure);

        $this->logger
            ->expects($this->exactly(2))
            ->method('info');

        $result = $this->useCase->execute($data);

        $this->assertInstanceOf(Closure::class, $result);
        $this->assertEquals(123, $result->getId());
    }

    public function testExecuteWithMissingDate(): void
    {
        $data = [
            'reason' => 'Chiusura per festività',
        ];

        $this->validator
            ->method('isRequired')
            ->willReturnCallback(function ($value, $key = null) use ($data) {
                return isset($data['date']) && $data['date'] !== null;
            });

        $this->expectException(ValidationException::class);
        $this->expectExceptionMessage('Closure validation failed');

        $this->useCase->execute($data);
    }
}








