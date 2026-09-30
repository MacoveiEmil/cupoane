<?php

declare(strict_types=1);

namespace Cupons\Core\Content;

/**
 * Paginile structurale ale site-ului, create automat (o singură dată; dacă le ștergi, se recreează
 * la următoarea actualizare a pluginului). Tema le afișează prin page-{slug}.php.
 * Conținutul paginilor statice îl scrie proprietarul.
 */
final class Pages
{
    public const OPTION  = 'cupons_pages';
    public const VERSION = '1';

    /** cheie => [titlu, slug] */
    public const PAGES = [
        'acasa'        => ['Acasă', 'acasa'],
        'magazine'     => ['Magazine', 'magazine'],
        'categorii'    => ['Categorii', 'categorii'],
        'deal-uri'     => ['Deal-uri', 'deal-uri'],
        'top-oferte'   => ['Top oferte', 'top-oferte'],
        'cauta'        => ['Caută', 'cauta'],
        'despre'       => ['Despre noi', 'despre-noi'],
        'metodologie'  => ['Metodologie', 'metodologie'],
        'cum'          => ['Cum funcționează', 'cum-functioneaza'],
        'transparenta' => ['Transparență și afiliere', 'transparenta-si-afiliere'],
        'calendar'     => ['Calendar reduceri', 'calendar-reduceri'],
        'contact'      => ['Contact', 'contact'],
        'termeni'      => ['Termeni și condiții', 'termeni-si-conditii'],
        'confidentialitate' => ['Confidențialitate', 'confidentialitate'],
        'cookies'      => ['Cookies', 'cookies'],
    ];

    /** Paginile din footer, în ordine. */
    public const FOOTER = ['despre', 'metodologie', 'cum', 'transparenta', 'calendar', 'contact', 'termeni', 'confidentialitate', 'cookies'];

    public static function register_hooks(): void
    {
        add_action('init', [self::class, 'maybe_create'], 20);
    }

    public static function maybe_create(): void
    {
        if (get_option(self::OPTION . '_version') === self::VERSION) {
            return;
        }
        self::ensure();
        update_option(self::OPTION . '_version', self::VERSION, false);
    }

    public static function ensure(): void
    {
        $ids = (array) get_option(self::OPTION, []);

        foreach (self::PAGES as $key => [$title, $slug]) {
            $id = (int) ($ids[$key] ?? 0);
            if ($id && ($p = get_post($id)) && $p->post_type === 'page' && $p->post_status !== 'trash') {
                continue;
            }
            $existing = get_page_by_path($slug, OBJECT, 'page');
            if ($existing instanceof \WP_Post && $existing->post_status !== 'trash') {
                $ids[$key] = $existing->ID;
                continue;
            }
            $new = wp_insert_post([
                'post_type'      => 'page',
                'post_status'    => 'publish',
                'post_title'     => $title,
                'post_name'      => $slug,
                'post_content'   => '',
                'comment_status' => 'closed',
                'ping_status'    => 'closed',
            ], true);
            if (!is_wp_error($new)) {
                $ids[$key] = (int) $new;
            }
        }

        update_option(self::OPTION, $ids, true);

        if (!empty($ids['acasa'])) {
            update_option('show_on_front', 'page');
            update_option('page_on_front', (int) $ids['acasa']);
        }
        if (!empty($ids['confidentialitate'])) {
            update_option('wp_page_for_privacy_policy', (int) $ids['confidentialitate']);
        }
    }

    public static function id(string $key): int
    {
        $ids = (array) get_option(self::OPTION, []);
        return (int) ($ids[$key] ?? 0);
    }

    public static function url(string $key): string
    {
        $id = self::id($key);
        if ($id && get_post_status($id) === 'publish') {
            return (string) get_permalink($id);
        }
        return $key === 'acasa' ? home_url('/') : '';
    }

    public static function is(string $key): bool
    {
        $id = self::id($key);
        return $id > 0 && is_page($id);
    }
}
