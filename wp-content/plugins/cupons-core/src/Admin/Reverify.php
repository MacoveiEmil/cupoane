<?php

declare(strict_types=1);

namespace Cupons\Core\Admin;

use Cupons\Core\Content\Links;
use Cupons\Core\Freshness\Cleanup;
use Cupons\Core\Keys;
use Cupons\Core\Query\Offers;
use Cupons\Core\Support\Dates;

/**
 * Lista „De reverificat”: oferte fără expirare, trecute automat în ciornă.
 * „Verificat azi” le republică și actualizează data verificării.
 */
final class Reverify
{
    public const SLUG   = 'cupons-reverify';
    public const ACTION = 'cupons_verify';

    public static function register_hooks(): void
    {
        add_action('admin_menu', [self::class, 'menu']);
        add_action('admin_post_' . self::ACTION, [self::class, 'handle']);
    }

    public static function menu(): void
    {
        $count = self::count();
        $badge = $count ? sprintf(' <span class="awaiting-mod"><span class="pending-count">%d</span></span>', $count) : '';
        add_submenu_page(
            'edit.php?post_type=' . Keys::COUPON,
            'De reverificat',
            'De reverificat' . $badge,
            'edit_posts',
            self::SLUG,
            [self::class, 'render']
        );
    }

    private static function query_args(): array
    {
        return [
            'post_type'      => Keys::offer_types(),
            'post_status'    => 'draft',
            'posts_per_page' => 200,
            'orderby'        => 'modified',
            'order'          => 'ASC',
            Offers::BYPASS   => true,
            'meta_query'     => [['key' => Keys::M_RECHECK, 'value' => '1']],
        ];
    }

    public static function count(): int
    {
        $q = new \WP_Query(['fields' => 'ids', 'posts_per_page' => 1] + self::query_args());
        return (int) $q->found_posts;
    }

    public static function render(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die('Nu ai acces.');
        }
        $posts = get_posts(self::query_args());
        $done  = isset($_GET['verified']) ? absint($_GET['verified']) : 0; // phpcs:ignore WordPress.Security.NonceVerification
        ?>
        <div class="wrap">
            <h1>De reverificat</h1>
            <p>Oferte fără dată de expirare care n-au fost verificate de mai mult de <?php echo (int) \Cupons\Core\Support\Settings::int('recheck_days'); ?> zile. Au fost scoase de pe site până le verifici.</p>
            <?php if ($done) : ?>
                <div class="notice notice-success is-dismissible"><p>Oferta a fost republicată.</p></div>
            <?php endif; ?>
            <?php if (!$posts) : ?>
                <p><strong>Nimic de reverificat.</strong></p>
            <?php else : ?>
                <table class="widefat striped">
                    <thead><tr><th>Ofertă</th><th>Magazin</th><th>Tip</th><th>Ultima verificare</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($posts as $post) :
                        $store = Links::store_of($post->ID);
                        $code  = (string) get_post_meta($post->ID, Keys::M_CODE, true);
                        $link  = Links::target_for_post($post);
                        ?>
                        <tr>
                            <td>
                                <strong><a href="<?php echo esc_url((string) get_edit_post_link($post->ID)); ?>"><?php echo esc_html(get_the_title($post)); ?></a></strong>
                                <?php if ($link) : ?><br><a href="<?php echo esc_url($link); ?>" target="_blank" rel="noopener noreferrer">Deschide în magazin ↗</a><?php endif; ?>
                            </td>
                            <td><?php echo $store ? esc_html($store->name) : '—'; ?></td>
                            <td><?php echo $post->post_type === Keys::DEAL ? 'Deal' : ($code !== '' ? '<code>' . esc_html($code) . '</code>' : 'Ofertă'); ?></td>
                            <td><?php echo esc_html(Dates::human((string) get_post_meta($post->ID, Keys::M_VERIFIED, true)) ?: '—'); ?></td>
                            <td>
                                <?php if (current_user_can('publish_post', $post->ID) || current_user_can('edit_post', $post->ID)) : ?>
                                <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                                    <input type="hidden" name="action" value="<?php echo esc_attr(self::ACTION); ?>">
                                    <input type="hidden" name="post_id" value="<?php echo (int) $post->ID; ?>">
                                    <?php wp_nonce_field(self::ACTION . '_' . $post->ID); ?>
                                    <button class="button button-primary">Verificat azi</button>
                                </form>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
        <?php
    }

    public static function handle(): void
    {
        $id = absint($_POST['post_id'] ?? 0);
        check_admin_referer(self::ACTION . '_' . $id);

        $post = get_post($id);
        if (!$post || !in_array($post->post_type, Keys::offer_types(), true)) {
            wp_die('Oferta nu există.');
        }
        $type = get_post_type_object($post->post_type);
        if (!current_user_can('edit_post', $id) || !current_user_can($type->cap->publish_posts)) {
            wp_die('Nu ai drepturi să publici această ofertă.');
        }

        Cleanup::mark_verified($id);

        wp_safe_redirect(add_query_arg(
            ['post_type' => Keys::COUPON, 'page' => self::SLUG, 'verified' => 1],
            admin_url('edit.php')
        ));
        exit;
    }
}
