<?php

declare(strict_types=1);

namespace Cupons\Core\Admin;

use Cupons\Core\Content\Links;
use Cupons\Core\Keys;
use Cupons\Core\Support\Dates;
use Cupons\Core\Support\Sanitize;

/**
 * Caseta „Detalii” pentru cupoane și câmpurile comune (magazin, etichete, date, verificare)
 * folosite și de deal-uri.
 */
final class CouponBox
{
    public const NONCE = 'cupons_offer_nonce';

    public static function register_hooks(): void
    {
        add_action('add_meta_boxes_' . Keys::COUPON, [self::class, 'add']);
        add_action('save_post_' . Keys::COUPON, [self::class, 'save'], 10, 2);
        add_filter('wp_insert_post_data', [self::class, 'validate'], 10, 2);
        add_action('admin_notices', [self::class, 'notices']);
        add_filter('enter_title_here', static fn($t, $post) => $post->post_type === Keys::COUPON ? 'Titlu (ex.: 15% reducere la toate produsele)' : $t, 10, 2);
    }

    public static function add(): void
    {
        add_meta_box('cupons_coupon', 'Detalii cupon', [self::class, 'render'], Keys::COUPON, 'normal', 'high');
    }

    public static function render(\WP_Post $post): void
    {
        $m     = static fn(string $k) => (string) get_post_meta($post->ID, $k, true);
        $store = Links::store_of($post->ID);
        $tags  = wp_get_object_terms($post->ID, Keys::TAG, ['fields' => 'ids']);

        wp_nonce_field('cupons_offer_save', self::NONCE);
        ?>
        <table class="form-table cupons-form">
            <tr>
                <th><label for="cupons-store">Magazin *</label></th>
                <td><?php Fields::select('cupons[store]', Fields::store_options(), $store ? (string) $store->term_id : '', 'cupons-store'); ?></td>
            </tr>
            <tr>
                <th><label for="cupons-code">Cod</label></th>
                <td>
                    <input type="text" id="cupons-code" name="cupons[code]" value="<?php echo esc_attr($m(Keys::M_CODE)); ?>" class="regular-text code">
                    <p class="description">Lasă gol pentru o ofertă fără cod (butonul devine „Vezi oferta”).</p>
                </td>
            </tr>
            <tr>
                <th><label for="cupons-value">Valoare reducere</label></th>
                <td>
                    <input type="text" inputmode="decimal" id="cupons-value" name="cupons[value]" value="<?php echo esc_attr($m(Keys::M_VALUE)); ?>" class="small-text">
                    <?php Fields::select('cupons[value_type]', Keys::VALUE_TYPES, $m(Keys::M_VALUE_TYPE) ?: 'procent', 'cupons-value-type'); ?>
                </td>
            </tr>
            <tr>
                <th><label for="cupons-terms">Condiții</label></th>
                <td><textarea id="cupons-terms" name="cupons[terms]" rows="3" class="large-text"><?php echo esc_textarea($m(Keys::M_TERMS)); ?></textarea></td>
            </tr>
            <tr>
                <th><label for="cupons-link">Link de afiliere (opțional)</label></th>
                <td>
                    <input type="url" id="cupons-link" name="cupons[link]" value="<?php echo esc_attr($m(Keys::M_LINK)); ?>" class="large-text" placeholder="https://">
                    <p class="description">Dacă e gol, se folosește linkul implicit al magazinului.</p>
                </td>
            </tr>
            <?php self::common_rows($post, $tags); ?>
        </table>
        <?php
    }

    /** Rânduri comune cupon/deal. @param int[]|\WP_Error $tags */
    public static function common_rows(\WP_Post $post, array|\WP_Error $tags): void
    {
        $m        = static fn(string $k) => (string) get_post_meta($post->ID, $k, true);
        $verified = $m(Keys::M_VERIFIED);
        $is_new   = $post->post_status === 'auto-draft';
        ?>
        <tr>
            <th>Valabilitate</th>
            <td>
                <label>Start <input type="date" name="cupons[start]" value="<?php echo esc_attr($m(Keys::M_START)); ?>"></label>
                <label>Expiră <input type="date" name="cupons[expires]" value="<?php echo esc_attr($m(Keys::M_EXPIRES)); ?>"></label>
                <p class="description">Expirarea e inclusivă: oferta dispare de pe site după sfârșitul acelei zile. Fără dată = trebuie reverificată periodic.</p>
            </td>
        </tr>
        <tr>
            <th>Opțiuni</th>
            <td>
                <label class="cupons-inline"><input type="checkbox" name="cupons[exclusive]" value="1" <?php checked($m(Keys::M_EXCLUSIVE), '1'); ?>> Exclusiv</label>
                <label class="cupons-inline"><input type="checkbox" name="cupons[top]" value="1" <?php checked($m(Keys::M_TOP), '1'); ?>> Top ofertă</label>
            </td>
        </tr>
        <tr>
            <th>Etichete</th>
            <td><?php Fields::tag_checkboxes('cupons[tags]', is_array($tags) ? array_map('intval', $tags) : []); ?></td>
        </tr>
        <tr>
            <th>Verificare</th>
            <td>
                <p>Ultima verificare: <strong><?php echo $verified ? esc_html(Dates::human($verified)) : '—'; ?></strong></p>
                <label><input type="checkbox" name="cupons[verified_today]" value="1" <?php checked($is_new); ?>> Am verificat oferta azi</label>
                <?php if ($m(Keys::M_EXT_ID)) : ?>
                    <p class="description">ID extern: <code><?php echo esc_html($m(Keys::M_EXT_ID)); ?></code></p>
                <?php endif; ?>
            </td>
        </tr>
        <?php
    }

    public static function save(int $post_id, \WP_Post $post): void
    {
        $in = self::input($post_id);
        if ($in === null) {
            return;
        }

        update_post_meta($post_id, Keys::M_CODE, Sanitize::code($in['code'] ?? ''));
        update_post_meta($post_id, Keys::M_VALUE, Sanitize::amount($in['value'] ?? ''));
        update_post_meta($post_id, Keys::M_VALUE_TYPE, Sanitize::enum($in['value_type'] ?? '', array_keys(Keys::VALUE_TYPES), 'procent'));
        update_post_meta($post_id, Keys::M_TERMS, Sanitize::rich($in['terms'] ?? ''));
        update_post_meta($post_id, Keys::M_LINK, Sanitize::url($in['link'] ?? ''));

        self::save_common($post_id, $in);
    }

    /** Salvează câmpurile comune. Apelat și din DealBox. */
    public static function save_common(int $post_id, array $in): void
    {
        $store = absint($in['store'] ?? 0);
        wp_set_object_terms($post_id, $store && term_exists($store, Keys::STORE) ? [$store] : [], Keys::STORE, false);

        $tags = array_values(array_filter(array_map('absint', (array) ($in['tags'] ?? [])), static fn(int $id) => $id > 0 && term_exists($id, Keys::TAG)));
        wp_set_object_terms($post_id, $tags, Keys::TAG, false);

        update_post_meta($post_id, Keys::M_START, Sanitize::date($in['start'] ?? ''));
        update_post_meta($post_id, Keys::M_EXPIRES, Sanitize::date($in['expires'] ?? ''));
        update_post_meta($post_id, Keys::M_EXCLUSIVE, Sanitize::flag($in['exclusive'] ?? ''));
        update_post_meta($post_id, Keys::M_TOP, Sanitize::flag($in['top'] ?? ''));

        if (!empty($in['verified_today'])) {
            update_post_meta($post_id, Keys::M_VERIFIED, Dates::today());
            delete_post_meta($post_id, Keys::M_RECHECK);
        }
    }

    /** Input validat (nonce + capability) sau null. */
    public static function input(int $post_id): ?array
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return null;
        }
        if (!isset($_POST[self::NONCE]) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST[self::NONCE])), 'cupons_offer_save')) {
            return null;
        }
        if (!current_user_can('edit_post', $post_id)) {
            return null;
        }
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- sanitizat per câmp.
        return isset($_POST['cupons']) && is_array($_POST['cupons']) ? wp_unslash($_POST['cupons']) : [];
    }

    /**
     * O ofertă nu poate fi publicată fără magazin; o ofertă cu expirare în trecut nu poate fi publicată.
     * Rulează înainte de salvare, deci statusul corect ajunge direct în baza de date.
     */
    public static function validate(array $data, array $postarr): array
    {
        if (!in_array($data['post_type'], Keys::offer_types(), true) || $data['post_status'] !== 'publish') {
            return $data;
        }
        if (!isset($_POST[self::NONCE]) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST[self::NONCE])), 'cupons_offer_save')) {
            return $data;
        }
        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput
        $in    = isset($_POST['cupons']) && is_array($_POST['cupons']) ? wp_unslash($_POST['cupons']) : [];
        $error = '';

        if (!absint($in['store'] ?? 0)) {
            $error = 'Alege magazinul înainte de publicare.';
        } elseif (($exp = Sanitize::date($in['expires'] ?? '')) !== '' && $exp < Dates::today()) {
            $error = 'Data de expirare e în trecut: oferta nu poate fi publicată.';
        } elseif ($data['post_type'] === Keys::DEAL && Sanitize::url($in['product_url'] ?? '') === '') {
            $error = 'Completează URL-ul produsului.';
        }

        if ($error !== '') {
            $data['post_status'] = 'draft';
            set_transient('cupons_notice_' . get_current_user_id(), $error, 60);
        }
        return $data;
    }

    public static function notices(): void
    {
        $key = 'cupons_notice_' . get_current_user_id();
        $msg = get_transient($key);
        if ($msg) {
            delete_transient($key);
            printf('<div class="notice notice-error is-dismissible"><p>%s Oferta a fost salvată ca ciornă.</p></div>', esc_html((string) $msg));
        }
    }
}
