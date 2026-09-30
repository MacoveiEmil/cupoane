<?php

declare(strict_types=1);

namespace Cupons\Core\Admin;

use Cupons\Core\Keys;

/** Categoriile au maxim 2 niveluri: părinte → subcategorie. */
final class CategoryRules
{
    public static function register_hooks(): void
    {
        add_filter('taxonomy_parent_dropdown_args', [self::class, 'dropdown'], 10, 2);
        add_filter('pre_insert_term', [self::class, 'check_insert'], 10, 3);
        add_filter('wp_update_term_parent', [self::class, 'check_update'], 10, 3);
    }

    public static function dropdown(array $args, string $taxonomy): array
    {
        if ($taxonomy === Keys::CATEGORY) {
            $args['depth'] = 1; // doar categorii de nivel 1 pot fi părinți
        }
        return $args;
    }

    public static function check_insert(mixed $term, string $taxonomy, array $args = []): mixed
    {
        if ($taxonomy !== Keys::CATEGORY) {
            return $term;
        }
        $parent = (int) ($args['parent'] ?? 0);
        if ($parent && self::depth($parent) >= 1) {
            return new \WP_Error('cupons_depth', 'Categoriile pot avea maxim 2 niveluri.');
        }
        return $term;
    }

    public static function check_update(int $parent, int $term_id, string $taxonomy): int
    {
        if ($taxonomy !== Keys::CATEGORY || !$parent) {
            return $parent;
        }
        $has_children = (bool) get_term_children($term_id, $taxonomy);
        if (self::depth($parent) >= 1 || $has_children) {
            $current = get_term($term_id, $taxonomy);
            return $current instanceof \WP_Term ? (int) $current->parent : 0;
        }
        return $parent;
    }

    private static function depth(int $term_id): int
    {
        return count(get_ancestors($term_id, Keys::CATEGORY, 'taxonomy'));
    }
}
