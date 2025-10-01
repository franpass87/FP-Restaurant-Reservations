<?php

declare(strict_types=1);

namespace Tests\Integration\Reservations;

use FP\Resv\Domain\Reservations\Availability;
use FP\Resv\Domain\Settings\Options;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeWpdb;

final class AvailabilityTest extends TestCase
{
    private FakeWpdb $wpdb;
    private Availability $availability;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;

        update_option('fp_resv_general', [
            'restaurant_timezone'      => 'Europe/Rome',
            'service_hours_definition' => "sat=19:00-21:00",
            'slot_interval_minutes'    => '60',
            'table_turnover_minutes'   => '60',
            'buffer_before_minutes'    => '0',
            'max_parallel_parties'     => '5',
            'enable_waitlist'          => '0',
        ]);

        update_option('fp_resv_rooms', [
            'merge_strategy'        => 'smart',
            'default_room_capacity' => '4',
        ]);

        $this->availability = new Availability(new Options(), $this->wpdb);
    }

    public function testSlotsMarkedFullWhenCapacityConsumed(): void
    {
        $this->wpdb->insert('wp_fp_rooms', [
            'id'       => 1,
            'capacity' => 4,
            'active'   => 1,
        ]);

        $this->wpdb->insert('wp_fp_reservations', [
            'id'      => 1,
            'date'    => '2024-05-04',
            'time'    => '19:00:00',
            'party'   => 2,
            'status'  => 'confirmed',
            'room_id' => null,
            'table_id'=> null,
        ]);

        $this->wpdb->insert('wp_fp_reservations', [
            'id'      => 2,
            'date'    => '2024-05-04',
            'time'    => '19:00:00',
            'party'   => 2,
            'status'  => 'confirmed',
            'room_id' => null,
            'table_id'=> null,
        ]);

        $result = $this->availability->findSlots([
            'date'  => '2024-05-04',
            'party' => 2,
        ]);

        self::assertSame('full', $result['slots'][0]['status']);
    }

    public function testRoomFilterIgnoresReservationsFromOtherRooms(): void
    {
        update_option('fp_resv_rooms', [
            'merge_strategy'        => 'smart',
            'default_room_capacity' => '8',
        ]);

        $this->availability = new Availability(new Options(), $this->wpdb);

        $this->wpdb->insert('wp_fp_rooms', [
            'id'       => 1,
            'capacity' => 8,
            'active'   => 1,
        ]);

        $this->wpdb->insert('wp_fp_rooms', [
            'id'       => 2,
            'capacity' => 8,
            'active'   => 1,
        ]);

        $this->wpdb->insert('wp_fp_reservations', [
            'id'      => 3,
            'date'    => '2024-05-04',
            'time'    => '19:00:00',
            'party'   => 4,
            'status'  => 'confirmed',
            'room_id' => 2,
            'table_id'=> null,
        ]);

        $result = $this->availability->findSlots([
            'date'  => '2024-05-04',
            'party' => 2,
            'room'  => 1,
        ]);

        self::assertSame('available', $result['slots'][0]['status']);
        self::assertTrue($result['meta']['has_availability']);
    }

    public function testLocationFilterExcludesOtherLocations(): void
    {
        $this->wpdb->insert('wp_fp_rooms', [
            'id'       => 1,
            'capacity' => 8,
            'active'   => 1,
        ]);

        $this->wpdb->insert('wp_fp_reservations', [
            'id'          => 10,
            'date'        => '2024-05-04',
            'time'        => '19:00:00',
            'party'       => 6,
            'status'      => 'confirmed',
            'room_id'     => null,
            'table_id'    => null,
            'location_id' => 'downtown',
        ]);

        $this->wpdb->insert('wp_fp_reservations', [
            'id'          => 11,
            'date'        => '2024-05-04',
            'time'        => '19:00:00',
            'party'       => 6,
            'status'      => 'confirmed',
            'room_id'     => null,
            'table_id'    => null,
            'location_id' => 'uptown',
        ]);

        $result = $this->availability->findSlots([
            'date'     => '2024-05-04',
            'party'    => 2,
            'location' => 'downtown',
        ]);

        self::assertSame('limited', $result['slots'][0]['status']);
        self::assertTrue($result['meta']['has_availability']);
    }

    public function testClosureBlocksRequestedSlots(): void
    {
        $this->wpdb->insert('wp_fp_rooms', [
            'id'       => 1,
            'capacity' => 4,
            'active'   => 1,
        ]);

        $this->wpdb->insert('wp_fp_closures', [
            'id'                     => 1,
            'scope'                  => 'restaurant',
            'room_id'                => null,
            'table_id'               => null,
            'type'                   => 'full',
            'start_at'               => '2024-05-04 18:00:00',
            'end_at'                 => '2024-05-04 22:00:00',
            'recurrence_json'        => null,
            'capacity_override_json' => null,
            'active'                 => 1,
        ]);

        $result = $this->availability->findSlots([
            'date'  => '2024-05-04',
            'party' => 2,
        ]);

        self::assertSame('blocked', $result['slots'][0]['status']);
        self::assertFalse($result['meta']['has_availability']);
        self::assertNotEmpty($result['slots'][0]['reasons']);
    }
}
