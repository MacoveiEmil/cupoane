<?php

declare(strict_types=1);

namespace Cupons\Core\Import;

use Cupons\Core\Keys;
use Cupons\Core\Support\Tables;

/**
 * Programele din rețele și asocierea lor cu magazinele.
 * Asocierea = term meta pe magazin (rețea + ID program) + term_id în tabela de programe.
 */
final class Programs
{
    /** @var array<string,array<string,int>> */
    private static array $maps = [];

    /**
     * Salvează programul; dacă nu e asociat, încearcă asocierea automată după domeniu.
     * @return int term_id asociat (0 = neasociat)
     */
    public static function upsert(Program $p): int
    {
        global $wpdb;
        $table = Tables::programs();
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (network, program_id, name, site_url, unique_code, term_id, last_seen)
             VALUES (%s, %s, %s, %s, %s, 0, %s)
             ON DUPLICATE KEY UPDATE name = VALUES(name), site_url = VALUES(site_url),
                 unique_code = IF(VALUES(unique_code) = '', unique_code, VALUES(unique_code)), last_seen = VALUES(last_seen)",
            $p->network, $p->id, mb_substr($p->name, 0, 190), mb_substr($p->url, 0, 255), $p->unique_code, current_time('mysql', true)
        ));

        $term_id = self::term_for($p->network, $p->id);
        if ($term_id === 0) {
            // Doar dacă magazinul găsit nu e deja legat de alt program (un magazin = o rețea).
            $match   = self::match_by_domain($p->url);
            $term_id = ($match && self::associate($p->network, $p->id, $match, false)) ? $match : 0;
        }
        return $term_id;
    }

    /** Magazinul asociat unui program (după term meta), sau 0. */
    public static function term_for(string $network, string $program_id): int
    {
        return self::map($network)[$program_id] ?? 0;
    }

    /** @return array<string,int> program_id => term_id pentru o rețea */
    public static function map(string $network): array
    {
        if (isset(self::$maps[$network])) {
            return self::$maps[$network];
        }
        $terms = get_terms([
            'taxonomy'   => Keys::STORE,
            'hide_empty' => false,
            'meta_query' => [
                ['key' => Keys::T_NETWORK, 'value' => $network],
                ['key' => Keys::T_PROGRAM, 'value' => '', 'compare' => '!='],
            ],
        ]);
        $map = [];
        foreach (is_array($terms) ? $terms : [] as $t) {
            $map[(string) get_term_meta($t->term_id, Keys::T_PROGRAM, true)] = (int) $t->term_id;
        }
        return self::$maps[$network] = $map;
    }

    /**
     * Asociază un program cu un magazin. $overwrite=false nu schimbă un magazin deja legat de alt program.
     */
    public static function associate(string $network, string $program_id, int $term_id, bool $overwrite = true): bool
    {
        global $wpdb;
        if (!$term_id || !term_exists($term_id, Keys::STORE)) {
            return false;
        }
        $current = (string) get_term_meta($term_id, Keys::T_PROGRAM, true);
        if (!$overwrite && $current !== '' && $current !== $program_id) {
            return false;
        }
        update_term_meta($term_id, Keys::T_NETWORK, $network);
        update_term_meta($term_id, Keys::T_PROGRAM, $program_id);
        unset(self::$maps[$network]);
        $wpdb->update(Tables::programs(), ['term_id' => $term_id], ['network' => $network, 'program_id' => $program_id]);
        return true;
    }

    public static function unique_code(string $network, string $program_id): string
    {
        global $wpdb;
        return (string) $wpdb->get_var($wpdb->prepare(
            'SELECT unique_code FROM ' . Tables::programs() . ' WHERE network = %s AND program_id = %s',
            $network, $program_id
        ));
    }

    /** Programele neasociate cu niciun magazin. @return array<int,object> */
    public static function unmapped(int $limit = 200): array
    {
        global $wpdb;
        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT network, program_id, name, site_url, last_seen FROM ' . Tables::programs() . ' ORDER BY network, name LIMIT %d',
            $limit * 3
        ));
        $out = [];
        foreach ($rows as $r) {
            if (self::term_for($r->network, $r->program_id) === 0) {
                $out[] = $r;
            }
        }
        return array_slice($out, 0, $limit);
    }

    /** Magazinul al cărui URL are același domeniu (fără www), dacă e unic. */
    private static function match_by_domain(string $url): int
    {
        $host = self::host($url);
        if ($host === '') {
            return 0;
        }
        $terms = get_terms(['taxonomy' => Keys::STORE, 'hide_empty' => false, 'meta_key' => Keys::T_URL]);
        $found = [];
        foreach (is_array($terms) ? $terms : [] as $t) {
            if (self::host((string) get_term_meta($t->term_id, Keys::T_URL, true)) === $host) {
                $found[] = (int) $t->term_id;
            }
        }
        return count($found) === 1 ? $found[0] : 0;
    }

    private static function host(string $url): string
    {
        if ($url !== '' && !str_contains($url, '://')) {
            $url = 'https://' . $url;
        }
        $host = strtolower((string) wp_parse_url($url, PHP_URL_HOST));
        return (string) preg_replace('/^www\./', '', $host);
    }
}
