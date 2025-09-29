<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use FP\Resv\Core\Helpers;
use PHPUnit\Framework\TestCase;

final class HelpersTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $_SERVER = [];
    }

    public function testReturnsForwardedIpWhenAvailable(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.5, 198.51.100.10';
        $_SERVER['REMOTE_ADDR']          = '198.51.100.2';

        self::assertSame('203.0.113.5', Helpers::clientIp());
    }

    public function testFallsBackToRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '198.51.100.20';

        self::assertSame('198.51.100.20', Helpers::clientIp());
    }

    public function testReturnsDefaultWhenNoHeaderIsPresent(): void
    {
        self::assertSame('0.0.0.0', Helpers::clientIp());
    }
}

