<?php

declare(strict_types=1);

namespace Tests\Unit\Domain\Tracking;

use FP\Resv\Core\Consent;
use FP\Resv\Domain\Settings\Options;
use FP\Resv\Domain\Tracking\Ads;
use FP\Resv\Domain\Tracking\Clarity;
use FP\Resv\Domain\Tracking\GA4;
use FP\Resv\Domain\Tracking\Manager;
use FP\Resv\Domain\Tracking\Meta;
use PHPUnit\Framework\TestCase;

final class ManagerTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $_GET = [];
        $_COOKIE = [];
        update_option('fp_resv_tracking', [
            'tracking_utm_cookie_days' => '30',
        ]);
    }

    public function testCaptureQueuesUntilConsentGranted(): void
    {
        $_GET['utm_source'] = 'newsletter';
        $_GET['utm_campaign'] = 'spring';

        $options = new Options();
        Consent::init($options);
        Consent::update([
            'analytics' => 'denied',
            'ads'       => 'denied',
        ]);

        $manager = new Manager(
            $options,
            new GA4($options),
            new Ads($options),
            new Meta($options),
            new Clarity($options)
        );

        $manager->captureAttribution();

        $queue = get_option('fp_resv_pending_attribution', []);
        self::assertIsArray($queue);
        self::assertCount(1, $queue);
        self::assertSame('newsletter', $queue[0]['values']['utm_source']);

        Consent::update([
            'analytics' => 'granted',
            'ads'       => 'granted',
        ]);

        self::assertTrue(Consent::has('analytics'));
        self::assertTrue(Consent::has('ads'));

        $manager->captureAttribution();
        $manager->maybeFlushPendingAttribution();

        $queue = get_option('fp_resv_pending_attribution', []);
        self::assertIsArray($queue);
        self::assertSame([], $queue);
    }
}
