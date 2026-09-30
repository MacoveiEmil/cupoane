<?php

declare(strict_types=1);

namespace Cupons\Core\Content;

use Cupons\Core\Keys;

/**
 * Cupoanele și deal-urile NU au pagini proprii: apar doar în listări (ancoră #c-{id}).
 * Ștergerea lor nu creează 404-uri.
 */
final class PostTypes
{
    public static function register_hooks(): void
    {
        add_action('init', [self::class, 'register'], 5);
    }

    public static function register(): void
    {
        $common = [
            'public'              => false,
            'publicly_queryable'  => false,
            'exclude_from_search' => true,
            'show_ui'             => true,
            'show_in_nav_menus'   => false,
            'show_in_admin_bar'   => true,
            'show_in_rest'        => false,
            'has_archive'         => false,
            'rewrite'             => false,
            'query_var'           => false,
            'capability_type'     => 'post',
            'map_meta_cap'        => true,
            'supports'            => ['title'],
            'delete_with_user'    => false,
        ];

        register_post_type(Keys::COUPON, $common + [
            'labels' => [
                'name'               => 'Cupoane',
                'singular_name'      => 'Cupon',
                'menu_name'          => 'Cupoane',
                'all_items'          => 'Toate cupoanele',
                'add_new'            => 'Adaugă cupon',
                'add_new_item'       => 'Adaugă cupon',
                'edit_item'          => 'Editează cuponul',
                'new_item'           => 'Cupon nou',
                'search_items'       => 'Caută cupoane',
                'not_found'          => 'Niciun cupon.',
                'not_found_in_trash' => 'Niciun cupon în coș.',
            ],
            'menu_icon'     => 'dashicons-tickets-alt',
            'menu_position' => 25,
        ]);

        register_post_type(Keys::DEAL, $common + [
            'labels' => [
                'name'               => 'Deal-uri',
                'singular_name'      => 'Deal',
                'menu_name'          => 'Deal-uri',
                'all_items'          => 'Deal-uri',
                'add_new'            => 'Adaugă deal',
                'add_new_item'       => 'Adaugă deal',
                'edit_item'          => 'Editează deal-ul',
                'new_item'           => 'Deal nou',
                'search_items'       => 'Caută deal-uri',
                'not_found'          => 'Niciun deal.',
                'not_found_in_trash' => 'Niciun deal în coș.',
            ],
            'show_in_menu' => 'edit.php?post_type=' . Keys::COUPON,
        ]);
    }
}
