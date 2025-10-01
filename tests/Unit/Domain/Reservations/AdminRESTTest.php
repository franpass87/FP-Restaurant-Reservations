<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Reservations;

use FP\Resv\Domain\Reservations\AdminREST;
use FP\Resv\Domain\Reservations\Repository as ReservationsRepository;
use FP\Resv\Domain\Reservations\Service;
use FP\Resv\Domain\Tables\LayoutService;
use FP\Resv\Domain\Tables\Repository as TablesRepository;
use PHPUnit\Framework\TestCase;
use WP_REST_Request;
use WP_REST_Response;
use Tests\Support\FakeWpdb;

final class AdminRESTTest extends TestCase
{
    public function testHandleAgendaBuildsDaysAndTables(): void
    {
        $wpdb = new FakeWpdb();
        $reservationsRepository = new ReservationsRepository($wpdb);
        $tablesRepository       = new TablesRepository($wpdb);
        $layout                 = new LayoutService($tablesRepository);

        $service = $this->createStub(Service::class);

        $wpdb->insert($tablesRepository->roomsTable(), [
            'id'          => 2,
            'name'        => 'Sala Principale',
            'description' => 'Sala principale',
            'color'       => '#ffffff',
            'capacity'    => 40,
            'order_index' => 1,
            'active'      => 1,
        ]);
        $wpdb->insert($tablesRepository->roomsTable(), [
            'id'          => 3,
            'name'        => 'Sala Privata',
            'description' => 'Sala privata',
            'color'       => '#000000',
            'capacity'    => 12,
            'order_index' => 2,
            'active'      => 1,
        ]);

        $wpdb->insert($tablesRepository->tablesTable(), [
            'id'          => 7,
            'room_id'     => 2,
            'code'        => 'T1',
            'seats_min'   => 2,
            'seats_std'   => 4,
            'seats_max'   => 6,
            'status'      => 'available',
            'active'      => 1,
            'order_index' => 1,
            'join_group'  => 'interni',
        ]);
        $wpdb->insert($tablesRepository->tablesTable(), [
            'id'          => 9,
            'room_id'     => 2,
            'code'        => 'T2',
            'seats_min'   => 2,
            'seats_std'   => 4,
            'seats_max'   => 4,
            'status'      => 'maintenance',
            'active'      => 0,
            'order_index' => 2,
            'join_group'  => 'interni',
        ]);
        $wpdb->insert($tablesRepository->tablesTable(), [
            'id'          => 12,
            'room_id'     => 3,
            'code'        => 'P1',
            'seats_min'   => 4,
            'seats_std'   => 6,
            'seats_max'   => 8,
            'status'      => 'available',
            'active'      => 1,
            'order_index' => 1,
        ]);

        $wpdb->insert($reservationsRepository->customersTableName(), [
            'id'         => 5,
            'first_name' => 'Alice',
            'last_name'  => 'Example',
            'email'      => 'alice@example.test',
            'phone'      => '+39012345678',
            'lang'       => 'it',
        ]);

        $wpdb->insert($reservationsRepository->tableName(), [
            'id'                    => 10,
            'customer_id'           => 5,
            'status'                => 'confirmed',
            'date'                  => '2024-08-16',
            'time'                  => '18:30:00',
            'party'                 => 2,
            'notes'                 => 'Niente cipolla',
            'allergies'             => 'Glutine',
            'room_id'               => 3,
            'table_id'              => 7,
            'visited_at'            => null,
            'calendar_event_id'     => 'evt-1',
            'calendar_sync_status'  => 'synced',
            'calendar_synced_at'    => '2024-08-01 12:00:00',
            'calendar_last_error'   => null,
        ]);

        $wpdb->insert($reservationsRepository->tableName(), [
            'id'           => 11,
            'status'       => 'pending',
            'date'         => '2024-08-16',
            'time'         => '19:15:00',
            'party'        => 4,
            'notes'        => '',
            'allergies'    => '',
            'room_id'      => null,
            'table_id'     => null,
            'customer_id'  => null,
            'email'        => 'guest@example.test',
            'customer_lang'=> 'en',
        ]);

        $rest = new AdminREST($reservationsRepository, $service, null, $layout);

        $request = new WP_REST_Request([
            'date' => '2024-08-16',
            'range' => 'week',
        ]);

        $response = $rest->handleAgenda($request);

        self::assertInstanceOf(WP_REST_Response::class, $response);
        $data = $response->get_data();

        self::assertSame([
            'mode' => 'week',
            'start' => '2024-08-16',
            'end' => '2024-08-22',
        ], $data['range']);
        self::assertCount(2, $data['reservations']);
        self::assertSame('confirmed', $data['reservations'][0]['status']);
        self::assertSame('Alice', $data['reservations'][0]['customer']['first_name']);

        self::assertCount(1, $data['days']);
        $day = $data['days'][0];
        self::assertSame('2024-08-16', $day['date']);
        self::assertSame([10, 11], array_column($day['reservations'], 'id'));
        self::assertSame('Alice Example', $day['reservations'][0]['customer']['name']);
        self::assertSame('guest@example.test', $day['reservations'][1]['customer']['name']);

        self::assertCount(3, $data['tables']);
        self::assertSame([7, 9, 12], array_column($data['tables'], 'id'));
        self::assertSame('Sala Principale', $data['tables'][0]['room_name']);

        self::assertCount(2, $data['rooms']);
        self::assertSame('Sala Privata', $data['rooms'][1]['name']);
        self::assertSame('interni', $data['groups'][0]['code']);
        self::assertSame([7, 9], $data['groups'][0]['tables']);

        self::assertIsString($data['meta']['generated_at']);
        self::assertMatchesRegularExpression('/^\d{4}-\d{2}-\d{2}T/', $data['meta']['generated_at']);
    }

    public function testHandleAgendaFallsBackToTodayForInvalidParameters(): void
    {
        $wpdb = new FakeWpdb();
        $reservationsRepository = new ReservationsRepository($wpdb);
        $tablesRepository       = new TablesRepository($wpdb);
        $layout                 = new LayoutService($tablesRepository);

        $service = $this->createStub(Service::class);

        $today = gmdate('Y-m-d');

        $rest = new AdminREST($reservationsRepository, $service, null, $layout);

        $request = new WP_REST_Request([
            'date' => 'not-a-date',
            'range' => 'month',
        ]);

        $response = $rest->handleAgenda($request);
        $data = $response->get_data();

        self::assertSame('day', $data['range']['mode']);
        self::assertSame($today, $data['range']['start']);
        self::assertSame($today, $data['range']['end']);
        self::assertSame([], $data['reservations']);
        self::assertSame([], $data['days']);
        self::assertSame([], $data['tables']);
        self::assertSame([], $data['rooms']);
        self::assertSame([], $data['groups']);
    }
}
