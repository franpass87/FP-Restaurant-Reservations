<?php

declare(strict_types=1);

namespace FP\Resv\Tests\Unit\Core;

use FP\Resv\Core\Logging;
use PHPUnit\Framework\TestCase;

use function add_action;
use function file_get_contents;
use function ini_set;
use function is_file;
use function sprintf;
use function strpos;
use function sys_get_temp_dir;
use function tempnam;
use function trim;

final class LoggingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        unset($GLOBALS['__wp_tests_hooks']['fp_resv_log']);
    }

    protected function tearDown(): void
    {
        unset($GLOBALS['__wp_tests_hooks']['fp_resv_log']);
        parent::tearDown();
    }

    public function testLogsToErrorLogWhenNoHandlersAreRegistered(): void
    {
        $logFile  = tempnam(sys_get_temp_dir(), 'fp-resv-log-') ?: sys_get_temp_dir() . '/fp-resv-log';
        $previous = ini_set('error_log', $logFile);

        Logging::log('reservations', 'Test message', ['foo' => 'bar']);

        $output = is_file($logFile) ? (file_get_contents($logFile) ?: '') : '';
        @unlink($logFile);

        if ($previous !== false) {
            ini_set('error_log', $previous);
        }

        $this->assertNotFalse(strpos($output, '[fp-resv][reservations] Test message'));
        $this->assertNotFalse(strpos($output, '"foo":"bar"'));
    }

    public function testLogsContextEvenWhenEncodingFails(): void
    {
        $logFile  = tempnam(sys_get_temp_dir(), 'fp-resv-log-') ?: sys_get_temp_dir() . '/fp-resv-log';
        $previous = ini_set('error_log', $logFile);

        Logging::log('reservations', 'Broken context', ['bad' => "\xC3\x28"]);

        $output = is_file($logFile) ? (file_get_contents($logFile) ?: '') : '';
        @unlink($logFile);

        if ($previous !== false) {
            ini_set('error_log', $previous);
        }

        $this->assertNotFalse(strpos($output, '[fp-resv][reservations] Broken context'));
        $this->assertNotFalse(strpos($output, '"bad":null'));
    }

    public function testSkipsFallbackWhenListenerIsRegistered(): void
    {
        $captured = '';
        add_action('fp_resv_log', function (string $channel, string $message, array $context) use (&$captured): void {
            $captured = sprintf('%s:%s', $channel, $message);
        }, 10, 3);

        $logFile  = tempnam(sys_get_temp_dir(), 'fp-resv-log-') ?: sys_get_temp_dir() . '/fp-resv-log';
        $previous = ini_set('error_log', $logFile);

        Logging::log('mail', 'Handled');

        $output = is_file($logFile) ? (file_get_contents($logFile) ?: '') : '';
        @unlink($logFile);

        if ($previous !== false) {
            ini_set('error_log', $previous);
        }

        $this->assertSame('mail:Handled', $captured);
        $this->assertSame('', trim($output));
    }
}
