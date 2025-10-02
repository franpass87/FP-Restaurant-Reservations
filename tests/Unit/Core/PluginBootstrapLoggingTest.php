<?php

declare(strict_types=1);

namespace FP\Resv\Tests\Unit\Core;

use FP\Resv\Core\Plugin;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use LogicException;
use ReflectionMethod;
use function add_action;

final class PluginBootstrapLoggingTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();

        unset($GLOBALS['__wp_tests_hooks']['fp_resv_log']);
    }

    public function testRunBootstrapStageLogsAndRethrowsExceptions(): void
    {
        $exception = new RuntimeException('Activation exploded', 1337);
        $captured  = [];

        add_action('fp_resv_log', static function (string $channel, string $message, array $context) use (&$captured): void {
            $captured[] = [$channel, $message, $context];
        }, 10, 3);

        $method = new ReflectionMethod(Plugin::class, 'runBootstrapStage');
        $method->setAccessible(true);

        $this->expectExceptionObject($exception);

        try {
            $method->invoke(null, 'activation', static function () use ($exception): void {
                throw $exception;
            });
        } finally {
            $this->assertNotEmpty($captured, 'The bootstrap failure should be logged.');

            [$channel, $message, $context] = $captured[0];

            $this->assertSame('bootstrap', $channel);
            $this->assertSame('Plugin stage "activation" failed', $message);

            $this->assertSame('activation', $context['stage']);
            $this->assertSame(Plugin::VERSION, $context['plugin_version']);
            $this->assertArrayHasKey('plugin_file', $context);
            $this->assertArrayHasKey('plugin_dir', $context);
            $this->assertArrayHasKey('plugin_url', $context);
            $this->assertSame(RuntimeException::class, $context['exception']);
            $this->assertSame('Activation exploded', $context['message']);
            $this->assertSame(1337, $context['code']);
        }
    }

    public function testExceptionContextIncludesPreviousExceptionDetails(): void
    {
        $previous = new LogicException('Previous failure', 404);
        $exception = new RuntimeException('Bootstrap failure', 500, $previous);

        $method = new ReflectionMethod(Plugin::class, 'exceptionContext');
        $method->setAccessible(true);

        $context = $method->invoke(null, 'bootstrap', $exception);

        $this->assertSame('bootstrap', $context['stage']);
        $this->assertSame(Plugin::VERSION, $context['plugin_version']);
        $this->assertSame(LogicException::class, $context['previous']);
        $this->assertSame('Previous failure', $context['previous_message']);
        $this->assertSame(404, $context['previous_code']);
        $this->assertSame(RuntimeException::class, $context['exception']);
        $this->assertSame('Bootstrap failure', $context['message']);
        $this->assertSame(500, $context['code']);
    }
}
