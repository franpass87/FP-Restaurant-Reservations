<?php

declare(strict_types=1);

namespace FP\Resv\Domain\Events;

use function get_page_by_path;
use function get_permalink;
use function is_string;

/**
 * Risolve il permalink per un evento.
 * Estratto da Service per migliorare la manutenibilità.
 */
final class EventPermalinkResolver
{
    /**
     * Risolve il permalink per un evento.
     */
    public function resolve(string $slug): string
    {
        if ($slug === '') {
            return '';
        }

        $post = get_page_by_path($slug, \OBJECT, 'fp_event');
        if ($post !== null) {
            $link = get_permalink($post);
            if (is_string($link)) {
                return $link;
            }
        }

        return '';
    }
}















