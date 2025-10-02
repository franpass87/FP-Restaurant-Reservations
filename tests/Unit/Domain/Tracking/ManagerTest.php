<?php

declare(strict_types=1);

namespace FP\Resv\Tests\Unit\Domain\Tracking;

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
        unset($_COOKIE['fp_resv_utm']);
        $_GET = [];
        $GLOBALS['__wp_tests_options'] = [];
    }

    public function testCaptureAttributionSkipsWhenConsentDenied(): void
    {
        $this->bootstrapConsent(['consent_mode_default' => 'denied']);
        $_GET['utm_source'] = 'newsletter';

        $manager = $this->createManager();
        $manager->captureAttribution();

        self::assertArrayNotHasKey('fp_resv_utm', $_COOKIE);
    }

    public function testCaptureAttributionStoresWhenAnalyticsConsentGranted(): void
    {
        $this->bootstrapConsent(['consent_mode_default' => 'denied']);
        Consent::update(['analytics' => 'granted']);
        $_GET['utm_source'] = 'google';

        $manager = $this->createManager();
        $manager->captureAttribution();

        self::assertArrayHasKey('fp_resv_utm', $_COOKIE);
        $data = json_decode((string) $_COOKIE['fp_resv_utm'], true);
        self::assertIsArray($data);
        self::assertSame('google', $data['utm_source'] ?? null);
    }

    public function testCaptureAttributionStoresWhenAdsConsentGranted(): void
    {
        $this->bootstrapConsent(['consent_mode_default' => 'denied']);
        Consent::update(['ads' => 'granted']);
        $_GET['utm_medium'] = 'cpc';

        $manager = $this->createManager();
        $manager->captureAttribution();

        self::assertArrayHasKey('fp_resv_utm', $_COOKIE);
        $data = json_decode((string) $_COOKIE['fp_resv_utm'], true);
        self::assertIsArray($data);
        self::assertSame('cpc', $data['utm_medium'] ?? null);
    }

    /**
     * @param array<string, mixed> $tracking
     */
    private function bootstrapConsent(array $tracking = []): void
    {
        $GLOBALS['__wp_tests_options']['fp_resv_tracking'] = $tracking;
        Consent::init(new Options());
    }

    private function createManager(): Manager
    {
        $options = new Options();
        $ga4     = new GA4($options);
        $ads     = new Ads($options);
        $meta    = new Meta($options);
        $clarity = new Clarity($options);

        return new Manager($options, $ga4, $ads, $meta, $clarity);
    }
}
