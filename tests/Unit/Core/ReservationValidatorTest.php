<?php

declare(strict_types=1);

namespace FP\Resv\Tests\Unit\Core;

use DateTimeImmutable;
use FP\Resv\Core\Exceptions\InvalidDateException;
use FP\Resv\Core\ReservationValidator;
use PHPUnit\Framework\TestCase;

class ReservationValidatorTest extends TestCase
{
    private ReservationValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->validator = new ReservationValidator();
    }

    public function testAssertValidDateAcceptsToday(): void
    {
        $today = date('Y-m-d');
        
        $this->expectNotToPerformAssertions();
        $this->validator->assertValidDate($today);
    }

    public function testAssertValidDateAcceptsFutureDate(): void
    {
        $tomorrow = date('Y-m-d', strtotime('+1 day'));
        
        $this->expectNotToPerformAssertions();
        $this->validator->assertValidDate($tomorrow);
    }

    public function testAssertValidDateRejectsPastDate(): void
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $this->expectException(InvalidDateException::class);
        $this->expectExceptionMessage('Non è possibile prenotare per giorni passati.');
        
        $this->validator->assertValidDate($yesterday);
    }

    public function testAssertValidDateRejectsOldPastDate(): void
    {
        $lastWeek = date('Y-m-d', strtotime('-7 days'));
        
        $this->expectException(InvalidDateException::class);
        $this->expectExceptionMessage('Non è possibile prenotare per giorni passati.');
        
        $this->validator->assertValidDate($lastWeek);
    }

    public function testAssertValidDateRejectsInvalidFormat(): void
    {
        $this->expectException(InvalidDateException::class);
        $this->expectExceptionMessage('Formato data non valido. Utilizzare YYYY-MM-DD.');
        
        $this->validator->assertValidDate('2024/01/01');
    }

    public function testAssertValidDateRejectsInvalidDate(): void
    {
        $this->expectException(InvalidDateException::class);
        $this->expectExceptionMessage('La data specificata non è valida.');
        
        $this->validator->assertValidDate('2024-02-30');
    }

    public function testValidateReturnsFalseForPastDate(): void
    {
        $yesterday = date('Y-m-d', strtotime('-1 day'));
        
        $payload = [
            'date' => $yesterday,
            'time' => '19:00',
            'party' => 2,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com'
        ];
        
        $result = $this->validator->validate($payload);
        
        $this->assertFalse($result);
        $errors = $this->validator->getErrors();
        $this->assertArrayHasKey('date', $errors);
        $this->assertEquals('Non è possibile prenotare per giorni passati.', $errors['date']);
    }

    public function testValidateReturnsTrueForToday(): void
    {
        $today = date('Y-m-d');
        
        $payload = [
            'date' => $today,
            'time' => '19:00',
            'party' => 2,
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'test@example.com'
        ];
        
        $result = $this->validator->validate($payload);
        
        $this->assertTrue($result);
        $this->assertEmpty($this->validator->getErrors());
    }
}
