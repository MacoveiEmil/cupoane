<?php

declare(strict_types=1);

namespace Cupons\Core\Admin;

use Cupons\Core\Import\Importer;
use Cupons\Core\Import\Programs;
use Cupons\Core\Keys;

/**
 * Pagina „Import”: „Importă acum” per rețea (rulează pe pași, cu progres), plus lista „Neasociate”.
 * Cu JS, pașii rulează automat prin admin-ajax; fără JS, butonul „Continuă” rulează câte un pas.
 */
final class ImportPage
{
    public const SLUG = 'cupons-import';

    public static function register_hooks(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 15);
        add_action('admin_post_cupons_import_start', [self::class, 'handle_start']);
        add_action('admin_post_cupons_import_step', [self::class, 'handle_step']);
        add_action('admin_post_cupons_import_assoc', [self::class, 'handle_assoc']);
        add_action('wp_ajax_cupons_import_step', [self::class, 'ajax_step']);
    }

    public static function menu(): void
    {
        add_submenu_page('edit.php?post_type=' . Keys::COUPON, 'Import din rețele', 'Import', 'manage_options', self::SLUG, [self::class, 'render']);
    }

    private static function url(array $args = []): string
    {
        return add_query_arg(['post_type' => Keys::COUPON, 'page' => self::SLUG] + $args, admin_url('edit.php'));
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Nu ai acces.');
        }
        $run = sanitize_key($_GET['run'] ?? ''); // phpcs:ignore WordPress.Security.NonceVerification
        ?>
        <div class="wrap cupons-import">
            <h1>Import din rețele</h1>
            <p>Ofertele noi intră ca <strong>ciornă</strong>. Le verifici și le publici din <a href="<?php echo esc_url(admin_url('edit.php?post_type=' . Keys::COUPON . '&post_status=draft')); ?>">Cupoane → Ciorne</a>.
               Ofertele deja importate își actualizează doar codul, datele și linkul. Cele șterse sau expirate nu sunt readuse.</p>

            <div class="cupons-cards">
            <?php foreach (Importer::connectors() as $id => $c) :
                $s       = Importer::state($id);
                $running = Importer::is_running($s);
                ?>
                <div class="cupons-card" data-import="<?php echo esc_attr($id); ?>" <?php echo ($run === $id && $running) ? 'data-autorun' : ''; ?>>
                    <h2><?php echo esc_html($c->label()); ?></h2>
                    <?php if (!$c->is_configured()) : ?>
                        <p><span class="cupons-badge">neconfigurat</span> Completează datele de acces în <a href="<?php echo esc_url(add_query_arg(['post_type' => Keys::COUPON, 'page' => Settings::SLUG], admin_url('edit.php'))); ?>">Setări</a>.</p>
                    <?php else : ?>
                        <p class="cupons-status" data-status><?php echo esc_html(self::summary($s)); ?></p>
                        <?php if ($s['error']) : ?><div class="notice notice-error inline"><p data-error><?php echo esc_html($s['error']); ?></p></div><?php endif; ?>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cupons-inline">
                            <input type="hidden" name="action" value="cupons_import_start">
                            <input type="hidden" name="network" value="<?php echo esc_attr($id); ?>">
                            <?php wp_nonce_field('cupons_import_' . $id); ?>
                            <button class="button button-primary" <?php disabled($running && !$s['error']); ?>>Importă acum</button>
                        </form>
                        <?php if ($running) : ?>
                            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" class="cupons-inline">
                                <input type="hidden" name="action" value="cupons_import_step">
                                <input type="hidden" name="network" value="<?php echo esc_attr($id); ?>">
                                <?php wp_nonce_field('cupons_import_' . $id); ?>
                                <button class="button" data-continue>Continuă</button>
                            </form>
                        <?php endif; ?>
                        <span data-nonce="<?php echo esc_attr(wp_create_nonce('cupons_import_' . $id)); ?>"></span>
                    <?php endif; ?>
                </div>
            <?php endforeach; ?>
            </div>

            <?php self::render_unmapped(); ?>

            <h2>Din linia de comandă</h2>
            <p><code>wp cupons import</code> (toate rețelele) sau <code>wp cupons import --network=2performant</code>.</p>
        </div>
        <?php
    }

    public static function summary(array $s): string
    {
        $st = $s['stats'];
        $phases = ['idle' => 'Nu a rulat încă.', 'programs' => 'În curs: programe…', 'offers' => 'În curs: oferte…', 'done' => 'Terminat'];
        $text = $phases[$s['phase']] ?? $s['phase'];
        if ($s['phase'] === 'done' && $s['finished']) {
            $text .= ' la ' . wp_date('j M Y, H:i', (int) $s['finished']) . '.';
        }
        if ($s['phase'] !== 'idle') {
            $text .= sprintf(
                ' Programe: %d (%d asociate). Oferte: %d noi, %d actualizate, %d neschimbate, %d la magazine neasociate, %d expirate, %d ignorate.',
                $st['programs'], $st['mapped'], $st['created'], $st['updated'], $st['unchanged'], $st['unmapped'], $st['expired'], $st['skipped']
            );
        }
        return $text;
    }

    private static function render_unmapped(): void
    {
        $rows = Programs::unmapped();
        ?>
        <h2 id="neasociate">Neasociate <span class="count">(<?php echo count($rows); ?>)</span></h2>
        <p>Programe din rețele care nu sunt legate de niciun magazin. Ofertele lor nu se importă până nu le asociezi.
           Asocierea automată se face când domeniul programului coincide cu URL-ul unui magazin.</p>
        <?php if (!$rows) : ?>
            <p><em>Nimic de asociat.</em></p>
            <?php return; ?>
        <?php endif; ?>
        <table class="widefat striped">
            <thead><tr><th>Rețea</th><th>Program</th><th>Site</th><th>Asociază cu magazinul</th></tr></thead>
            <tbody>
            <?php $stores = Fields::store_options(); ?>
            <?php foreach ($rows as $r) : ?>
                <tr>
                    <td><?php echo esc_html(Keys::NETWORKS[$r->network] ?? $r->network); ?></td>
                    <td><strong><?php echo esc_html($r->name); ?></strong><br><code><?php echo esc_html($r->program_id); ?></code></td>
                    <td><?php echo $r->site_url ? '<a href="' . esc_url($r->site_url) . '" target="_blank" rel="noopener noreferrer">' . esc_html($r->site_url) . '</a>' : '—'; ?></td>
                    <td>
                        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                            <input type="hidden" name="action" value="cupons_import_assoc">
                            <input type="hidden" name="network" value="<?php echo esc_attr($r->network); ?>">
                            <input type="hidden" name="program_id" value="<?php echo esc_attr($r->program_id); ?>">
                            <?php wp_nonce_field('cupons_assoc_' . $r->network . '_' . $r->program_id); ?>
                            <select name="term_id">
                                <option value="new">+ Creează magazin nou „<?php echo esc_html($r->name); ?>”</option>
                                <?php foreach ($stores as $tid => $name) : if ($tid === '') { continue; } ?>
                                    <option value="<?php echo esc_attr((string) $tid); ?>"><?php echo esc_html($name); ?></option>
                                <?php endforeach; ?>
                            </select>
                            <button class="button">Asociază</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        <?php
    }

    private static function network_from_request(): string
    {
        $network = sanitize_key($_POST['network'] ?? '');
        if (!Importer::connector($network)) {
            wp_die('Rețea necunoscută.');
        }
        check_admin_referer('cupons_import_' . $network);
        if (!current_user_can('manage_options')) {
            wp_die('Nu ai acces.');
        }
        return $network;
    }

    public static function handle_start(): void
    {
        $network = self::network_from_request();
        Importer::start($network);
        wp_safe_redirect(self::url(['run' => $network]));
        exit;
    }

    public static function handle_step(): void
    {
        $network = self::network_from_request();
        Importer::step($network);
        wp_safe_redirect(self::url(['run' => $network]));
        exit;
    }

    public static function ajax_step(): void
    {
        $network = sanitize_key($_POST['network'] ?? '');
        check_ajax_referer('cupons_import_' . $network);
        if (!current_user_can('manage_options') || !Importer::connector($network)) {
            wp_send_json_error(['message' => 'Nu ai acces.'], 403);
        }
        $s = Importer::step($network);
        wp_send_json_success([
            'running' => Importer::is_running($s) && $s['error'] === '',
            'summary' => self::summary($s),
            'error'   => $s['error'],
        ]);
    }

    public static function handle_assoc(): void
    {
        $network = sanitize_key($_POST['network'] ?? '');
        $program = sanitize_text_field(wp_unslash($_POST['program_id'] ?? ''));
        check_admin_referer('cupons_assoc_' . $network . '_' . $program);
        if (!current_user_can('manage_options') || !Importer::connector($network)) {
            wp_die('Nu ai acces.');
        }

        $choice = sanitize_text_field(wp_unslash($_POST['term_id'] ?? ''));
        if ($choice === 'new') {
            global $wpdb;
            $row = $wpdb->get_row($wpdb->prepare(
                'SELECT name, site_url FROM ' . \Cupons\Core\Support\Tables::programs() . ' WHERE network = %s AND program_id = %s',
                $network, $program
            ));
            $term = $row ? wp_insert_term($row->name, Keys::STORE) : new \WP_Error('x', 'Program inexistent.');
            if (is_wp_error($term)) {
                wp_die(esc_html($term->get_error_message()));
            }
            $term_id = (int) $term['term_id'];
            update_term_meta($term_id, Keys::T_URL, \Cupons\Core\Support\Sanitize::url((string) $row->site_url));
        } else {
            $term_id = absint($choice);
        }

        Programs::associate($network, $program, $term_id);
        wp_safe_redirect(self::url() . '#neasociate');
        exit;
    }
}
