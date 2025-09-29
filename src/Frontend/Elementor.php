<?php

declare(strict_types=1);

namespace FP\Resv\Frontend;

use FP\Resv\Frontend\Elementor\ReservationWidget;
use function add_action;
use function class_exists;
use function did_action;
use function is_object;
use function method_exists;

final class Elementor
{
    public static function register(): void
    {
        if (did_action('elementor/loaded')) {
            add_action('elementor/widgets/register', [self::class, 'onWidgetsRegister']);
            return;
        }

        add_action('elementor/loaded', static function (): void {
            add_action('elementor/widgets/register', [self::class, 'onWidgetsRegister']);
        });
    }

    /**
     * @param mixed $widgetsManager
     */
    public static function onWidgetsRegister(mixed $widgetsManager): void
    {
        if (!class_exists('\\Elementor\\Widget_Base')) {
            return;
        }

        if (!class_exists(ReservationWidget::class)) {
            return;
        }

        if (!is_object($widgetsManager) || !method_exists($widgetsManager, 'register')) {
            return;
        }

        $widgetsManager->register(new ReservationWidget());
    }
}
