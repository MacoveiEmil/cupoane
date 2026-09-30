<?php

declare(strict_types=1);

namespace Cupons\Core\Query;

use Cupons\Core\Keys;
use Cupons\Core\Support\Dates;

/**
 * Toate interogările de oferte. Regula de bază: pe frontend apar DOAR ofertele active
 * (publicate, start ≤ azi, expirare ≥ azi sau fără expirare). Regula e impusă global
 * prin pre_get_posts, deci nicio listare nu o poate „uita”.
 */
final class Offers
{
    /** Query var care dezactivează filtrul (doar pentru cod intern: curățare, sync, admin). */
    public const BYPASS = 'cupons_all';

    /** Oferte pe pagină în listările paginate (categorie, etichetă, deal-uri). */
    public const PER_PAGE = 24;

    private const COUNTS_TRANSIENT = 'cupons_counts_';

    /** @var array<string,array<int,int>> */
    private static array $memo = [];

    public static function register_hooks(): void
    {
        // Prioritate mare: rulează după orice altă modificare a query-ului (temă, rute).
        add_action('pre_get_posts', [self::class, 'enforce_active'], 99);
    }

    public static function enforce_active(\WP_Query $q): void
    {
        // Doar frontend: admin, WP-CLI și cron văd toate ofertele.
        if (is_admin() || $q->get(self::BYPASS) || (defined('WP_CLI') && WP_CLI) || wp_doing_cron()) {
            return;
        }

        $types = (array) $q->get('post_type');
        if (!array_intersect($types, Keys::offer_types())) {
            return;
        }

        $existing = $q->get('meta_query');
        $meta     = self::active_meta_query();
        if (!empty($existing)) {
            $meta = ['relation' => 'AND', $meta, $existing];
        }
        $q->set('meta_query', $meta);
        $q->set('post_status', 'publish');
    }

    /** Aceeași regulă ca active_meta_query(), pentru o singură ofertă (fără status). */
    public static function is_active(int $post_id): bool
    {
        $today = Dates::today();
        $from  = (string) get_post_meta($post_id, Keys::M_FROM, true) ?: Keys::DATE_MIN;
        $until = (string) get_post_meta($post_id, Keys::M_UNTIL, true) ?: Keys::DATE_MAX;
        return $from <= $today && $until >= $today;
    }

    /** Clauze numite: pot fi folosite în orderby ('cupons_until' etc.). */
    public static function active_meta_query(?string $today = null): array
    {
        $today ??= Dates::today();
        return [
            'relation'     => 'AND',
            'cupons_from'  => ['key' => Keys::M_FROM, 'value' => $today, 'compare' => '<=', 'type' => 'DATE'],
            'cupons_until' => ['key' => Keys::M_UNTIL, 'value' => $today, 'compare' => '>=', 'type' => 'DATE'],
        ];
    }

    /**
     * Numărul de oferte active per termen al unei taxonomii (magazin/categorie/etichetă).
     * Un singur query SQL, rezultat păstrat într-un transient valabil azi și invalidat la purge.
     *
     * @return array<int,int> term_id => număr
     */
    public static function counts_by_term(string $taxonomy): array
    {
        $today = Dates::today();
        $key   = self::COUNTS_TRANSIENT . md5($taxonomy . $today);

        if (isset(self::$memo[$key])) {
            return self::$memo[$key];
        }

        $cached = get_transient($key);
        if (is_array($cached)) {
            return self::$memo[$key] = $cached;
        }

        global $wpdb;
        [$t1, $t2] = Keys::offer_types();
        // Datele sunt stocate Y-m-d, deci comparația lexicografică e corectă.
        $rows = $wpdb->get_results($wpdb->prepare(
            "SELECT tt.term_id, COUNT(DISTINCT p.ID) AS c
             FROM {$wpdb->posts} p
             INNER JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
             INNER JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = %s
             INNER JOIN {$wpdb->postmeta} mf ON mf.post_id = p.ID AND mf.meta_key = %s AND mf.meta_value <= %s
             INNER JOIN {$wpdb->postmeta} mu ON mu.post_id = p.ID AND mu.meta_key = %s AND mu.meta_value >= %s
             WHERE p.post_status = 'publish' AND p.post_type IN (%s, %s)
             GROUP BY tt.term_id",
            $taxonomy, Keys::M_FROM, $today, Keys::M_UNTIL, $today, $t1, $t2
        ));

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row->term_id] = (int) $row->c;
        }

        set_transient($key, $counts, DAY_IN_SECONDS);
        return self::$memo[$key] = $counts;
    }

    public static function count_for_term(int $term_id, string $taxonomy): int
    {
        return self::counts_by_term($taxonomy)[$term_id] ?? 0;
    }

    public static function flush_counts(): void
    {
        self::$memo = [];
        $today      = Dates::today();
        foreach ([Keys::STORE, Keys::CATEGORY, Keys::TAG] as $tax) {
            delete_transient(self::COUNTS_TRANSIENT . md5($tax . $today));
        }
    }
}
