<?php

declare(strict_types=1);

namespace Cupons\Core\Content;

use Cupons\Core\Keys;

/**
 * Linkurile de ieșire. Tema folosește doar go_*(): toate ieșirile trec prin /go/.
 */
final class Links
{
    /** /go/{id}/ pentru cupoane și deal-uri. */
    public static function go_post(int $post_id): string
    {
        return home_url('/go/' . $post_id . '/');
    }

    /** /go/{slug-magazin}/ pentru butonul „Mergi la magazin”. */
    public static function go_store(\WP_Term $store): string
    {
        return home_url('/go/' . $store->slug . '/');
    }

    /** Destinația reală a unei oferte (link de afiliere). */
    public static function target_for_post(\WP_Post $post): string
    {
        if ($post->post_type === Keys::DEAL) {
            $url = (string) get_post_meta($post->ID, Keys::M_DEEPLINK, true);
            return $url !== '' ? $url : (string) get_post_meta($post->ID, Keys::M_PRODUCT_URL, true);
        }

        $url = (string) get_post_meta($post->ID, Keys::M_LINK, true);
        if ($url !== '') {
            return $url;
        }
        $store = self::store_of($post->ID);
        return $store ? self::target_for_store($store) : '';
    }

    public static function target_for_store(\WP_Term $store): string
    {
        $aff = (string) get_term_meta($store->term_id, Keys::T_AFF, true);
        return $aff !== '' ? $aff : (string) get_term_meta($store->term_id, Keys::T_URL, true);
    }

    public static function store_of(int $post_id): ?\WP_Term
    {
        $terms = get_the_terms($post_id, Keys::STORE);
        return (is_array($terms) && $terms) ? $terms[0] : null;
    }
}
