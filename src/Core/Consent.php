<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use FP\Resv\Domain\Settings\Options;
use function apply_filters;
use function headers_sent;
use function in_array;
use function is_array;
use function is_ssl;
use function is_string;
use function json_decode;
use function setcookie;
use function strtolower;
use function time;
use function trim;
use function wp_json_encode;
use const DAY_IN_SECONDS;

final class Consent
{
    private const COOKIE_NAME     = 'fp_resv_consent';
    private const CONSENT_VERSION = 1;

    /**
     * @var array<string, string>
     */
    private static array $state = [];

    /**
     * @var array{version: string, updated_at: int}
     */
    private static array $meta = [
        'version'    => '1.0',
        'updated_at' => 0,
    ];

    private static bool $initialized = false;

    private static int $cookieTtlDays = 180;

    private static string $policyVersion = '1.0';

    public static function init(Options $options): void
    {
        self::$cookieTtlDays = self::resolveCookieTtl($options);
        self::$policyVersion = self::resolvePolicyVersion($options);

        [$state, $meta] = self::loadState($options);

        self::$state       = $state;
        self::$meta        = $meta;
        self::$initialized = true;
    }

    public static function cookieName(): string
    {
        return self::COOKIE_NAME;
    }

    public static function cookieTtlDays(): int
    {
        self::ensureInitialized();

        return self::$cookieTtlDays;
    }

    public static function version(): int
    {
        return self::CONSENT_VERSION;
    }

    public static function has(string $type): bool
    {
        self::ensureInitialized();

        $normalized = strtolower(trim($type));
        $state      = self::$state[$normalized] ?? 'denied';

        if (in_array($normalized, ['security', 'functionality'], true)) {
            $state = 'granted';
        }

        return $state === 'granted';
    }

    /**
     * @return array<string, string>
     */
    public static function all(): array
    {
        self::ensureInitialized();

        return self::$state;
    }

    /**
     * @return array{version: string, updated_at: int}
     */
    public static function metadata(): array
    {
        self::ensureInitialized();

        return self::$meta;
    }

    /**
     * @param array<string, string|bool> $updates
     *
     * @return array<string, string>
     */
    public static function update(array $updates): array
    {
        self::ensureInitialized();

        foreach ($updates as $key => $value) {
            $normalized = strtolower(trim((string) $key));
            if (!isset(self::$state[$normalized])) {
                continue;
            }

            $status = is_string($value) ? strtolower(trim($value)) : ($value ? 'granted' : 'denied');
            if (!in_array($status, ['granted', 'denied'], true)) {
                continue;
            }

            self::$state[$normalized] = $status;
        }

        self::enforceBaselines();
        self::$meta['updated_at'] = time();
        self::$meta['version']    = self::$policyVersion;
        self::persist();

        return self::$state;
    }

    /**
     * @return array<string, string>
     */
    public static function gtagState(): array
    {
        self::ensureInitialized();

        $state = self::$state;

        return apply_filters('fp_resv_consent_gtag_state', [
            'analytics_storage'      => $state['analytics'] ?? 'denied',
            'ad_storage'             => $state['ads'] ?? 'denied',
            'ad_user_data'           => $state['ads'] ?? 'denied',
            'ad_personalization'     => $state['ads'] ?? 'denied',
            'personalization_storage'=> $state['personalization'] ?? 'denied',
            'functionality_storage'  => 'granted',
            'security_storage'       => 'granted',
        ]);
    }

    private static function ensureInitialized(): void
    {
        if (self::$initialized) {
            return;
        }

        $defaults = [
            'analytics'       => 'granted',
            'ads'             => 'granted',
            'personalization' => 'granted',
            'clarity'         => 'granted',
            'functionality'   => 'granted',
            'security'        => 'granted',
        ];

        self::$state = $defaults;
        self::$meta  = [
            'version'    => self::$policyVersion,
            'updated_at' => 0,
        ];

        self::$initialized = true;
    }

    private static function loadState(Options $options): array
    {
        $defaults = self::defaultState($options);
        $cookie   = $_COOKIE[self::COOKIE_NAME] ?? '';

        $meta = [
            'version'    => self::$policyVersion,
            'updated_at' => 0,
        ];

        if (!is_string($cookie) || $cookie === '') {
            return [$defaults, $meta];
        }

        $decoded = json_decode($cookie, true);
        if (!is_array($decoded)) {
            return [$defaults, $meta];
        }

        $stateData = $decoded;
        if (isset($decoded['state']) && is_array($decoded['state'])) {
            $stateData = $decoded['state'];
        }

        $state = $defaults;
        foreach ($stateData as $key => $value) {
            $normalized = strtolower(trim((string) $key));
            if (!isset($state[$normalized])) {
                continue;
            }

            if (is_string($value)) {
                $candidate = strtolower(trim($value));
                if (in_array($candidate, ['granted', 'denied'], true)) {
                    $state[$normalized] = $candidate;
                }
            }
        }

        $state['functionality'] = 'granted';
        $state['security']      = 'granted';

        if (isset($decoded['meta']) && is_array($decoded['meta'])) {
            $metadata = $decoded['meta'];
            if (isset($metadata['version']) && is_string($metadata['version']) && $metadata['version'] !== '') {
                $meta['version'] = $metadata['version'];
            }

            if (isset($metadata['updated_at'])) {
                $timestamp = (int) $metadata['updated_at'];
                if ($timestamp > 0) {
                    $meta['updated_at'] = $timestamp;
                }
            }
        }

        return [$state, $meta];
    }

    private static function defaultState(Options $options): array
    {
        $settings = $options->getGroup('fp_resv_tracking', [
            'consent_mode_default' => 'auto',
        ]);

        $mode = strtolower(trim((string) ($settings['consent_mode_default'] ?? 'auto')));
        if (!in_array($mode, ['auto', 'denied', 'granted'], true)) {
            $mode = 'auto';
        }

        $base = [
            'analytics'       => 'denied',
            'ads'             => 'denied',
            'personalization' => 'denied',
            'clarity'         => 'denied',
            'functionality'   => 'granted',
            'security'        => 'granted',
        ];

        if ($mode === 'granted') {
            $base['analytics']       = 'granted';
            $base['ads']             = 'granted';
            $base['personalization'] = 'granted';
            $base['clarity']         = 'granted';
        } elseif ($mode === 'denied') {
            $base['analytics']       = 'denied';
            $base['ads']             = 'denied';
            $base['personalization'] = 'denied';
            $base['clarity']         = 'denied';
        }

        return apply_filters('fp_resv_consent_default_state', $base, $mode);
    }

    private static function resolveCookieTtl(Options $options): int
    {
        $settings = $options->getGroup('fp_resv_tracking', [
            'tracking_cookie_ttl_days' => '180',
        ]);

        $ttl = (int) ($settings['tracking_cookie_ttl_days'] ?? 180);
        if ($ttl < 0) {
            $ttl = 0;
        }

        return $ttl;
    }

    private static function enforceBaselines(): void
    {
        self::$state['functionality'] = 'granted';
        self::$state['security']      = 'granted';
    }

    private static function persist(): void
    {
        if (headers_sent()) {
            return;
        }

        if (self::$meta['updated_at'] === 0) {
            self::$meta['updated_at'] = time();
        }

        self::$meta['version'] = self::$policyVersion;

        $value = wp_json_encode([
            'state' => self::$state,
            'meta'  => self::$meta,
        ]);
        if (!is_string($value)) {
            return;
        }

        $expires = self::$cookieTtlDays > 0 ? time() + (self::$cookieTtlDays * DAY_IN_SECONDS) : 0;

        $secure = is_ssl();
        setcookie(
            self::COOKIE_NAME,
            $value,
            [
                'expires'  => $expires,
                'path'     => '/',
                'secure'   => $secure,
                'httponly' => false,
                'samesite' => 'Lax',
            ]
        );

        $_COOKIE[self::COOKIE_NAME] = $value;
    }

    private static function resolvePolicyVersion(Options $options): string
    {
        $settings = $options->getGroup('fp_resv_tracking', [
            'privacy_policy_version' => '1.0',
        ]);

        $version = trim((string) ($settings['privacy_policy_version'] ?? '1.0'));
        if ($version === '') {
            $version = '1.0';
        }

        return $version;
    }
}
