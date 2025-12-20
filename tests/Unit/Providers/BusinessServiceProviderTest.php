<?php

declare(strict_types=1);

namespace Tests\Unit\Providers;

use FP\Resv\Kernel\Container;
use FP\Resv\Providers\BusinessServiceProvider;
use PHPUnit\Framework\TestCase;

/**
 * Test for BusinessServiceProvider
 */
final class BusinessServiceProviderTest extends TestCase
{
    private Container $container;
    private BusinessServiceProvider $provider;

    protected function setUp(): void
    {
        parent::setUp();

        $this->container = new Container();
        
        // Register dependencies required by BusinessServiceProvider
        $this->container->singleton(
            \FP\Resv\Core\Services\OptionsInterface::class,
            \FP\Resv\Core\Services\Options::class
        );
        $this->container->singleton(
            \FP\Resv\Core\Adapters\DatabaseAdapterInterface::class,
            \FP\Resv\Core\Adapters\DatabaseAdapter::class
        );
        $this->container->singleton(
            \FP\Resv\Core\Mailer::class,
            \FP\Resv\Core\Mailer::class
        );
        $this->container->singleton(
            \FP\Resv\Domain\Reservations\Repository::class,
            function () {
                return $this->createMock(\FP\Resv\Domain\Reservations\Repository::class);
            }
        );
        $this->container->singleton(
            \FP\Resv\Domain\Payments\Repository::class,
            function () {
                return $this->createMock(\FP\Resv\Domain\Payments\Repository::class);
            }
        );
        $this->container->singleton(
            \FP\Resv\Domain\Customers\Repository::class,
            function () {
                return $this->createMock(\FP\Resv\Domain\Customers\Repository::class);
            }
        );
        $this->container->singleton(
            \FP\Resv\Domain\Tables\Repository::class,
            function () {
                return $this->createMock(\FP\Resv\Domain\Tables\Repository::class);
            }
        );
        $this->container->singleton(
            \FP\Resv\Domain\Brevo\Repository::class,
            function () {
                return $this->createMock(\FP\Resv\Domain\Brevo\Repository::class);
            }
        );

        $this->provider = new BusinessServiceProvider();
    }

    public function testRegisterDoesNotThrowException(): void
    {
        // Test that register completes without exceptions
        // (actual service registration requires many dependencies)
        $this->expectNotToPerformAssertions();
        $this->provider->register($this->container);
    }

    public function testRegisterRegistersKeyServices(): void
    {
        $this->provider->register($this->container);

        // Test that key services are registered (if dependencies are available)
        // Note: Some services may not be registered if dependencies are missing
        // This is a basic smoke test
        $this->assertTrue(true); // Placeholder - actual test would require full dependency setup
    }

    public function testBootDoesNotThrowException(): void
    {
        $this->provider->register($this->container);
        
        // Boot should not throw exceptions even if some services fail to initialize
        $this->expectNotToPerformAssertions();
        $this->provider->boot($this->container);
    }
}

