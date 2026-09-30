<?php

declare(strict_types=1);

namespace Cupons\Core\Admin;

use Cupons\Core\Content\Links;
use Cupons\Core\Keys;
use Cupons\Core\Support\Sanitize;

/** Caseta „Detalii” pentru deal-uri (produse anume). */
final class DealBox
{
    public static function register_hooks(): void
    {
        add_action('add_meta_boxes_' . Keys::DEAL, [self::class, 'add']);
        add_action('save_post_' . Keys::DEAL, [self::class, 'save'], 10, 2);
        add_filter('enter_title_here', static fn($t, $post) => $post->post_type === Keys::DEAL ? 'Numele produsului' : $t, 10, 2);
    }

    public static function add(): void
    {
        add_meta_box('cupons_deal', 'Detalii deal', [self::class, 'render'], Keys::DEAL, 'normal', 'high');
    }

    public static function render(\WP_Post $post): void
    {
        $m     = static fn(string $k) => (string) get_post_meta($post->ID, $k, true);
        $store = Links::store_of($post->ID);
        $tags  = wp_get_object_terms($post->ID, Keys::TAG, ['fields' => 'ids']);
        $pct   = (int) $m(Keys::M_PERCENT);

        wp_nonce_field('cupons_offer_save', CouponBox::NONCE);
        ?>
        <table class="form-table cupons-form">
            <tr>
                <th><label for="cupons-store">Magazin *</label></th>
                <td><?php Fields::select('cupons[store]', Fields::store_options(), $store ? (string) $store->term_id : '', 'cupons-store'); ?></td>
            </tr>
            <tr>
                <th><label for="cupons-product">URL produs *</label></th>
                <td><input type="url" id="cupons-product" name="cupons[product_url]" value="<?php echo esc_attr($m(Keys::M_PRODUCT_URL)); ?>" class="large-text" placeholder="https://"></td>
            </tr>
            <tr>
                <th><label for="cupons-deeplink">Deeplink de afiliere</label></th>
                <td>
                    <input type="url" id="cupons-deeplink" name="cupons[deeplink]" value="<?php echo esc_attr($m(Keys::M_DEEPLINK)); ?>" class="large-text" placeholder="https://">
                    <p class="description">Dacă e gol, redirectul duce la URL-ul produsului (fără comision).</p>
                </td>
            </tr>
            <tr>
                <th>Imagine</th>
                <td><?php Fields::media('cupons[image]', (int) $m(Keys::M_IMAGE), 'Alege imaginea produsului'); ?></td>
            </tr>
            <tr>
                <th>Preț</th>
                <td>
                    <label>Vechi <input type="text" inputmode="decimal" name="cupons[price_old]" value="<?php echo esc_attr($m(Keys::M_PRICE_OLD)); ?>" class="small-text"> lei</label>
                    <label>Nou <input type="text" inputmode="decimal" name="cupons[price_new]" value="<?php echo esc_attr($m(Keys::M_PRICE_NEW)); ?>" class="small-text"> lei</label>
                    <?php if ($pct > 0) : ?><strong>−<?php echo (int) $pct; ?>%</strong><?php endif; ?>
                    <p class="description">Procentul se calculează automat la salvare.</p>
                </td>
            </tr>
            <?php CouponBox::common_rows($post, $tags); ?>
        </table>
        <?php
    }

    public static function save(int $post_id, \WP_Post $post): void
    {
        $in = CouponBox::input($post_id);
        if ($in === null) {
            return;
        }

        update_post_meta($post_id, Keys::M_PRODUCT_URL, Sanitize::url($in['product_url'] ?? ''));
        update_post_meta($post_id, Keys::M_DEEPLINK, Sanitize::url($in['deeplink'] ?? ''));
        $img = absint($in['image'] ?? 0);
        update_post_meta($post_id, Keys::M_IMAGE, ($img && wp_attachment_is_image($img)) ? (string) $img : '');
        update_post_meta($post_id, Keys::M_PRICE_OLD, Sanitize::amount($in['price_old'] ?? ''));
        update_post_meta($post_id, Keys::M_PRICE_NEW, Sanitize::amount($in['price_new'] ?? ''));

        CouponBox::save_common($post_id, $in);
    }
}
