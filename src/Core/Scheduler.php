<?php

declare(strict_types=1);

namespace FP\Resv\Core;

use FP\Resv\Domain\Brevo\AutomationService;
use function add_action;
use function do_action;
use function wp_next_scheduled;
use function wp_schedule_event;
use const DAY_IN_SECONDS;
use const HOUR_IN_SECONDS;

final class Scheduler
{
    public static function init(): void
    {
        add_action('init', [self::class, 'ensureEvents']);
        add_action('fp_resv_run_postvisit_jobs', [self::class, 'runPostVisitJobs']);
        add_action('fp_resv_retention_cleanup', [self::class, 'runRetentionCleanup']);
    }

    public static function ensureEvents(): void
    {
        if (!wp_next_scheduled('fp_resv_run_postvisit_jobs')) {
            wp_schedule_event(time() + HOUR_IN_SECONDS, 'hourly', 'fp_resv_run_postvisit_jobs');
        }

        if (!wp_next_scheduled('fp_resv_retention_cleanup')) {
            wp_schedule_event(time() + DAY_IN_SECONDS, 'daily', 'fp_resv_retention_cleanup');
        }
    }

    public static function runPostVisitJobs(): void
    {
        $container = \FP\Resv\Kernel\LegacyBridge::getContainer();
        $automation = $container->get(AutomationService::class);
        if ($automation instanceof AutomationService) {
            $automation->processDueJobs();
        }
    }

    public static function runRetentionCleanup(): void
    {
        $container = \FP\Resv\Kernel\LegacyBridge::getContainer();
        $privacy = $container->get(Privacy::class);
        if ($privacy instanceof Privacy) {
            $results = $privacy->runRetentionCleanup();
            do_action('fp_resv_retention_cleanup_completed', $results);
        }
    }
}
