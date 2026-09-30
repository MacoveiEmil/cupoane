<?php

declare(strict_types=1);

namespace Cupons\Core\Content;

use Cupons\Core\Keys;
use Cupons\Core\Support\Sanitize;

/**
 * Înregistrează meta-urile tipizate cu sanitizare la nivelul API-ului de meta,
 * astfel încât orice scriere (admin, import, WP-CLI) trece prin aceleași reguli.
 */
final class Meta
{
    public static function register_hooks(): void
    {
        add_action('init', [self::class, 'register'], 6);

        // Câmpurile normalizate (from/until) se recalculează la orice schimbare de start/expirare.
        foreach (['added_post_meta', 'updated_post_meta', 'deleted_post_meta'] as $hook) {
            add_action($hook, [self::class, 'on_meta_change'], 10, 3);
        }
    }

    public static function register(): void
    {
        $auth = static fn($allowed, $meta_key, $post_id) => current_user_can('edit_post', $post_id);

        $common = [
            Keys::M_EXPIRES   => [Sanitize::class, 'date'],
            Keys::M_START     => [Sanitize::class, 'date'],
            Keys::M_VERIFIED  => [Sanitize::class, 'date'],
            Keys::M_EXCLUSIVE => [Sanitize::class, 'flag'],
            Keys::M_TOP       => [Sanitize::class, 'flag'],
            Keys::M_RECHECK   => [Sanitize::class, 'flag'],
            Keys::M_FROM      => [Sanitize::class, 'date'],
            Keys::M_UNTIL     => [Sanitize::class, 'date'],
            Keys::M_EXT_ID    => 'sanitize_text_field',
        ];

        $coupon = [
            Keys::M_TYPE       => static fn($v) => Sanitize::enum($v, ['cod', 'oferta'], 'oferta'),
            Keys::M_CODE       => [Sanitize::class, 'code'],
            Keys::M_VALUE      => [Sanitize::class, 'amount'],
            Keys::M_VALUE_TYPE => static fn($v) => Sanitize::enum($v, array_keys(Keys::VALUE_TYPES), 'procent'),
            Keys::M_TERMS      => [Sanitize::class, 'rich'],
            Keys::M_LINK       => [Sanitize::class, 'url'],
        ];

        $deal = [
            Keys::M_PRODUCT_URL => [Sanitize::class, 'url'],
            Keys::M_DEEPLINK    => [Sanitize::class, 'url'],
            Keys::M_IMAGE       => 'absint',
            Keys::M_PRICE_OLD   => [Sanitize::class, 'amount'],
            Keys::M_PRICE_NEW   => [Sanitize::class, 'amount'],
            Keys::M_PERCENT     => 'absint',
        ];

        foreach ([Keys::COUPON => $common + $coupon, Keys::DEAL => $common + $deal] as $type => $fields) {
            foreach ($fields as $key => $sanitize) {
                register_post_meta($type, $key, [
                    'type'              => 'string',
                    'single'            => true,
                    'show_in_rest'      => false,
                    'sanitize_callback' => $sanitize,
                    'auth_callback'     => $auth,
                ]);
            }
        }
    }

    public static function on_meta_change(int|array $meta_id, int $post_id, string $meta_key): void
    {
        if ($meta_key !== Keys::M_START && $meta_key !== Keys::M_EXPIRES) {
            return;
        }
        if (!in_array(get_post_type($post_id), Keys::offer_types(), true)) {
            return;
        }
        Normalizer::dates($post_id);
    }
}
