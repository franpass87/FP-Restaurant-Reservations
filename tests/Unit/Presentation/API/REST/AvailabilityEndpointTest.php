<?php

declare(strict_types=1);

namespace Tests\Unit\Presentation\API\REST;

use FP\Resv\Application\Availability\GetAvailabilityUseCase;
use FP\Resv\Core\Services\LoggerInterface;
use FP\Resv\Core\Services\SanitizerInterface;
use FP\Resv\Presentation\API\REST\AvailabilityEndpoint;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\MockObject\MockObject;
use WP_REST_Request;
use WP_REST_Response;
use WP_Error;

/**
 * Test for AvailabilityEndpoint
 */
final class AvailabilityEndpointTest extends TestCase
{
    private LoggerInterface&MockObject $logger;
    private GetAvailabilityUseCase&MockObject $getAvailabilityUseCase;
    private SanitizerInterface&MockObject $sanitizer;
    private AvailabilityEndpoint $endpoint;

    protected function setUp(): void
    {
        parent::setUp();

        $this->logger = $this->createMock(LoggerInterface::class);
        $this->getAvailabilityUseCase = $this->createMock(GetAvailabilityUseCase::class);
        $this->sanitizer = $this->createMock(SanitizerInterface::class);

        $this->endpoint = new AvailabilityEndpoint(
            $this->logger,
            $this->getAvailabilityUseCase,
            $this->sanitizer
        );
    }

    public function testGetAvailabilityWithValidCriteria(): void
    {
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_param')->willReturnMap([
            ['date', '2025-12-20'],
            ['party', 4],
            ['meal', 'dinner'],
        ]);

        $expectedResult = [
            'date' => '2025-12-20',
            'slots' => [
                ['time' => '19:00', 'available' => true],
                ['time' => '20:00', 'available' => true],
            ],
        ];

        $this->sanitizer
            ->method('textField')
            ->willReturnArgument(0);
        $this->sanitizer
            ->method('integer')
            ->willReturnArgument(0);

        $this->getAvailabilityUseCase
            ->expects($this->once())
            ->method('execute')
            ->willReturn($expectedResult);

        $result = $this->endpoint->getAvailability($request);

        $this->assertInstanceOf(WP_REST_Response::class, $result);
        $this->assertEquals(200, $result->get_status());
    }

    public function testGetAvailabilityWithMissingDate(): void
    {
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_param')->willReturnMap([
            ['date', ''],
            ['party', 4],
        ]);

        $this->sanitizer
            ->method('textField')
            ->willReturn('');
        $this->sanitizer
            ->method('integer')
            ->willReturn(4);

        $result = $this->endpoint->getAvailability($request);

        $this->assertInstanceOf(WP_Error::class, $result);
    }

    public function testGetAvailabilityWithInvalidPartySize(): void
    {
        $request = $this->createMock(WP_REST_Request::class);
        $request->method('get_param')->willReturnMap([
            ['date', '2025-12-20'],
            ['party', 0],
        ]);

        $this->sanitizer
            ->method('textField')
            ->willReturn('2025-12-20');
        $this->sanitizer
            ->method('integer')
            ->willReturn(0);

        $result = $this->endpoint->getAvailability($request);

        $this->assertInstanceOf(WP_Error::class, $result);
    }
}








