<?php

declare(strict_types=1);

namespace Tests\Integration\Reservations;

use DateTimeImmutable;
use FP\Resv\Domain\Reservations\Models\Reservation;
use FP\Resv\Domain\Reservations\Repository;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeWpdb;

final class RepositoryTest extends TestCase
{
    private FakeWpdb $wpdb;
    private Repository $repository;

    protected function setUp(): void
    {
        parent::setUp();
        $this->wpdb        = new FakeWpdb();
        $this->repository  = new Repository($this->wpdb);
        $GLOBALS['wpdb']   = $this->wpdb;
    }

    public function testInsertAndFindRoundtrip(): void
    {
        $id = $this->repository->insert([
            'status'      => 'pending',
            'date'        => '2024-05-01',
            'time'        => '19:30:00',
            'party'       => 4,
            'customer_id' => 10,
            'notes'       => 'Window seat',
        ]);

        self::assertSame(1, $id);

        $reservation = $this->repository->find($id);
        self::assertInstanceOf(Reservation::class, $reservation);
        self::assertSame('pending', $reservation->status);
        self::assertSame('2024-05-01', $reservation->date);
        self::assertSame('19:30:00', $reservation->time);
        self::assertSame(4, $reservation->party);
        self::assertInstanceOf(DateTimeImmutable::class, $reservation->created);
    }

    public function testAuditLogStoresEntries(): void
    {
        $this->repository->logAudit([
            'entity_id' => 42,
            'action'    => 'create',
            'ip'        => '198.51.100.7',
        ]);

        $table = $this->wpdb->get_table($this->repository->auditTable());
        self::assertCount(1, $table);
        $log = $table[1];
        self::assertSame('create', $log['action']);
        self::assertSame('198.51.100.7', $log['ip']);
    }
}

