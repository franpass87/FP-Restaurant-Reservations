<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Notifications;

use FP\Resv\Domain\Settings\Options;

final class Settings
{
    public const CHANNEL_CONFIRMATION = 'confirmation';
    public const CHANNEL_REMINDER = 'reminder';
    public const CHANNEL_REVIEW = 'review';

    private const CHANNEL_KEYS = [
        self::CHANNEL_CONFIRMATION => 'customer_confirmation_channel',
        self::CHANNEL_REMINDER     => 'customer_reminder_channel',
        self::CHANNEL_REVIEW       => 'customer_review_channel',
    ];

    private const SUBJECT_KEYS = [
        self::CHANNEL_CONFIRMATION => 'customer_confirmation_subject',
        self::CHANNEL_REMINDER     => 'customer_reminder_subject',
        self::CHANNEL_REVIEW       => 'customer_review_subject',
    ];

    private const BODY_KEYS = [
        self::CHANNEL_CONFIRMATION => 'customer_confirmation_body',
        self::CHANNEL_REMINDER     => 'customer_reminder_body',
        self::CHANNEL_REVIEW       => 'customer_review_body',
    ];

    private const LAYOUT_HEADER_KEY = 'customer_template_header';
    private const LAYOUT_FOOTER_KEY = 'customer_template_footer';
    private const LOGO_URL_KEY      = 'customer_template_logo_url';

    private const ENABLE_KEYS = [
        self::CHANNEL_REMINDER => 'customer_reminder_enabled',
        self::CHANNEL_REVIEW   => 'customer_review_enabled',
    ];

    private const OFFSET_KEYS = [
        self::CHANNEL_REMINDER => 'customer_reminder_offset_hours',
        self::CHANNEL_REVIEW   => 'customer_review_delay_hours',
    ];

    public function __construct(private readonly Options $options)
    {
    }

    /**
     * @return array<string, mixed>
     */
    public function all(): array
    {
        return $this->options->getGroup('fp_resv_notifications', []);
    }

    public function channelValue(string $channel): string
    {
        $options = $this->all();
        $key     = self::CHANNEL_KEYS[$channel] ?? '';
        $value   = isset($options[$key]) ? (string) $options[$key] : 'plugin';

        return in_array($value, ['plugin', 'brevo'], true) ? $value : 'plugin';
    }

    public function shouldUsePlugin(string $channel): bool
    {
        $value = $this->channelValue($channel);
        if ($value === 'brevo') {
            return !$this->isBrevoActive();
        }

        return true;
    }

    public function shouldUseBrevo(string $channel): bool
    {
        return $this->channelValue($channel) === 'brevo' && $this->isBrevoActive();
    }

    public function isEnabled(string $channel): bool
    {
        $options = $this->all();
        $key     = self::ENABLE_KEYS[$channel] ?? '';
        if ($key === '') {
            return true;
        }

        $value = isset($options[$key]) ? (string) $options[$key] : '1';

        return $value === '1';
    }

    public function offsetHours(string $channel, int $default): int
    {
        $options = $this->all();
        $key     = self::OFFSET_KEYS[$channel] ?? '';
        if ($key === '') {
            return $default;
        }

        $value = isset($options[$key]) ? (int) $options[$key] : $default;
        if ($value <= 0) {
            $value = $default;
        }

        return $value;
    }

    /**
     * @return array{subject: string, body: string}
     */
    public function template(string $channel): array
    {
        $options    = $this->all();
        $subjectKey = self::SUBJECT_KEYS[$channel] ?? '';
        $bodyKey    = self::BODY_KEYS[$channel] ?? '';

        return [
            'subject' => $subjectKey !== '' ? (string) ($options[$subjectKey] ?? '') : '',
            'body'    => $bodyKey !== '' ? (string) ($options[$bodyKey] ?? '') : '',
        ];
    }

    /**
     * @return array{header: string, footer: string}
     */
    public function layout(): array
    {
        $options = $this->all();

        return [
            'header' => (string) ($options[self::LAYOUT_HEADER_KEY] ?? ''),
            'footer' => (string) ($options[self::LAYOUT_FOOTER_KEY] ?? ''),
        ];
    }

    public function logoUrl(): string
    {
        $options = $this->all();

        return trim((string) ($options[self::LOGO_URL_KEY] ?? ''));
    }

    public function reviewUrl(): string
    {
        $options = $this->all();

        return isset($options['customer_review_url']) ? (string) $options['customer_review_url'] : '';
    }

    public function isBrevoActive(): bool
    {
        $brevoOptions = $this->options->getGroup('fp_resv_brevo', []);
        $enabled      = isset($brevoOptions['brevo_enabled']) ? (string) $brevoOptions['brevo_enabled'] : '0';
        $apiKey       = isset($brevoOptions['brevo_api_key']) ? trim((string) $brevoOptions['brevo_api_key']) : '';

        return $enabled === '1' && $apiKey !== '';
    }
}
