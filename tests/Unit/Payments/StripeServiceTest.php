<?php

declare(strict_types=1);

namespace Tests\Unit\Payments;

use FP\Resv\Domain\Payments\StripeService;
use FP\Resv\Domain\Settings\Options;
use PHPUnit\Framework\TestCase;
use Tests\Support\FakeWpdb;
use FP\Resv\Domain\Payments\Repository;

final class StripeServiceTest extends TestCase
{
    protected function tearDown(): void
    {
        parent::tearDown();
        update_option('fp_resv_payments', []);
    }

    public function testCalculateReservationAmountUsesDepositForCaptureStrategy(): void
    {
        update_option('fp_resv_payments', [
            'stripe_enabled'        => '1',
            'stripe_capture_type'   => 'deposit',
            'stripe_deposit_amount' => '15',
        ]);

        $service = new StripeService(new Options(), new Repository(new FakeWpdb()));

        $amount = $service->calculateReservationAmount(['party' => 3], 42);

        self::assertSame(45.0, $amount);
    }

    public function testCalculateReservationAmountPrefersSubmittedValue(): void
    {
        update_option('fp_resv_payments', [
            'stripe_capture_type'   => 'authorization',
            'stripe_deposit_amount' => '20',
        ]);

        $service = new StripeService(new Options(), new Repository(new FakeWpdb()));

        $amount = $service->calculateReservationAmount([
            'party' => 2,
            'value' => 75,
        ], 5);

        self::assertSame(75.0, $amount);
    }

    public function testShouldRequireReservationPaymentWhenEnabledAndPositiveAmount(): void
    {
        update_option('fp_resv_payments', [
            'stripe_enabled'        => '1',
            'stripe_capture_type'   => 'authorization',
            'stripe_deposit_amount' => '10',
        ]);

        $service = new StripeService(new Options(), new Repository(new FakeWpdb()));

        self::assertTrue($service->shouldRequireReservationPayment(['party' => 2]));

        update_option('fp_resv_payments', [
            'stripe_enabled' => '0',
        ]);

        $disabled = new StripeService(new Options(), new Repository(new FakeWpdb()));
        self::assertFalse($disabled->shouldRequireReservationPayment(['party' => 2]));
    }

    public function testToMinorUnitsHandlesZeroDecimalCurrencies(): void
    {
        update_option('fp_resv_payments', []);
        $service = new StripeService(new Options(), new Repository(new FakeWpdb()));

        $method = new \ReflectionMethod($service, 'toMinorUnits');
        $method->setAccessible(true);

        self::assertSame(1234, $method->invoke($service, 12.34, 'eur'));
        self::assertSame(13, $method->invoke($service, 12.7, 'JPY'));
    }
}
