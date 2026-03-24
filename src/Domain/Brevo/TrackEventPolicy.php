<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Brevo;

use FP\Resv\Domain\Settings\Options;
use function is_array;
use function sanitize_key;

/**
 * Abilitazione singoli eventi Track/Automation verso Brevo (checklist admin).
 */
final class TrackEventPolicy
{
    /**
     * Eventi noti inviati dal plugin (allineare a checklist in PagesConfig).
     */
    public const EVENT_IDS = [
        'email_confirmation',
        'email_reminder',
        'email_review',
        'reservation_confirmed',
        'reservation_visited',
        'survey_completed',
        'survey_negative',
        'post_visit_24h',
    ];

    public static function isEventEnabled(Options $options, string $event): bool
    {
        $event = sanitize_key($event);
        if ($event === '') {
            return false;
        }

        $brevo = $options->getGroup('fp_resv_brevo', []);

        $submittedFlag = $brevo['brevo_track_events_submitted'] ?? null;
        if ($submittedFlag !== '1' && $submittedFlag !== 1) {
            return true;
        }

        $map = $brevo['brevo_track_events'] ?? null;
        if (!is_array($map)) {
            return false;
        }

        return isset($map[$event]) && ($map[$event] === '1' || $map[$event] === 1 || $map[$event] === true);
    }
}
