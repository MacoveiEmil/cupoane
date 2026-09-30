<?php

declare(strict_types=1);

namespace Cupons\Core\Admin;

use Cupons\Core\Keys;

final class Assets
{
    public static function register_hooks(): void
    {
        add_action('admin_enqueue_scripts', [self::class, 'enqueue']);
    }

    public static function enqueue(): void
    {
        $screen = get_current_screen();
        if (!$screen) {
            return;
        }
        $ours = in_array($screen->post_type, Keys::offer_types(), true)
            || in_array($screen->taxonomy, [Keys::STORE, Keys::CATEGORY, Keys::TAG], true)
            || str_contains((string) $screen->id, 'cupons');
        if (!$ours) {
            return;
        }

        $needs_media = $screen->taxonomy === Keys::STORE || ($screen->base === 'post' && $screen->post_type === Keys::DEAL);
        if ($needs_media) {
            wp_enqueue_media();
        }

        wp_enqueue_style('cupons-admin', CUPONS_CORE_URL . 'assets/admin.css', [], CUPONS_CORE_VERSION);
        wp_enqueue_script('cupons-admin', CUPONS_CORE_URL . 'assets/admin.js', [], CUPONS_CORE_VERSION, ['in_footer' => true]);
    }
}
