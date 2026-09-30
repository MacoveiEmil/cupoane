<?php

declare(strict_types=1);

namespace Cupons\Core\Admin;

use Cupons\Core\Import\ImportException;
use Cupons\Core\Import\Importer;
use Cupons\Core\Keys;
use Cupons\Core\Support\Sanitize;

/**
 * „Adaugă deal rapid”: lipești URL-ul produsului, alegi magazinul; deeplink-ul se generează
 * după formatul rețelei magazinului. Se creează o ciornă; restul câmpurilor se completează manual.
 */
final class QuickDeal
{
    public const SLUG = 'cupons-quick-deal';

    public static function register_hooks(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 14);
        add_action('admin_post_cupons_quick_deal', [self::class, 'handle']);
        add_action('admin_notices', [self::class, 'notice']);
    }

    public static function menu(): void
    {
        add_submenu_page('edit.php?post_type=' . Keys::COUPON, 'Adaugă deal rapid', 'Adaugă deal rapid', 'edit_posts', self::SLUG, [self::class, 'render']);
    }

    public static function render(): void
    {
        if (!current_user_can('edit_posts')) {
            wp_die('Nu ai acces.');
        }
        ?>
        <div class="wrap">
            <h1>Adaugă deal rapid</h1>
            <p>Lipește linkul produsului și alege magazinul. Linkul de afiliere se generează automat după rețeaua magazinului (2Performant sau Profitshare), apoi completezi prețurile și imaginea.</p>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="cupons_quick_deal">
                <?php wp_nonce_field('cupons_quick_deal'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="qd-url">URL produs</label></th>
                        <td><input type="url" id="qd-url" name="url" class="large-text" required placeholder="https://"></td>
                    </tr>
                    <tr>
                        <th><label for="qd-store">Magazin</label></th>
                        <td><?php Fields::select('store', Fields::store_options(), '', 'qd-store'); ?></td>
                    </tr>
                </table>
                <?php submit_button('Creează deal'); ?>
            </form>
        </div>
        <?php
    }

    public static function handle(): void
    {
        check_admin_referer('cupons_quick_deal');
        if (!current_user_can('edit_posts')) {
            wp_die('Nu ai acces.');
        }

        $url   = Sanitize::url(wp_unslash($_POST['url'] ?? ''));
        $store = get_term(absint($_POST['store'] ?? 0), Keys::STORE);
        if ($url === '' || !$store instanceof \WP_Term) {
            wp_die('Completează un URL valid și alege magazinul. <a href="javascript:history.back()">Înapoi</a>');
        }

        [$deeplink, $message] = self::deeplink($url, $store);

        $id = wp_insert_post([
            'post_type'   => Keys::DEAL,
            'post_status' => 'draft',
            'post_title'  => self::title_from_url($url),
            'meta_input'  => [Keys::M_PRODUCT_URL => $url, Keys::M_DEEPLINK => $deeplink],
        ], true);
        if (is_wp_error($id)) {
            wp_die(esc_html($id->get_error_message()));
        }
        wp_set_object_terms($id, [$store->term_id], Keys::STORE);

        set_transient('cupons_qd_' . get_current_user_id(), $message, 120);
        wp_safe_redirect(admin_url('post.php?post=' . $id . '&action=edit'));
        exit;
    }

    /** @return array{0:string,1:array{type:string,text:string}} */
    private static function deeplink(string $url, \WP_Term $store): array
    {
        $network   = (string) get_term_meta($store->term_id, Keys::T_NETWORK, true);
        $program   = (string) get_term_meta($store->term_id, Keys::T_PROGRAM, true);
        $connector = Importer::connector($network);

        if (!$connector) {
            return ['', ['type' => 'warning', 'text' => 'Magazinul nu e asociat unei rețele: deal-ul va trimite la URL-ul produsului, fără comision.']];
        }
        if ($program === '') {
            return ['', ['type' => 'warning', 'text' => 'Magazinul nu are ID de program în rețea: completează-l în fișa magazinului sau asociază-l din Import.']];
        }
        try {
            return [$connector->deeplink($url, $program), ['type' => 'success', 'text' => 'Deal creat ca ciornă, cu deeplink ' . $connector->label() . '. Completează titlul, prețurile și imaginea.']];
        } catch (ImportException $e) {
            return ['', ['type' => 'error', 'text' => 'Deeplink-ul nu a putut fi generat: ' . $e->getMessage()]];
        }
    }

    /** Titlu provizoriu din ultimul segment al URL-ului (de editat). */
    private static function title_from_url(string $url): string
    {
        $path = trim((string) wp_parse_url($url, PHP_URL_PATH), '/');
        $last = $path !== '' ? basename($path) : (string) wp_parse_url($url, PHP_URL_HOST);
        $last = preg_replace('/\.(html?|php)$/i', '', $last) ?? $last;
        $text = ucfirst(trim((string) preg_replace('/[-_]+/', ' ', rawurldecode($last))));
        return mb_substr($text !== '' ? $text : 'Deal nou', 0, 120);
    }

    public static function notice(): void
    {
        $key = 'cupons_qd_' . get_current_user_id();
        $msg = get_transient($key);
        if (is_array($msg)) {
            delete_transient($key);
            printf('<div class="notice notice-%s is-dismissible"><p>%s</p></div>', esc_attr($msg['type']), esc_html($msg['text']));
        }
    }
}
