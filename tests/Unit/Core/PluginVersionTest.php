<?php

declare(strict_types=1);

namespace FP\Resv\Tests\Unit\Core;

use FP\Resv\Core\Plugin;
use PHPUnit\Framework\TestCase;

final class PluginVersionTest extends TestCase
{
    public function testVersionConstantMatchesPluginHeader(): void
    {
        $pluginFile = dirname(__DIR__, 3) . '/fp-restaurant-reservations.php';
        $contents   = file_get_contents($pluginFile);

        $this->assertNotFalse($contents, 'Plugin file should be readable.');

        $matches = [];
        $this->assertSame(1, preg_match('/^\s*\*\s*Version:\s*(?<version>[^\r\n]+)/m', $contents, $matches));
        $this->assertArrayHasKey('version', $matches);

        $headerVersion = trim($matches['version']);

        $this->assertSame(
            $headerVersion,
            Plugin::VERSION,
            'The plugin header and Plugin::VERSION must remain in sync.'
        );
    }

    public function testVersionConstantIsSemantic(): void
    {
        $this->assertSame(
            1,
            preg_match('/^\d+\.\d+\.\d+$/', Plugin::VERSION),
            'Plugin::VERSION must be a semantic version string (X.Y.Z).'
        );
    }
}
