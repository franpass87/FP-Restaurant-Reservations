<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Events;

use DateTimeImmutable;
use DateTimeZone;
use function __;
use function add_action;
use function esc_html;
use function get_post_meta;
use function get_the_ID;
use function get_the_title;
use function is_singular;
use function register_post_meta;
use function register_post_type;
use function register_taxonomy;
use function strtotime;
use function wp_json_encode;
use function wp_strip_all_tags;

final class CPT
{
    private const META_PREFIX = '_fp_event_';

    public function register(): void
    {
        add_action('init', [$this, 'registerPostType']);
        add_action('init', [$this, 'registerTaxonomy']);
        add_action('init', [$this, 'registerMeta']);
        add_action('wp_head', [$this, 'outputSchema'], 5);
    }

    public function registerPostType(): void
    {
        register_post_type(
            'fp_event',
            [
                'labels' => [
                    'name'               => __('Eventi', 'fp-restaurant-reservations'),
                    'singular_name'      => __('Evento', 'fp-restaurant-reservations'),
                    'add_new'            => __('Aggiungi evento', 'fp-restaurant-reservations'),
                    'add_new_item'       => __('Aggiungi nuovo evento', 'fp-restaurant-reservations'),
                    'edit_item'          => __('Modifica evento', 'fp-restaurant-reservations'),
                    'new_item'           => __('Nuovo evento', 'fp-restaurant-reservations'),
                    'view_item'          => __('Visualizza evento', 'fp-restaurant-reservations'),
                    'search_items'       => __('Cerca eventi', 'fp-restaurant-reservations'),
                    'not_found'          => __('Nessun evento trovato', 'fp-restaurant-reservations'),
                    'not_found_in_trash' => __('Nessun evento nel cestino', 'fp-restaurant-reservations'),
                ],
                'public'             => true,
                'show_in_menu'       => 'fp-resv-settings',
                'menu_icon'          => 'dashicons-tickets-alt',
                'supports'           => ['title', 'editor', 'excerpt', 'thumbnail'],
                'show_in_rest'       => true,
                'has_archive'        => true,
                'rewrite'            => ['slug' => 'eventi'],
                'capability_type'    => 'post',
                'map_meta_cap'       => true,
            ]
        );
    }

    public function registerTaxonomy(): void
    {
        register_taxonomy(
            'fp_event_category',
            'fp_event',
            [
                'labels' => [
                    'name'          => __('Categorie evento', 'fp-restaurant-reservations'),
                    'singular_name' => __('Categoria evento', 'fp-restaurant-reservations'),
                    'search_items'  => __('Cerca categorie evento', 'fp-restaurant-reservations'),
                    'all_items'     => __('Tutte le categorie', 'fp-restaurant-reservations'),
                    'edit_item'     => __('Modifica categoria', 'fp-restaurant-reservations'),
                    'update_item'   => __('Aggiorna categoria', 'fp-restaurant-reservations'),
                    'add_new_item'  => __('Aggiungi nuova categoria', 'fp-restaurant-reservations'),
                    'new_item_name' => __('Nome nuova categoria', 'fp-restaurant-reservations'),
                    'menu_name'     => __('Categorie eventi', 'fp-restaurant-reservations'),
                ],
                'hierarchical'      => true,
                'show_admin_column' => true,
                'show_in_rest'      => true,
                'rewrite'           => ['slug' => 'categoria-evento'],
            ]
        );
    }

    public function registerMeta(): void
    {
        $metaKeys = [
            'start',
            'end',
            'capacity',
            'price',
            'currency',
            'location',
        ];

        foreach ($metaKeys as $key) {
            register_post_meta(
                'fp_event',
                self::META_PREFIX . $key,
                [
                    'type'              => 'string',
                    'single'            => true,
                    'show_in_rest'      => true,
                    'sanitize_callback' => static fn ($value): string => wp_strip_all_tags((string) $value),
                ]
            );
        }
    }

    public function outputSchema(): void
    {
        if (!is_singular('fp_event')) {
            return;
        }

        $postId = (int) get_the_ID();
        if ($postId <= 0) {
            return;
        }

        $startRaw = (string) get_post_meta($postId, self::META_PREFIX . 'start', true);
        $endRaw   = (string) get_post_meta($postId, self::META_PREFIX . 'end', true);
        $capacity = (string) get_post_meta($postId, self::META_PREFIX . 'capacity', true);
        $price    = (string) get_post_meta($postId, self::META_PREFIX . 'price', true);
        $currency = (string) get_post_meta($postId, self::META_PREFIX . 'currency', true);
        $location = (string) get_post_meta($postId, self::META_PREFIX . 'location', true);

        $start = $this->normalizeDate($startRaw);
        $end   = $this->normalizeDate($endRaw);

        $schema = [
            '@context'              => 'https://schema.org',
            '@type'                 => 'Event',
            'name'                  => esc_html(get_the_title($postId)),
            'startDate'             => $start,
            'endDate'               => $end ?? $start,
            'eventStatus'           => 'https://schema.org/EventScheduled',
            'eventAttendanceMode'   => 'https://schema.org/OfflineEventAttendanceMode',
        ];

        if ($location !== '') {
            $schema['location'] = [
                '@type'   => 'Place',
                'name'    => esc_html($location),
                'address' => esc_html($location),
            ];
        }

        if ($capacity !== '') {
            $schema['maximumAttendeeCapacity'] = (int) $capacity;
        }

        if ($price !== '') {
            $schema['offers'] = [
                '@type'         => 'Offer',
                'price'         => (float) $price,
                'priceCurrency' => $currency !== '' ? $currency : 'EUR',
                'availability'  => 'https://schema.org/InStock',
            ];
        }

        $json = wp_json_encode($schema, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (!is_string($json)) {
            return;
        }

        echo '<script type="application/ld+json">' . $json . '</script>';
    }

    private function normalizeDate(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $timestamp = strtotime($value);
        if ($timestamp === false) {
            return null;
        }

        $date = new DateTimeImmutable('@' . $timestamp);
        $date = $date->setTimezone(new DateTimeZone('UTC'));

        return $date->format('c');
    }
}
