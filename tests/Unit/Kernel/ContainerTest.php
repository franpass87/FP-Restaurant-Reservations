<?php

declare(strict_types=1);

namespace Tests\Unit\Kernel;

use FP\Resv\Kernel\Container;
use PHPUnit\Framework\TestCase;

/**
 * Test for Kernel\Container (PSR-11)
 */
final class ContainerTest extends TestCase
{
    private Container $container;

    protected function setUp(): void
    {
        parent::setUp();
        $this->container = new Container();
    }

    public function testBindAndGet(): void
    {
        $this->container->bind('test.key', 'test.value');

        $this->assertEquals('test.value', $this->container->get('test.key'));
    }

    public function testSingleton(): void
    {
        $instance = new \stdClass();
        $instance->value = 'singleton';

        $this->container->singleton('test.singleton', fn() => $instance);

        $first = $this->container->get('test.singleton');
        $second = $this->container->get('test.singleton');

        $this->assertSame($first, $second);
    }

    public function testFactory(): void
    {
        $this->container->bind('test.factory', fn() => new \stdClass());

        $first = $this->container->get('test.factory');
        $second = $this->container->get('test.factory');

        $this->assertNotSame($first, $second);
    }

    public function testHas(): void
    {
        $this->assertFalse($this->container->has('test.missing'));

        $this->container->bind('test.exists', 'value');
        $this->assertTrue($this->container->has('test.exists'));
    }

    public function testGetThrowsExceptionWhenNotFound(): void
    {
        $this->expectException(\Psr\Container\NotFoundExceptionInterface::class);

        $this->container->get('test.not.found');
    }

    public function testAlias(): void
    {
        $this->container->bind('test.original', 'original.value');
        $this->container->alias('test.alias', 'test.original');

        $this->assertEquals('original.value', $this->container->get('test.alias'));
    }

    public function testBindWithClosure(): void
    {
        $this->container->bind('test.closure', fn() => 'closure.value');

        $this->assertEquals('closure.value', $this->container->get('test.closure'));
    }

    public function testMultipleBindings(): void
    {
        $this->container->bind('test.1', 'value1');
        $this->container->bind('test.2', 'value2');
        $this->container->bind('test.3', 'value3');

        $this->assertEquals('value1', $this->container->get('test.1'));
        $this->assertEquals('value2', $this->container->get('test.2'));
        $this->assertEquals('value3', $this->container->get('test.3'));
    }
}

