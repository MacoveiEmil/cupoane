<?php

declare(strict_types=1);

namespace Cupons\Core\Front;

use Cupons\Core\Keys;
use Cupons\Core\Query\Offers;

/**
 * JSON static mic cu magazinele, pentru sugestiile din căutare (încărcat lazy, la focus).
 * Regenerat la salvarea/ștergerea unui magazin și după fiecare purge (numărul de oferte se schimbă).
 * Format: [[nume, cale URL, oferte active], ...]
 */
final class SearchIndex
{
    private const DIR     = 'cupons';
    private const FILE    = 'magazine.json';
    private const VERSION = 'cupons_search_version';

    private static bool $dirty = false;

    public static function register_hooks(): void
    {
        foreach (['created_', 'edited_', 'delete_'] as $prefix) {
            add_action($prefix . Keys::STORE, [self::class, 'mark_dirty']);
        }
        add_action('cupons_store_saved', [self::class, 'mark_dirty']);
        add_action('cupons_purge_urls', [self::class, 'mark_dirty']);
        add_action('shutdown', [self::class, 'maybe_build'], 5);
    }

    public static function mark_dirty(): void
    {
        self::$dirty = true;
    }

    public static function maybe_build(): void
    {
        if (self::$dirty) {
            self::$dirty = false;
            self::build();
        }
    }

    public static function build(): bool
    {
        $terms = get_terms(['taxonomy' => Keys::STORE, 'hide_empty' => false, 'orderby' => 'name']);
        if (!is_array($terms)) {
            return false;
        }
        $counts = Offers::counts_by_term(Keys::STORE);
        $rows   = [];
        foreach ($terms as $t) {
            if (($counts[$t->term_id] ?? 0) === 0) {
                continue; // sugestiile arată doar magazine cu oferte active
            }
            $link = get_term_link($t);
            if (is_string($link)) {
                $rows[] = [$t->name, wp_make_link_relative($link), $counts[$t->term_id] ?? 0];
            }
        }
        usort($rows, static fn($a, $b) => [$b[2], $a[0]] <=> [$a[2], $b[0]]);

        $dir = self::dir();
        if (!wp_mkdir_p($dir['path'])) {
            return false;
        }
        $ok = file_put_contents($dir['path'] . '/' . self::FILE, wp_json_encode($rows, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), LOCK_EX);
        if ($ok !== false) {
            update_option(self::VERSION, (string) time(), true);
            return true;
        }
        return false;
    }

    /** URL cu versiune (cache-busting), sau '' dacă nu există încă. */
    public static function url(): string
    {
        $dir     = self::dir();
        $version = (string) get_option(self::VERSION, '');
        if ($version === '' || !is_readable($dir['path'] . '/' . self::FILE)) {
            if (!self::build()) {
                return '';
            }
            $version = (string) get_option(self::VERSION, '');
        }
        return $dir['url'] . '/' . self::FILE . '?v=' . rawurlencode($version);
    }

    /** @return array{path:string,url:string} */
    private static function dir(): array
    {
        $up = wp_upload_dir(null, false);
        return [
            'path' => trailingslashit($up['basedir']) . self::DIR,
            'url'  => set_url_scheme(trailingslashit($up['baseurl']) . self::DIR),
        ];
    }
}
