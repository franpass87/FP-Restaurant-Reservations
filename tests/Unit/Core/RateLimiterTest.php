<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use FP\Resv\Core\RateLimiter;
use PHPUnit\Framework\TestCase;

final class RateLimiterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['__wp_tests_transients'] = [];
    }

    public function testAllowsWithinLimit(): void
    {
        $result = RateLimiter::check('example', 2, 60);

        self::assertTrue($result['allowed']);
        self::assertSame(1, $result['remaining']);
        self::assertSame(0, $result['retry_after']);
    }

    public function testBlocksWhenLimitExceeded(): void
    {
        RateLimiter::check('limited', 1, 60);
        $result = RateLimiter::check('limited', 1, 60);

        self::assertFalse($result['allowed']);
        self::assertGreaterThan(0, $result['retry_after']);
        self::assertSame(0, $result['remaining']);
    }
}
