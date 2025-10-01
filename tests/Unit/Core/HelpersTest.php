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

    public function testSkipsInvalidForwardedForEntries(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'unknown, 203.0.113.77';
        $_SERVER['REMOTE_ADDR']          = '198.51.100.20';

        self::assertSame('203.0.113.77', Helpers::clientIp());
    }

    public function testSkipsPrivateForwardedForEntries(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '10.0.0.15, 203.0.113.150';
        $_SERVER['REMOTE_ADDR']          = '198.51.100.20';

        self::assertSame('203.0.113.150', Helpers::clientIp());
    }

    public function testSkipsInvalidXRealIpEntries(): void
    {
        $_SERVER['HTTP_X_REAL_IP'] = 'unknown, 203.0.113.211';
        $_SERVER['REMOTE_ADDR']    = '198.51.100.20';

        self::assertSame('203.0.113.211', Helpers::clientIp());
    }

    public function testStripsPortFromForwardedFor(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '203.0.113.77:52341, 198.51.100.20';
        $_SERVER['REMOTE_ADDR']          = '198.51.100.21';

        self::assertSame('203.0.113.77', Helpers::clientIp());
    }

    public function testHandlesForwardedHeader(): void
    {
        $_SERVER['HTTP_FORWARDED']       = 'for="203.0.113.88:443";proto=https, for="198.51.100.33"';
        $_SERVER['HTTP_X_FORWARDED_FOR'] = 'unknown';
        $_SERVER['REMOTE_ADDR']          = '198.51.100.50';

        self::assertSame('203.0.113.88', Helpers::clientIp());
    }

    public function testHandlesIpv6ForwardedValues(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '[2001:db8::dead:beef]:10443, 198.51.100.20';

        self::assertSame('2001:db8::dead:beef', Helpers::clientIp());
    }

    public function testHandlesIpv4MappedIpv6Addresses(): void
    {
        $_SERVER['HTTP_X_FORWARDED_FOR'] = '::ffff:192.0.2.128';

        self::assertSame('::ffff:192.0.2.128', Helpers::clientIp());
    }

    public function testReturnsDefaultWhenNoHeaderIsPresent(): void
    {
        self::assertSame('0.0.0.0', Helpers::clientIp());
    }

    public function testAllowsPrivateRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = '10.0.0.45';

        self::assertSame('10.0.0.45', Helpers::clientIp());
    }

    public function testStripsZoneIdentifierFromRemoteAddr(): void
    {
        $_SERVER['REMOTE_ADDR'] = 'fe80::1%en0';

        self::assertSame('fe80::1', Helpers::clientIp());
    }
}

