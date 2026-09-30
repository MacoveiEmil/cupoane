<?php

declare(strict_types=1);

namespace Cupons\Core\Front;

use Cupons\Core\Content\Links;
use Cupons\Core\Content\Sync;
use Cupons\Core\Keys;
use Cupons\Core\Query\Offers;
use Cupons\Core\Stats\Clicks;
use Cupons\Core\Stats\Votes;
use Cupons\Core\Support\Dates;

/**
 * Datele gata de afișat pentru temă. Tema nu citește meta direct și nu face interogări proprii:
 * totul trece pe aici, deci regulile (oferte active, ordine, praguri) sunt într-un singur loc.
 */
final class View
{
    /** @var array<int,array> */
    private static array $votes = [];

    /* ---------- Oferte ---------- */

    /** Datele unui card de cupon. */
    public static function offer(\WP_Post $post): array
    {
        $id    = $post->ID;
        $m     = static fn(string $k) => (string) get_post_meta($id, $k, true);
        $code  = $m(Keys::M_CODE);
        $vtype = $m(Keys::M_VALUE_TYPE) ?: 'procent';
        $value = $m(Keys::M_VALUE);

        return [
            'id'        => $id,
            'anchor'    => 'c-' . $id,
            'title'     => get_the_title($post),
            'has_code'  => $code !== '',
            'code'      => $code,
            'masked'    => $code !== '' ? mb_substr($code, 0, 2) . '****' : '',
            'badge'     => self::badge($vtype, $value, $code !== ''),
            'exclusive' => $m(Keys::M_EXCLUSIVE) === '1',
            'expiry'    => self::expiry($m(Keys::M_EXPIRES)),
            'verified'  => Dates::human($m(Keys::M_VERIFIED)),
            'verified_ymd' => $m(Keys::M_VERIFIED),
            'terms'     => $m(Keys::M_TERMS),
            'go'        => Links::go_post($id),
            'store'     => Links::store_of($id),
            'tags'      => self::terms($id, Keys::TAG),
            'votes'     => self::$votes[$id] ?? Votes::summary(0, 0),
        ];
    }

    /** Datele unui card de deal. */
    public static function deal(\WP_Post $post): array
    {
        $id = $post->ID;
        $m  = static fn(string $k) => (string) get_post_meta($id, $k, true);
        return [
            'id'        => $id,
            'anchor'    => 'c-' . $id,
            'title'     => get_the_title($post),
            'image_id'  => (int) $m(Keys::M_IMAGE),
            'price_old' => self::money($m(Keys::M_PRICE_OLD)),
            'price_new' => self::money($m(Keys::M_PRICE_NEW)),
            'percent'   => (int) $m(Keys::M_PERCENT),
            'expiry'    => self::expiry($m(Keys::M_EXPIRES)),
            'verified'  => Dates::human($m(Keys::M_VERIFIED)),
            'go'        => Links::go_post($id),
            'store'     => Links::store_of($id),
        ];
    }

    /** Preîncarcă voturile pentru o listă de cupoane (un singur query). @param int[] $ids */
    public static function prime_votes(array $ids): void
    {
        self::$votes = Votes::totals($ids) + self::$votes;
    }

    private static function badge(string $type, string $value, bool $has_code): array
    {
        if ($type === 'transport') {
            return ['text' => 'Transport gratuit', 'kind' => 'ship'];
        }
        if ($value !== '' && (float) $value > 0) {
            $num = str_replace('.', ',', $value);
            return $type === 'lei'
                ? ['text' => $num . ' lei', 'kind' => 'lei']
                : ['text' => '−' . $num . '%', 'kind' => 'pct'];
        }
        return ['text' => $has_code ? 'Cod' : 'Ofertă', 'kind' => 'plain'];
    }

    /** @return array{label:string,soon:bool,date:string} */
    private static function expiry(string $ymd): array
    {
        if (Dates::normalize($ymd) === '') {
            return ['label' => '', 'soon' => false, 'date' => ''];
        }
        $today = Dates::today();
        if ($ymd === $today) {
            return ['label' => 'Expiră azi', 'soon' => true, 'date' => $ymd];
        }
        if ($ymd === Dates::offset(1)) {
            return ['label' => 'Expiră mâine', 'soon' => true, 'date' => $ymd];
        }
        return ['label' => 'Valabil până la ' . Dates::human($ymd), 'soon' => false, 'date' => $ymd];
    }

    private static function money(string $v): string
    {
        if ($v === '') {
            return '';
        }
        $f = (float) $v;
        return number_format($f, fmod($f, 1.0) > 0 ? 2 : 0, ',', '.') . ' lei';
    }

    /** @return \WP_Term[] */
    private static function terms(int $id, string $tax): array
    {
        $t = get_the_terms($id, $tax);
        return is_array($t) ? $t : [];
    }

    /* ---------- Magazine ---------- */

    public static function store(\WP_Term $t): array
    {
        $info = (array) get_term_meta($t->term_id, Keys::T_INFO, true);
        $rows = [];
        foreach (Keys::INFO_FIELDS as $k => $label) {
            if (!empty($info[$k])) {
                $rows[] = ['label' => $label, 'value' => (string) $info[$k]];
            }
        }
        $faq = array_values(array_filter((array) get_term_meta($t->term_id, Keys::T_FAQ, true), 'is_array'));

        return [
            'id'          => $t->term_id,
            'name'        => $t->name,
            'url'         => (string) get_term_link($t),
            'go'          => Links::go_store($t),
            'logo_id'     => (int) get_term_meta($t->term_id, Keys::T_LOGO, true),
            'count'       => Offers::count_for_term($t->term_id, Keys::STORE),
            'updated'     => Dates::human((string) get_term_meta($t->term_id, Keys::T_UPDATED, true)),
            'updated_ymd' => (string) get_term_meta($t->term_id, Keys::T_UPDATED, true),
            'info'        => $rows,
            'faq'         => $faq,
            'description' => (string) term_description($t),
            'site_url'    => (string) get_term_meta($t->term_id, Keys::T_URL, true),
        ];
    }

    /**
     * Logo ca <img> WebP cu width/height, sau un placeholder cu inițiala (zero requesturi).
     * @param array{size?:string,px?:int,lazy?:bool,priority?:bool} $o
     */
    public static function logo(\WP_Term $t, array $o = []): string
    {
        $size = $o['size'] ?? Images::LOGO;
        $px   = $o['px'] ?? 120;
        $id   = (int) get_term_meta($t->term_id, Keys::T_LOGO, true);

        if ($id && wp_attachment_is_image($id)) {
            $attrs = [
                'alt'      => $t->name,
                'class'    => 'logo__img',
                'loading'  => ($o['lazy'] ?? true) ? 'lazy' : 'eager',
                'decoding' => 'async',
            ];
            if (!empty($o['priority'])) {
                $attrs['fetchpriority'] = 'high';
                $attrs['loading']       = 'eager';
            }
            $img = wp_get_attachment_image($id, $size, false, $attrs + ['sizes' => $px . 'px']);
            if ($img !== '') {
                return $img;
            }
        }

        $initial = mb_strtoupper(mb_substr(remove_accents($t->name), 0, 1));
        $hue     = crc32($t->slug) % 360;
        return sprintf(
            '<span class="logo__ph" style="--h:%d" role="img" aria-label="%s">%s</span>',
            $hue, esc_attr($t->name), esc_html($initial)
        );
    }

    /**
     * Cea mai bună ofertă activă a unui magazin, ca etichetă scurtă („până la −40%”, „până la 50 lei”, „Transport gratuit”).
     * Doar din oferte reale active; '' dacă nu există valori numerice.
     */
    public static function best_offer_label(\WP_Term $store): string
    {
        $ids = get_posts([
            'post_type'        => Keys::COUPON,
            'posts_per_page'   => 100,
            'fields'           => 'ids',
            'no_found_rows'    => true,
            'suppress_filters' => false,
            'tax_query'        => [['taxonomy' => Keys::STORE, 'terms' => $store->term_id]],
        ]);
        $best = ['procent' => 0.0, 'lei' => 0.0, 'transport' => false];
        foreach ($ids as $id) {
            $type  = (string) get_post_meta($id, Keys::M_VALUE_TYPE, true);
            $value = (float) get_post_meta($id, Keys::M_VALUE, true);
            if ($type === 'transport') {
                $best['transport'] = true;
            } elseif (isset($best[$type]) && $value > $best[$type]) {
                $best[$type] = $value;
            }
        }
        $num = static fn(float $v) => str_replace('.', ',', rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.'));
        if ($best['procent'] > 0) {
            return 'până la −' . $num($best['procent']) . '%';
        }
        if ($best['lei'] > 0) {
            return 'până la ' . $num($best['lei']) . ' lei';
        }
        return $best['transport'] ? 'Transport gratuit' : '';
    }

    /** Cifre reale pentru homepage: oferte active și magazine cu oferte. @return array{offers:int,stores:int} */
    public static function stats(): array
    {
        $stores = Offers::counts_by_term(Keys::STORE);
        return ['offers' => array_sum($stores), 'stores' => count(array_filter($stores))];
    }

    /** Magazine similare: aceleași categorii, cu cele mai multe oferte active. @return \WP_Term[] */
    public static function similar_stores(\WP_Term $store, int $limit = 6): array
    {
        $cats = Sync::store_categories($store->term_id);
        if (!$cats) {
            return [];
        }
        $terms = get_terms([
            'taxonomy'   => Keys::STORE,
            'hide_empty' => false,
            'exclude'    => [$store->term_id],
            'meta_query' => [['key' => Keys::T_CATS, 'value' => array_map('strval', $cats), 'compare' => 'IN']],
        ]);
        return self::rank_stores(is_array($terms) ? $terms : [], $limit);
    }

    /** Magazine populare: ordinea din admin, apoi cele cu cele mai multe oferte. @return \WP_Term[] */
    public static function popular_stores(int $limit = 12): array
    {
        $ordered = get_terms([
            'taxonomy'   => Keys::STORE,
            'hide_empty' => false,
            'number'     => $limit,
            'meta_key'   => Keys::T_HOME_ORDER,
            'orderby'    => 'meta_value_num',
            'order'      => 'ASC',
            'meta_query' => [['key' => Keys::T_HOME_ORDER, 'value' => 0, 'compare' => '>', 'type' => 'NUMERIC']],
        ]);
        $ordered = is_array($ordered) ? $ordered : [];
        if (count($ordered) >= $limit) {
            return $ordered;
        }
        $all  = get_terms(['taxonomy' => Keys::STORE, 'hide_empty' => false, 'exclude' => wp_list_pluck($ordered, 'term_id')]);
        $fill = self::rank_stores(is_array($all) ? $all : [], $limit - count($ordered));
        return array_merge($ordered, $fill);
    }

    /** Sortare după oferte active (desc), doar magazine cu oferte. @param \WP_Term[] $terms */
    private static function rank_stores(array $terms, int $limit): array
    {
        $counts = Offers::counts_by_term(Keys::STORE);
        $terms  = array_filter($terms, static fn($t) => ($counts[$t->term_id] ?? 0) > 0);
        usort($terms, static fn($a, $b) => [($counts[$b->term_id] ?? 0), $a->name] <=> [($counts[$a->term_id] ?? 0), $b->name]);
        return array_slice(array_values($terms), 0, $limit);
    }

    /** Toate magazinele grupate după inițială (fără diacritice). @return array<string,array> */
    public static function stores_az(): array
    {
        $terms  = get_terms(['taxonomy' => Keys::STORE, 'hide_empty' => false, 'orderby' => 'name']);
        $counts = Offers::counts_by_term(Keys::STORE);
        $groups = [];
        foreach (is_array($terms) ? $terms : [] as $t) {
            $l = mb_strtoupper(mb_substr(remove_accents($t->name), 0, 1));
            $l = ctype_alpha($l) ? $l : '0–9';
            $groups[$l][] = ['term' => $t, 'count' => $counts[$t->term_id] ?? 0];
        }
        ksort($groups, SORT_NATURAL);
        return $groups;
    }

    /* ---------- Categorii ---------- */

    /** Numărul de magazine per categorie (inclusiv subcategoriile). @return array<int,int> */
    public static function stores_per_category(): array
    {
        static $map = null;
        if ($map !== null) {
            return $map;
        }
        $sets  = [];
        $terms = get_terms(['taxonomy' => Keys::STORE, 'hide_empty' => false, 'fields' => 'ids']);
        foreach (is_array($terms) ? $terms : [] as $store_id) {
            foreach (Sync::store_categories_with_ancestors((int) $store_id) as $cat) {
                $sets[$cat][(int) $store_id] = true;
            }
        }
        return $map = array_map('count', $sets);
    }

    /** @return array<int,array{term:\WP_Term,url:string,stores:int,offers:int,children:array}> */
    public static function categories_tree(): array
    {
        $stores = self::stores_per_category();
        $offers = Offers::counts_by_term(Keys::CATEGORY);
        $row    = static fn(\WP_Term $t) => [
            'term'   => $t,
            'url'    => (string) get_term_link($t),
            'stores' => $stores[$t->term_id] ?? 0,
            'offers' => $offers[$t->term_id] ?? 0,
        ];

        $out     = [];
        $parents = get_terms(['taxonomy' => Keys::CATEGORY, 'hide_empty' => false, 'parent' => 0, 'orderby' => 'name']);
        foreach (is_array($parents) ? $parents : [] as $p) {
            $item             = $row($p);
            $item['children'] = [];
            $children         = get_terms(['taxonomy' => Keys::CATEGORY, 'hide_empty' => false, 'parent' => $p->term_id, 'orderby' => 'name']);
            foreach (is_array($children) ? $children : [] as $c) {
                $item['children'][] = $row($c) + ['children' => []];
            }
            $out[] = $item;
        }
        return $out;
    }

    /** Magazinele unei categorii (inclusiv subcategorii), după oferte active. @return \WP_Term[] */
    public static function stores_in_category(\WP_Term $cat, int $limit = 24): array
    {
        $ids   = array_merge([$cat->term_id], array_map('intval', get_term_children($cat->term_id, Keys::CATEGORY) ?: []));
        $terms = get_terms([
            'taxonomy'   => Keys::STORE,
            'hide_empty' => false,
            'meta_query' => [['key' => Keys::T_CATS, 'value' => array_map('strval', $ids), 'compare' => 'IN']],
        ]);
        return self::rank_stores(is_array($terms) ? $terms : [], $limit);
    }

    /* ---------- Secțiunile homepage / Top oferte ---------- */

    /** Top oferte: bifate manual, completate cu cele mai accesate în ultimele 30 de zile. @return \WP_Post[] */
    public static function top_offers(int $limit = 8): array
    {
        $manual = get_posts(self::active([
            'posts_per_page' => $limit,
            'meta_query'     => [['key' => Keys::M_TOP, 'value' => '1']],
            'orderby'        => 'modified',
        ]));
        if (count($manual) >= $limit) {
            return $manual;
        }
        $popular = array_diff(Clicks::top(Keys::CLICK_COUPON, 30, 60), wp_list_pluck($manual, 'ID'));
        if ($popular) {
            $more = get_posts(self::active([
                'post__in'       => array_values($popular),
                'orderby'        => 'post__in',
                'posts_per_page' => $limit - count($manual),
            ]));
            $manual = array_merge($manual, $more);
        }
        return $manual;
    }

    /** @return \WP_Post[] */
    public static function exclusive_offers(int $limit = 6, array $exclude = []): array
    {
        // $exclude: oferte deja afișate mai sus pe pagină (fără ID-uri #c-{id} duplicate).
        return get_posts(self::active([
            'posts_per_page' => $limit,
            'post__not_in'   => array_map('intval', $exclude),
            'meta_query'     => [['key' => Keys::M_EXCLUSIVE, 'value' => '1']],
        ]));
    }

    /** @return \WP_Post[] */
    public static function recent_offers(int $limit = 8, array $exclude = []): array
    {
        return get_posts(self::active(['posts_per_page' => $limit, 'post__not_in' => array_map('intval', $exclude)]));
    }

    /** Deal-uri active, paginate. */
    public static function deals_query(int $paged = 1, int $per_page = Offers::PER_PAGE): \WP_Query
    {
        return new \WP_Query([
            'post_type'           => Keys::DEAL,
            'post_status'         => 'publish',
            'posts_per_page'      => $per_page,
            'paged'               => max(1, $paged),
            'orderby'             => 'date',
            'order'               => 'DESC',
            'ignore_sticky_posts' => true,
        ]);
    }

    /** Deal-urile active ale unui magazin. @return \WP_Post[] */
    public static function store_deals(\WP_Term $store, int $limit = 6): array
    {
        return get_posts([
            'post_type'        => Keys::DEAL,
            'posts_per_page'   => $limit,
            'suppress_filters' => false,
            'tax_query'        => [['taxonomy' => Keys::STORE, 'terms' => $store->term_id]],
        ]);
    }

    private static function active(array $args): array
    {
        return $args + [
            'post_type'        => Keys::COUPON,
            'post_status'      => 'publish',
            'orderby'          => 'date',
            'order'            => 'DESC',
            'suppress_filters' => false,
            'no_found_rows'    => true,
        ];
    }

    /* ---------- Căutare ---------- */

    /** @return array{stores:\WP_Term[],categories:\WP_Term[]} */
    public static function search(string $q): array
    {
        $q = trim(mb_substr($q, 0, 60));
        if (mb_strlen($q) < 2) {
            return ['stores' => [], 'categories' => []];
        }
        $stores = get_terms(['taxonomy' => Keys::STORE, 'hide_empty' => false, 'search' => $q, 'number' => 30]);
        $cats   = get_terms(['taxonomy' => Keys::CATEGORY, 'hide_empty' => false, 'search' => $q, 'number' => 10]);
        return [
            'stores'     => self::rank_stores(is_array($stores) ? $stores : [], 30) ?: (is_array($stores) ? $stores : []),
            'categories' => is_array($cats) ? $cats : [],
        ];
    }

    public static function search_query(): string
    {
        // phpcs:ignore WordPress.Security.NonceVerification -- formular public GET, doar citire.
        return isset($_GET['q']) ? sanitize_text_field(wp_unslash((string) $_GET['q'])) : '';
    }
}
