<?php

declare(strict_types=1);

namespace Tests\Integration\Diagnostics;

use FP\Resv\Core\Logging;
use FP\Resv\Core\ServiceContainer;
use FP\Resv\Domain\Diagnostics\Logger;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeWpdb;

final class LoggingTest extends TestCase
{
    private FakeWpdb $wpdb;
    private Logger $logger;

    protected function setUp(): void
    {
        parent::setUp();

        $this->wpdb = new FakeWpdb();
        $GLOBALS['wpdb'] = $this->wpdb;

        $this->logger = new Logger($this->wpdb);
        $container    = ServiceContainer::getInstance();
        $container->register(Logger::class, $this->logger);
        $container->register('diagnostics.logger', $this->logger);
    }

    public function testLogPersistsRow(): void
    {
        Logging::log('payments', 'Test message', [
            'reservation_id' => 42,
            'customer_id'    => 77,
            'extra'          => 'value',
        ]);

        $rows = $this->logger->recent();

        self::assertCount(1, $rows);
        $row = $rows[0];
        self::assertSame('payments', $row['channel']);
        self::assertSame('Test message', $row['message']);
        self::assertSame(42, (int) $row['reservation_id']);
        self::assertSame(77, (int) $row['customer_id']);
        self::assertNotEmpty($row['context_json']);
    }
}
