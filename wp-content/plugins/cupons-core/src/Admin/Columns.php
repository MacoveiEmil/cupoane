<?php

declare(strict_types=1);

namespace Cupons\Core\Admin;

use Cupons\Core\Keys;
use Cupons\Core\Stats\Clicks;
use Cupons\Core\Support\Dates;

/** Coloane utile în listele de cupoane și deal-uri. */
final class Columns
{
    /** @var array<int,int> */
    private static array $clicks = [];

    public static function register_hooks(): void
    {
        foreach (Keys::offer_types() as $type) {
            add_filter("manage_{$type}_posts_columns", [self::class, 'columns']);
            add_action("manage_{$type}_posts_custom_column", [self::class, 'cell'], 10, 2);
        }
        add_action('load-edit.php', [self::class, 'preload_clicks']);
    }

    public static function columns(array $cols): array
    {
        $date = $cols['date'] ?? null;
        unset($cols['date']);
        $cols['cupons_offer']    = 'Cod / Preț';
        $cols['cupons_expires']  = 'Expiră';
        $cols['cupons_verified'] = 'Verificat';
        $cols['cupons_clicks']   = 'Click-uri 30z';
        if ($date) {
            $cols['date'] = $date;
        }
        return $cols;
    }

    public static function preload_clicks(): void
    {
        $type = sanitize_key($_GET['post_type'] ?? ''); // phpcs:ignore WordPress.Security.NonceVerification
        if (!in_array($type, Keys::offer_types(), true)) {
            return;
        }
        add_filter('the_posts', static function (array $posts, \WP_Query $q) use ($type) {
            if ($q->is_main_query()) {
                $ids          = wp_list_pluck($posts, 'ID');
                self::$clicks = Clicks::totals($type === Keys::DEAL ? Keys::CLICK_DEAL : Keys::CLICK_COUPON, $ids);
            }
            return $posts;
        }, 10, 2);
    }

    public static function cell(string $col, int $post_id): void
    {
        $m = static fn(string $k) => (string) get_post_meta($post_id, $k, true);
        switch ($col) {
            case 'cupons_offer':
                if (get_post_type($post_id) === Keys::DEAL) {
                    $old = $m(Keys::M_PRICE_OLD);
                    $new = $m(Keys::M_PRICE_NEW);
                    echo $new !== '' ? esc_html(($old !== '' ? $old . ' → ' : '') . $new . ' lei') : '—';
                } else {
                    $code = $m(Keys::M_CODE);
                    echo $code !== '' ? '<code>' . esc_html($code) . '</code>' : '<em>fără cod</em>';
                }
                break;
            case 'cupons_expires':
                $exp = $m(Keys::M_EXPIRES);
                echo $exp !== '' ? esc_html(Dates::human($exp)) : '<em>fără dată</em>';
                break;
            case 'cupons_verified':
                $v = $m(Keys::M_VERIFIED);
                echo $v !== '' ? esc_html(Dates::human($v)) : '—';
                if ($m(Keys::M_RECHECK) === '1') {
                    echo ' <span class="cupons-badge">de reverificat</span>';
                }
                break;
            case 'cupons_clicks':
                echo (int) (self::$clicks[$post_id] ?? 0);
                break;
        }
    }
}
