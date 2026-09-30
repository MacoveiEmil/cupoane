<?php

declare(strict_types=1);

namespace Cupons\Core\Front;

use Cupons\Core\Content\Pages;
use Cupons\Core\Keys;

/** Breadcrumbs: aceleași elemente pentru HTML (temă) și pentru schema BreadcrumbList. */
final class Breadcrumbs
{
    /** @return array<int,array{name:string,url:string}> Ultimul element = pagina curentă. */
    public static function items(): array
    {
        static $items = null;
        if ($items !== null) {
            return $items;
        }

        $items = [];
        if (is_front_page()) {
            return $items;
        }

        $home  = ['name' => 'Acasă', 'url' => home_url('/')];
        $items = [$home];

        if (is_tax(Keys::STORE)) {
            self::push_page($items, 'magazine');
            $term    = get_queried_object();
            $items[] = ['name' => $term->name, 'url' => (string) get_term_link($term)];
        } elseif (is_tax(Keys::CATEGORY)) {
            self::push_page($items, 'categorii');
            $term = get_queried_object();
            if ($term->parent) {
                $parent = get_term($term->parent, Keys::CATEGORY);
                if ($parent instanceof \WP_Term) {
                    $items[] = ['name' => $parent->name, 'url' => (string) get_term_link($parent)];
                }
            }
            $items[] = ['name' => $term->name, 'url' => (string) get_term_link($term)];
        } elseif (is_tax(Keys::TAG)) {
            self::push_page($items, 'top-oferte');
            $term    = get_queried_object();
            $items[] = ['name' => $term->name, 'url' => (string) get_term_link($term)];
        } elseif (is_page()) {
            $id      = get_queried_object_id();
            $items[] = ['name' => get_the_title($id), 'url' => (string) get_permalink($id)];
        } elseif (is_404()) {
            $items[] = ['name' => 'Pagină negăsită', 'url' => ''];
        } elseif (is_singular()) {
            $id      = get_queried_object_id();
            $items[] = ['name' => get_the_title($id), 'url' => (string) get_permalink($id)];
        }

        return $items;
    }

    private static function push_page(array &$items, string $key): void
    {
        $url = Pages::url($key);
        if ($url !== '') {
            $items[] = ['name' => get_the_title(Pages::id($key)), 'url' => $url];
        }
    }
}
