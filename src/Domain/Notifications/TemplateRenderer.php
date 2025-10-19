<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Notifications;

use FP\Resv\Core\Logging;
use FP\Resv\Domain\Settings\Language;
use function __;
use function apply_filters;
use function esc_attr;
use function esc_html;
use function esc_url;
use function is_array;
use function gmdate;
use function preg_replace;
use function strtolower;
use function str_replace;
use function substr;
use function trim;
use function wp_autop;
use function wp_kses;
use function wp_kses_allowed_html;
use function wp_strip_all_tags;

final class TemplateRenderer
{
    public function __construct(
        private readonly Settings $settings,
        private readonly Language $language
    ) {
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array{subject: string, body: string}
     */
    public function render(string $type, array $context): array
    {
        $normalized = $this->normalizeContext($context);
        $language   = $normalized['language'];

        $templates = $this->settings->template($type);
        $layout    = $this->settings->layout();
        $strings   = $this->language->getStrings($language);
        $defaults  = is_array($strings['emails'][$type] ?? null) ? $strings['emails'][$type] : [];
        $layoutDefaults = is_array($strings['emails']['layout'] ?? null) ? $strings['emails']['layout'] : [];

        $subjectTemplate = trim((string) ($templates['subject'] !== '' ? $templates['subject'] : ($defaults['subject'] ?? '')));
        $bodyTemplate    = trim((string) ($templates['body'] !== '' ? $templates['body'] : ($defaults['body'] ?? '')));
        $headerTemplate  = trim((string) ($layout['header'] !== '' ? $layout['header'] : ($layoutDefaults['header'] ?? '')));
        $footerTemplate  = trim((string) ($layout['footer'] !== '' ? $layout['footer'] : ($layoutDefaults['footer'] ?? '')));

        if ($subjectTemplate === '' || $bodyTemplate === '') {
            Logging::log('mail', 'Notification template missing fallback', [
                'type'      => $type,
                'subject'   => $subjectTemplate,
                'body'      => $bodyTemplate,
                'language'  => $language,
            ]);
        }

        $tokens = $this->buildTokens($normalized, $strings);

        $subject = $this->replaceTokens($subjectTemplate, $tokens, false);
        $header  = $this->replaceTokens($headerTemplate, $tokens, true);
        $body    = $this->replaceTokens($bodyTemplate, $tokens, true);
        $footer  = $this->replaceTokens($footerTemplate, $tokens, true);

        $body = $this->wrapLayout($header, $body, $footer, $normalized);

        $subject = apply_filters('fp_resv_notifications_rendered_subject', $subject, $type, $normalized);
        $body    = apply_filters('fp_resv_notifications_rendered_body', $body, $type, $normalized);

        return [
            'subject' => $subject,
            'body'    => $body,
        ];
    }

    /**
     * @param array<string, mixed> $context
     *
     * @return array<string, mixed>
     */
    private function normalizeContext(array $context): array
    {
        $language = (string) ($context['language'] ?? '');
        if ($language === '') {
            $language = $this->language->getDefaultLanguage();
        }

        $normalized = $context;
        $normalized['language'] = $language;

        if (empty($normalized['date_formatted']) && !empty($normalized['date'])) {
            $normalized['date_formatted'] = $this->language->formatDate((string) $normalized['date'], $language);
        }

        if (empty($normalized['time_formatted']) && !empty($normalized['time'])) {
            $normalized['time_formatted'] = $this->language->formatTime((string) $normalized['time'], $language);
        }

        if (empty($normalized['status_label']) && !empty($normalized['status'])) {
            $normalized['status_label'] = $this->language->statusLabel((string) $normalized['status'], $language);
        }

        return $normalized;
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $strings
     *
     * @return array<string, string>
     */
    private function buildTokens(array $context, array $strings): array
    {
        $customer = is_array($context['customer'] ?? null) ? $context['customer'] : [];
        $restaurant = is_array($context['restaurant'] ?? null) ? $context['restaurant'] : [];

        $reviewUrl   = (string) ($context['review_url'] ?? '');
        $manageUrl   = (string) ($context['manage_url'] ?? '');
        $language    = (string) ($context['language'] ?? $this->language->getDefaultLanguage());
        $placeholders = is_array($strings['emails']['placeholders'] ?? null) ? $strings['emails']['placeholders'] : [];

        $manageLabel = (string) ($placeholders['manage_link'] ?? __('Gestisci prenotazione', 'fp-restaurant-reservations'));
        $reviewLabel = (string) ($placeholders['review_link'] ?? __('Lascia una recensione', 'fp-restaurant-reservations'));

        $logoUrl = (string) ($restaurant['logo_url'] ?? $this->settings->logoUrl());
        $logoUrl = esc_url($logoUrl);
        $logoAlt = esc_html((string) ($restaurant['name'] ?? ''));
        $logoImg = $logoUrl !== ''
            ? '<img src="' . $logoUrl . '" alt="' . $logoAlt . '" class="fp-resv-email-logo">'
            : '';

        $manageLink = $manageUrl !== ''
            ? '<a href="' . esc_url($manageUrl) . '">' . esc_html($manageLabel) . '</a>'
            : '';
        $reviewLink = $reviewUrl !== ''
            ? '<a href="' . esc_url($reviewUrl) . '">' . esc_html($reviewLabel) . '</a>'
            : '';

        $time = (string) ($context['time'] ?? '');
        if ($time !== '') {
            $time = substr($time, 0, 5);
        }

        return [
            'reservation.id'              => (string) ($context['id'] ?? ''),
            'reservation.status'          => (string) ($context['status'] ?? ''),
            'reservation.status_label'    => (string) ($context['status_label'] ?? ''),
            'reservation.date'            => (string) ($context['date'] ?? ''),
            'reservation.time'            => $time,
            'reservation.formatted_date'  => (string) ($context['date_formatted'] ?? ($context['date'] ?? '')),
            'reservation.formatted_time'  => (string) ($context['time_formatted'] ?? $time),
            'reservation.party'           => (string) ($context['party'] ?? ''),
            'reservation.meal'            => (string) ($context['meal'] ?? ''),
            'reservation.manage_url'      => esc_url($manageUrl),
            'reservation.manage_link'     => $manageLink,
            'customer.first_name'         => esc_html((string) ($customer['first_name'] ?? '')),
            'customer.last_name'          => esc_html((string) ($customer['last_name'] ?? '')),
            'restaurant.name'             => esc_html((string) ($restaurant['name'] ?? '')),
            'restaurant.logo_url'         => $logoUrl,
            'restaurant.logo_img'         => $logoImg,
            'review.url'                  => esc_url($reviewUrl),
            'review.link'                 => $reviewLink,
            'emails.year'                 => esc_html((string) gmdate('Y')),
        ];
    }

    private function replaceTokens(string $template, array $tokens, bool $html): string
    {
        $rendered = $template;

        foreach ($tokens as $key => $value) {
            $rendered = str_replace('{{' . $key . '}}', (string) $value, $rendered);
        }

        if ($html) {
            if (preg_replace('/<[^>]+>/', '', $rendered) === $rendered) {
                $rendered = wp_autop($rendered);
            }

            return $this->sanitizeHtml($rendered);
        }

        return wp_strip_all_tags($rendered);
    }

    private function sanitizeHtml(string $html): string
    {
        $allowed = wp_kses_allowed_html('post');

        foreach (['div', 'p', 'span', 'table', 'tbody', 'thead', 'tfoot', 'tr', 'td', 'th', 'img', 'a', 'ul', 'ol', 'li'] as $tag) {
            if (!isset($allowed[$tag])) {
                $allowed[$tag] = [];
            }

            $allowed[$tag]['style'] = true;
            $allowed[$tag]['class'] = true;
        }

        if (isset($allowed['img'])) {
            $allowed['img']['width']  = true;
            $allowed['img']['height'] = true;
        }

        return wp_kses($html, $allowed);
    }

    /**
     * @param array<string, mixed> $context
     */
    private function wrapLayout(string $header, string $body, string $footer, array $context): string
    {
        $header = trim($header);
        $body   = trim($body);
        $footer = trim($footer);

        $locale = (string) ($context['locale'] ?? '');
        if ($locale === '') {
            $locale = (string) ($context['language'] ?? '');
        }

        if ($locale === '') {
            $locale = $this->language->getDefaultLanguage();
        }

        $langAttr = str_replace('_', '-', $locale);
        $langAttr = strtolower((string) preg_replace('/[^a-zA-Z\-]/', '', $langAttr));
        if ($langAttr === '') {
            $langAttr = 'en';
        }

        $restaurant = is_array($context['restaurant'] ?? null) ? $context['restaurant'] : [];
        $title      = esc_html((string) ($restaurant['name'] ?? 'FP Reservations'));

        $headerRow = $header !== ''
            ? '<tr><td class="fp-resv-email__section fp-resv-email__section--header" style="padding:32px 32px 0 32px;background-color:#ffffff;">' . $header . '</td></tr>'
            : '';

        $bodyRow = '<tr><td class="fp-resv-email__section fp-resv-email__section--body" style="padding:32px;background-color:#ffffff;color:#111827;font-size:16px;line-height:1.6;">' . $body . '</td></tr>';

        $footerRow = $footer !== ''
            ? '<tr><td class="fp-resv-email__section fp-resv-email__section--footer" style="padding:24px 32px;background-color:#f9fafb;border-top:1px solid #e5e7eb;color:#6b7280;font-size:13px;line-height:1.5;text-align:center;">' . $footer . '</td></tr>'
            : '';

        $innerTable = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="fp-resv-email__container" style="width:100%;max-width:640px;margin:0 auto;border-collapse:collapse;background-color:#ffffff;border-radius:16px;overflow:hidden;box-shadow:0 15px 40px rgba(15,23,42,0.08);">'
            . $headerRow
            . $bodyRow
            . $footerRow
            . '</table>';

        return '<!DOCTYPE html>'
            . '<html lang="' . esc_attr($langAttr) . '">' . '<head>'
            . '<meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1.0">'
            . '<title>' . $title . '</title>'
            . '</head>'
            . '<body style="margin:0;padding:0;background-color:#f3f4f6;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" class="fp-resv-email" style="width:100%;border-collapse:collapse;background-color:#f3f4f6;padding:32px 0;">'
            . '<tr><td align="center" style="padding:0 16px;">'
            . $innerTable
            . '</td></tr>'
            . '</table>'
            . '</body>'
            . '</html>';
    }
}
