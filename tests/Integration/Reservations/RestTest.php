<?php

declare(strict_types=1);

namespace Tests\Integration\Reservations;

use FP\Resv\Domain\Reservations\Availability;
use FP\Resv\Domain\Reservations\REST;
use FP\Resv\Domain\Reservations\Service;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use WP_Error;
use WP_REST_Request;
use WP_REST_Response;

final class RestTest extends TestCase
{
    /** @var Availability&MockObject */
    private Availability $availability;

    /** @var Service&MockObject */
    private Service $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->availability = $this->createMock(Availability::class);
        $this->service      = $this->createMock(Service::class);
    }

    public function testHandleCreateReservationRequiresConsent(): void
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.11';

        $request = new WP_REST_Request([
            'fp_resv_nonce' => 'valid-nonce',
            'date'          => '2024-05-10',
            'time'          => '20:00',
            'party'         => 2,
        ]);

        $rest   = new REST($this->availability, $this->service);
        $result = $rest->handleCreateReservation($request);

        self::assertInstanceOf(WP_Error::class, $result);
        self::assertSame('fp_resv_missing_consent', $result->code);
    }

    public function testHandleCreateReservationReturnsResponseOnSuccess(): void
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.20';

        $request = new WP_REST_Request([
            'fp_resv_nonce'    => 'valid-nonce',
            'fp_resv_consent'  => 'yes',
            'fp_resv_party'    => 3,
            'fp_resv_first_name' => 'Ada',
            'fp_resv_last_name'  => 'Lovelace',
            'fp_resv_email'      => 'ada@example.test',
            'fp_resv_date'       => '2024-05-11',
            'fp_resv_time'       => '21:00',
        ]);

        $this->service
            ->expects(self::once())
            ->method('create')
            ->with(self::callback(function (array $payload): bool {
                return $payload['party'] === 3
                    && $payload['first_name'] === 'Ada'
                    && $payload['last_name'] === 'Lovelace'
                    && $payload['email'] === 'ada@example.test';
            }))
            ->willReturn([
                'id'         => 123,
                'status'     => 'pending',
                'manage_url' => 'https://example.test?fp_resv_manage=123',
            ]);

        $rest   = new REST($this->availability, $this->service);
        $result = $rest->handleCreateReservation($request);

        self::assertInstanceOf(WP_REST_Response::class, $result);
        self::assertSame(201, $result->get_status());
        $data = $result->get_data();
        self::assertSame('Prenotazione inviata con successo.', $data['message']);
        self::assertSame(123, $data['reservation']['id']);
    }
}

