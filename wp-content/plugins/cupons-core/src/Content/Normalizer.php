<?php

declare(strict_types=1);

namespace Cupons\Core\Content;

use Cupons\Core\Keys;
use Cupons\Core\Support\Dates;

/**
 * Garantează că fiecare ofertă are câmpurile folosite la interogări și sortări.
 * Fără asta, un meta lipsă ar scoate oferta din listări (JOIN) sau ar strica ordinea.
 */
final class Normalizer
{
    public static function normalize(int $post_id): void
    {
        self::dates($post_id);

        foreach ([Keys::M_EXCLUSIVE, Keys::M_TOP] as $key) {
            if (get_post_meta($post_id, $key, true) === '') {
                update_post_meta($post_id, $key, '0');
            }
        }

        if (get_post_meta($post_id, Keys::M_VERIFIED, true) === '') {
            update_post_meta($post_id, Keys::M_VERIFIED, Dates::today());
        }

        if (get_post_type($post_id) === Keys::COUPON) {
            $code = (string) get_post_meta($post_id, Keys::M_CODE, true);
            update_post_meta($post_id, Keys::M_TYPE, $code !== '' ? 'cod' : 'oferta');
        }

        if (get_post_type($post_id) === Keys::DEAL) {
            $old = (float) get_post_meta($post_id, Keys::M_PRICE_OLD, true);
            $new = (float) get_post_meta($post_id, Keys::M_PRICE_NEW, true);
            $pct = ($old > 0 && $new > 0 && $new < $old) ? (int) round(($old - $new) / $old * 100) : 0;
            update_post_meta($post_id, Keys::M_PERCENT, (string) $pct);
        }
    }

    public static function dates(int $post_id): void
    {
        $start  = Dates::normalize(get_post_meta($post_id, Keys::M_START, true));
        $expire = Dates::normalize(get_post_meta($post_id, Keys::M_EXPIRES, true));

        self::set_raw($post_id, Keys::M_FROM, $start ?: Keys::DATE_MIN);
        self::set_raw($post_id, Keys::M_UNTIL, $expire ?: Keys::DATE_MAX);
    }

    /** Scriere fără a redeclanșa on_meta_change pentru chei diferite de start/expirare. */
    private static function set_raw(int $post_id, string $key, string $value): void
    {
        if (get_post_meta($post_id, $key, true) !== $value) {
            update_post_meta($post_id, $key, $value);
        }
    }
}
