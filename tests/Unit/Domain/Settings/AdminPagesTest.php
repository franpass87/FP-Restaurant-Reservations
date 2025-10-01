<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Settings;

use FP\Resv\Domain\Settings\AdminPages;
use PHPUnit\Framework\TestCase;
use ReflectionMethod;

final class AdminPagesTest extends TestCase
{
    private AdminPages $pages;

    protected function setUp(): void
    {
        parent::setUp();

        $this->pages = new AdminPages();
    }

    public function testAcceptsWhitespaceSeparatedServiceHours(): void
    {
        $definition = "mon=12:30-15:00 tue=12:30-15:00";

        self::assertSame([], $this->collectInvalidEntries($definition));
    }

    public function testAcceptsTrailingSpacesInServiceHours(): void
    {
        $definition = "mon=12:30-15:00 tue=12:30-15:00   \n";

        self::assertSame([], $this->collectInvalidEntries($definition));
    }

    public function testRejectsEntriesWithInvalidTimeRanges(): void
    {
        $definition = "mon=18:00-17:00 tue=12:30-15:00";

        self::assertSame(['mon=18:00-17:00'], $this->collectInvalidEntries($definition));
    }

    /**
     * @return list<string>
     */
    private function collectInvalidEntries(string $definition): array
    {
        $method = new ReflectionMethod(AdminPages::class, 'collectInvalidServiceHoursEntries');
        $method->setAccessible(true);

        /** @var list<string> $result */
        $result = $method->invoke($this->pages, $definition);

        return $result;
    }
}
