<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Settings;

use FP\Resv\Core\Roles;
use function add_action;
use function add_filter;
use function admin_url;
use function current_user_can;
use function esc_attr;
use function esc_html__;
use function esc_js;
use function get_current_screen;
use function strpos;

/**
 * Migliora il menu admin FP Reservations con ordine voci, separatori visivi e admin bar (pattern FP-Experiences).
 */
final class AdminMenuEnhancer
{
    private const PARENT_SLUG = 'fp-resv-settings';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'reorderSubmenus'], 99);
        add_action('admin_head', [$this, 'renderSubmenuSectionEnhancements']);
        add_action('admin_bar_menu', [$this, 'registerAdminBarLinks'], 80);
        add_filter('admin_body_class', [$this, 'addAdminBodyClass']);
    }

    public function reorderSubmenus(): void
    {
        global $submenu;

        if (!isset($submenu[self::PARENT_SLUG]) || !is_array($submenu[self::PARENT_SLUG])) {
            return;
        }

        $items    = $submenu[self::PARENT_SLUG];
        $bucketed = [];

        foreach ($items as $item) {
            if (!is_array($item) || !isset($item[2])) {
                continue;
            }
            $slug = (string) $item[2];
            if (!isset($bucketed[$slug])) {
                $bucketed[$slug] = [];
            }
            $bucketed[$slug][] = $item;
        }

        $desiredOrder = [
            self::PARENT_SLUG,
            'fp-resv-manager',
            'fp-resv-layout',
            'fp-resv-analytics',
            'fp-resv-notifications',
            'fp-resv-payments',
            'fp-resv-brevo',
            'fp-resv-google-calendar',
            'fp-resv-style',
            'fp-resv-form-colors',
            'fp-resv-language',
            'fp-resv-orari-speciali',
            'fp-resv-tracking',
            'fp-resv-debug',
            'fp-resv-diagnostics',
        ];

        $reordered = [];

        foreach ($desiredOrder as $slug) {
            if (!isset($bucketed[$slug])) {
                continue;
            }
            foreach ($bucketed[$slug] as $entry) {
                $reordered[] = $entry;
            }
            unset($bucketed[$slug]);
        }

        foreach ($bucketed as $entries) {
            foreach ($entries as $entry) {
                $reordered[] = $entry;
            }
        }

        $submenu[self::PARENT_SLUG] = $reordered;
    }

    public function renderSubmenuSectionEnhancements(): void
    {
        if (!current_user_can(Roles::MANAGE_RESERVATIONS) && !current_user_can('manage_options')) {
            return;
        }

        ?>
        <style>
            #toplevel_page_fp-resv-settings .wp-submenu li.fpresv-submenu-section-start {
                margin-top: 8px;
                padding-top: 8px;
                border-top: 1px solid rgba(240, 246, 252, 0.18);
            }
            #toplevel_page_fp-resv-settings .wp-submenu li.fpresv-submenu-section-start::before {
                content: attr(data-section-label);
                display: block;
                margin: 0 10px 6px 10px;
                font-size: 10px;
                line-height: 1.2;
                letter-spacing: 0.08em;
                text-transform: uppercase;
                color: rgba(240, 246, 252, 0.62);
                font-weight: 600;
                pointer-events: none;
            }
        </style>
        <script>
        document.addEventListener('DOMContentLoaded', function () {
            const root = document.querySelector('#toplevel_page_fp-resv-settings .wp-submenu');
            if (!root) return;
            const markers = [
                { selector: 'a[href*="page=fp-resv-manager"]', label: '<?php echo esc_js(__('Operatività', 'fp-restaurant-reservations')); ?>' },
                { selector: 'a[href*="page=fp-resv-notifications"]', label: '<?php echo esc_js(__('Gestione', 'fp-restaurant-reservations')); ?>' },
                { selector: 'a[href*="page=fp-resv-settings"]', label: '<?php echo esc_js(__('Sistema', 'fp-restaurant-reservations')); ?>' },
            ];
            markers.forEach(function (marker) {
                const link = root.querySelector(marker.selector);
                if (!link) return;
                const item = link.closest('li');
                if (!item) return;
                item.classList.add('fpresv-submenu-section-start');
                item.setAttribute('data-section-label', marker.label);
            });
        });
        </script>
        <?php
    }

    /**
     * @param \WP_Admin_Bar $adminBar
     */
    public function registerAdminBarLinks($adminBar): void
    {
        if (!current_user_can(Roles::MANAGE_RESERVATIONS) && !current_user_can('manage_options') && !current_user_can(Roles::VIEW_RESERVATIONS_MANAGER)) {
            return;
        }

        $screen    = get_current_screen();
        $screenId  = $screen ? ($screen->id ?? '') : '';
        $isPlugin  = strpos($screenId, 'fp-resv') !== false;

        $adminBar->add_node([
            'id'    => 'fp-resv',
            'title' => esc_html__('FP Reservations', 'fp-restaurant-reservations'),
            'href'  => admin_url('admin.php?page=fp-resv-settings'),
            'meta'  => $isPlugin ? ['aria-current' => 'page'] : [],
        ]);

        if (current_user_can(Roles::MANAGE_RESERVATIONS) || current_user_can('manage_options')) {
            $adminBar->add_node([
                'id'     => 'fp-resv-manager',
                'parent' => 'fp-resv',
                'title'  => esc_html__('Manager', 'fp-restaurant-reservations'),
                'href'   => admin_url('admin.php?page=fp-resv-manager'),
            ]);
            $adminBar->add_node([
                'id'     => 'fp-resv-closures',
                'parent' => 'fp-resv',
                'title'  => esc_html__('Calendario Operativo', 'fp-restaurant-reservations'),
                'href'   => admin_url('admin.php?page=fp-resv-manager&fp_resv_tab=closures'),
            ]);
        }

        $adminBar->add_node([
            'id'     => 'fp-resv-settings',
            'parent' => 'fp-resv',
            'title'  => esc_html__('Impostazioni', 'fp-restaurant-reservations'),
            'href'   => admin_url('admin.php?page=fp-resv-settings'),
        ]);
    }

    public function addAdminBodyClass(string $classes): string
    {
        $screen = get_current_screen();
        if (!$screen) {
            return $classes;
        }
        $screenId = $screen->id ?? '';
        if (strpos($screenId, 'fp-resv') !== false && strpos($classes, 'fp-resv-admin-shell') === false) {
            $classes .= ' fp-resv-admin-shell';
        }
        return $classes;
    }
}
