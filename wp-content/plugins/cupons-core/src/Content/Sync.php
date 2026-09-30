<?php

declare(strict_types=1);

namespace Cupons\Core\Content;

use Cupons\Core\Freshness\CachePurger;
use Cupons\Core\Keys;
use Cupons\Core\Support\Dates;

/**
 * Efectele secundare ale modificărilor de conținut:
 * - normalizarea meta-urilor ofertei;
 * - categoriile ofertei = categoriile magazinului;
 * - „Actualizat la” pe magazin;
 * - purge de cache pe paginile afectate.
 */
final class Sync
{
    public static function register_hooks(): void
    {
        add_action('wp_after_insert_post', [self::class, 'after_save'], 10, 4);
        add_action('transition_post_status', [self::class, 'on_transition'], 10, 3);
        add_action('before_delete_post', [self::class, 'on_delete'], 10, 2);
        add_action('set_object_terms', [self::class, 'on_set_terms'], 10, 6);
    }

    public static function after_save(int $post_id, \WP_Post $post, bool $update, ?\WP_Post $before): void
    {
        if (!in_array($post->post_type, Keys::offer_types(), true) || wp_is_post_revision($post_id) || $post->post_status === 'auto-draft') {
            return;
        }

        Normalizer::normalize($post_id);
        self::sync_categories($post_id);

        if ($post->post_status === 'publish') {
            $store = Links::store_of($post_id);
            if ($store) {
                self::touch_store($store->term_id);
            }
        }

        if ($post->post_status === 'publish' || ($before && $before->post_status === 'publish')) {
            CachePurger::instance()->add_post($post_id);
        }
    }

    public static function on_transition(string $new, string $old, \WP_Post $post): void
    {
        if ($new !== $old && ($old === 'publish' || $new === 'publish') && in_array($post->post_type, Keys::offer_types(), true)) {
            CachePurger::instance()->add_post($post->ID);
        }
    }

    public static function on_delete(int $post_id, \WP_Post $post): void
    {
        if ($post->post_status === 'publish' && in_array($post->post_type, Keys::offer_types(), true)) {
            CachePurger::instance()->add_post($post_id);
        }
    }

    /** Dacă se schimbă magazinul unei oferte, purge și pe magazinul vechi. */
    public static function on_set_terms(int $object_id, array $terms, array $tt_ids, string $taxonomy, bool $append, array $old_tt_ids): void
    {
        if ($taxonomy !== Keys::STORE || $old_tt_ids === $tt_ids) {
            return;
        }
        foreach ($old_tt_ids as $tt_id) {
            $term = get_term_by('term_taxonomy_id', (int) $tt_id, Keys::STORE);
            if ($term instanceof \WP_Term) {
                CachePurger::instance()->add_term($term);
            }
        }
    }

    /**
     * Categoriile unei oferte se copiază de pe magazinul ei, inclusiv părinții:
     * astfel numărul de oferte al unei categorii-părinte e exact (fără dubluri).
     */
    public static function sync_categories(int $post_id): void
    {
        $store = Links::store_of($post_id);
        $cats  = $store ? self::store_categories_with_ancestors($store->term_id) : [];
        wp_set_object_terms($post_id, $cats, Keys::CATEGORY, false);
    }

    /** @return int[] */
    public static function store_categories_with_ancestors(int $term_id): array
    {
        $out = [];
        foreach (self::store_categories($term_id) as $cat) {
            $out[] = $cat;
            foreach (get_ancestors($cat, Keys::CATEGORY, 'taxonomy') as $parent) {
                $out[] = (int) $parent;
            }
        }
        return array_values(array_unique($out));
    }

    /** Resincronizează toate ofertele unui magazin (după schimbarea categoriilor lui). */
    public static function resync_store(int $term_id): void
    {
        $ids = get_posts([
            'post_type'        => Keys::offer_types(),
            'post_status'      => 'any',
            'fields'           => 'ids',
            'posts_per_page'   => -1,
            'no_found_rows'    => true,
            'suppress_filters' => true,
            'cupons_all'       => true,
            'tax_query'        => [['taxonomy' => Keys::STORE, 'terms' => $term_id]],
        ]);
        $cats = self::store_categories_with_ancestors($term_id);
        foreach ($ids as $id) {
            wp_set_object_terms((int) $id, $cats, Keys::CATEGORY, false);
        }
    }

    /** @return int[] */
    public static function store_categories(int $term_id): array
    {
        $ids = array_map('intval', (array) get_term_meta($term_id, Keys::T_CATS, false));
        return array_values(array_filter($ids, static fn(int $id) => $id > 0 && term_exists($id, Keys::CATEGORY)));
    }

    public static function touch_store(int $term_id): void
    {
        update_term_meta($term_id, Keys::T_UPDATED, Dates::today());
    }
}
