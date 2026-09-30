<?php

declare(strict_types=1);

namespace Cupons\Core\Query;

use Cupons\Core\Keys;

/**
 * Query-ul principal pentru paginile de magazin, categorie și etichetă.
 * Filtrul de oferte active (Offers::enforce_active, prioritate 99) se aplică peste acesta.
 */
final class Archives
{
    /** Limita de oferte pe pagina unui magazin (fără paginare). */
    public const STORE_LIMIT = 100;

    public static function register_hooks(): void
    {
        add_action('pre_get_posts', [self::class, 'main_query'], 10);
        add_action('template_redirect', [self::class, 'search_redirect'], 5);
    }

    public static function main_query(\WP_Query $q): void
    {
        if (is_admin() || !$q->is_main_query()) {
            return;
        }

        if ($q->is_tax(Keys::STORE)) {
            $q->set('post_type', [Keys::COUPON]);
            $q->set('posts_per_page', self::STORE_LIMIT);
            $q->set('no_found_rows', true);
            self::order($q, false);
            return;
        }

        if ($q->is_tax([Keys::CATEGORY, Keys::TAG])) {
            $q->set('post_type', [Keys::COUPON]);
            $q->set('posts_per_page', Offers::PER_PAGE);
            self::order($q, true);
        }
    }

    /** Ordinea: (top) → exclusive → verificate recent → cele mai noi. */
    private static function order(\WP_Query $q, bool $top_first): void
    {
        $meta = [
            'relation'    => 'AND',
            'cupons_excl' => ['key' => Keys::M_EXCLUSIVE, 'compare' => 'EXISTS'],
            'cupons_ver'  => ['key' => Keys::M_VERIFIED, 'compare' => 'EXISTS'],
        ];
        $orderby = ['cupons_excl' => 'DESC', 'cupons_ver' => 'DESC', 'date' => 'DESC'];

        if ($top_first) {
            $meta['cupons_top'] = ['key' => Keys::M_TOP, 'compare' => 'EXISTS'];
            $orderby            = ['cupons_top' => 'DESC'] + $orderby;
        }

        $q->set('meta_query', $meta);
        $q->set('orderby', $orderby);
    }

    /** Căutarea nativă WordPress (?s=) duce la pagina noastră de căutare în magazine. */
    public static function search_redirect(): void
    {
        if (!is_search() || is_admin()) {
            return;
        }
        $url = \Cupons\Core\Content\Pages::url('cauta');
        if ($url === '') {
            return;
        }
        $q = trim((string) get_search_query(false));
        wp_safe_redirect($q !== '' ? add_query_arg('q', rawurlencode($q), $url) : $url, 301);
        exit;
    }
}
