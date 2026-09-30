<?php

declare(strict_types=1);

namespace Cupons\Core\Content;

use Cupons\Core\Keys;

final class Taxonomies
{
    public static function register_hooks(): void
    {
        add_action('init', [self::class, 'register'], 5);
        // Slug-urile numerice de magazin ar intra în conflict cu /go/{id}/.
        add_filter('wp_unique_term_slug', [self::class, 'no_numeric_store_slug'], 10, 2);
    }

    public static function register(): void
    {
        $objects = Keys::offer_types();

        register_taxonomy(Keys::STORE, $objects, [
            'labels' => self::labels('Magazine', 'Magazin', 'Toate magazinele'),
            'public'             => true,
            'publicly_queryable' => true,
            'hierarchical'       => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_admin_column'  => true,
            'show_in_nav_menus'  => true,
            'show_tagcloud'      => false,
            'show_in_quick_edit' => false,
            'show_in_rest'       => false,
            'meta_box_cb'        => false, // selectat din caseta „Detalii”
            'rewrite'            => ['slug' => 'magazin', 'with_front' => false],
        ]);

        // Categoriile se aleg pe magazin; pe cupoane/deal-uri sunt sincronizate automat (Sync).
        register_taxonomy(Keys::CATEGORY, $objects, [
            'labels' => self::labels('Categorii', 'Categorie', 'Toate categoriile'),
            'public'             => true,
            'publicly_queryable' => true,
            'hierarchical'       => true,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_admin_column'  => false,
            'show_in_nav_menus'  => true,
            'show_tagcloud'      => false,
            'show_in_quick_edit' => false,
            'show_in_rest'       => false,
            'meta_box_cb'        => false,
            'rewrite'            => ['slug' => 'categorie', 'with_front' => false, 'hierarchical' => false],
        ]);

        register_taxonomy(Keys::TAG, $objects, [
            'labels' => self::labels('Etichete', 'Etichetă', 'Toate etichetele'),
            'public'             => true,
            'publicly_queryable' => true,
            'hierarchical'       => false,
            'show_ui'            => true,
            'show_in_menu'       => true,
            'show_admin_column'  => true,
            'show_in_nav_menus'  => true,
            'show_tagcloud'      => false,
            'show_in_quick_edit' => false,
            'show_in_rest'       => false,
            'meta_box_cb'        => false,
            'rewrite'            => ['slug' => 'oferte', 'with_front' => false],
        ]);
    }

    public static function ensure_default_tags(): void
    {
        foreach (Keys::DEFAULT_TAGS as $slug => $name) {
            if (!term_exists($slug, Keys::TAG)) {
                wp_insert_term($name, Keys::TAG, ['slug' => $slug]);
            }
        }
    }

    public static function no_numeric_store_slug(string $slug, object $term): string
    {
        if (($term->taxonomy ?? '') === Keys::STORE && ctype_digit($slug)) {
            return 'magazin-' . $slug;
        }
        return $slug;
    }

    private static function labels(string $plural, string $singular, string $all): array
    {
        return [
            'name'          => $plural,
            'singular_name' => $singular,
            'menu_name'     => $plural,
            'all_items'     => $all,
            'edit_item'     => 'Editează ' . mb_strtolower($singular),
            'view_item'     => 'Vezi ' . mb_strtolower($singular),
            'update_item'   => 'Actualizează',
            'add_new_item'  => 'Adaugă ' . mb_strtolower($singular),
            'new_item_name' => 'Nume nou',
            'search_items'  => 'Caută',
            'not_found'     => 'Nimic găsit.',
            'back_to_items' => '← Înapoi',
            'parent_item'   => 'Părinte',
        ];
    }
}
