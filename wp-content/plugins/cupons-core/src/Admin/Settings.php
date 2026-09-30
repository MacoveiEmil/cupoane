<?php

declare(strict_types=1);

namespace Cupons\Core\Admin;

use Cupons\Core\Freshness\Cleanup;
use Cupons\Core\Freshness\Scheduler;
use Cupons\Core\Import\Importer;
use Cupons\Core\Keys;
use Cupons\Core\Support\Settings as S;

final class Settings
{
    public const SLUG      = 'cupons-settings';
    public const RUN_NOW   = 'cupons_cleanup_now';

    public static function register_hooks(): void
    {
        add_action('admin_menu', [self::class, 'menu'], 20);
        add_action('admin_init', [self::class, 'register']);
        add_action('admin_post_' . self::RUN_NOW, [self::class, 'run_now']);
    }

    public static function menu(): void
    {
        add_submenu_page('edit.php?post_type=' . Keys::COUPON, 'Setări', 'Setări', 'manage_options', self::SLUG, [self::class, 'render']);
    }

    public static function register(): void
    {
        register_setting('cupons_settings_group', S::OPTION, [
            'type'              => 'array',
            'sanitize_callback' => [self::class, 'sanitize'],
            'default'           => S::defaults(),
        ]);
    }

    public static function sanitize(mixed $in): array
    {
        $in  = is_array($in) ? $in : [];
        $out = S::all(); // păstrăm cheile care nu sunt în formular (ex.: chei API din altă secțiune)
        $out['recheck_days'] = max(7, min(365, absint($in['recheck_days'] ?? 30)));
        $out['vote_min']     = max(1, min(100, absint($in['vote_min'] ?? 5)));
        $out['trust_cf_ip']  = empty($in['trust_cf_ip']) ? 0 : 1;

        // Credențialele rețelelor: un câmp secret lăsat gol își păstrează valoarea salvată.
        foreach (Importer::connectors() as $connector) {
            foreach ($connector->settings_fields() as $key => $field) {
                $value = trim(sanitize_text_field((string) ($in[$key] ?? '')));
                if ($field['secret'] && $value === '') {
                    continue;
                }
                $out[$key] = $value;
            }
            if (!empty($in['clear_' . $connector->id()])) {
                foreach (array_keys($connector->settings_fields()) as $key) {
                    $out[$key] = '';
                }
            }
        }
        delete_transient('cupons_2p_auth'); // datele de acces s-ar fi putut schimba
        return $out;
    }

    public static function render(): void
    {
        if (!current_user_can('manage_options')) {
            wp_die('Nu ai acces.');
        }
        $s    = S::all();
        $last = get_option(Cleanup::LOG_OPTION);
        $next = wp_next_scheduled(Scheduler::HOOK);
        ?>
        <div class="wrap">
            <h1>Setări cupoane</h1>
            <form method="post" action="options.php">
                <?php settings_fields('cupons_settings_group'); ?>
                <table class="form-table">
                    <tr>
                        <th><label for="cupons-recheck">Reverificare oferte fără expirare</label></th>
                        <td>
                            <input type="number" id="cupons-recheck" min="7" max="365" name="<?php echo esc_attr(S::OPTION); ?>[recheck_days]" value="<?php echo (int) $s['recheck_days']; ?>" class="small-text"> zile
                            <p class="description">După acest interval fără verificare, oferta trece în ciornă și apare la „De reverificat”.</p>
                        </td>
                    </tr>
                    <tr>
                        <th><label for="cupons-votemin">Prag rată de succes</label></th>
                        <td>
                            <input type="number" id="cupons-votemin" min="1" max="100" name="<?php echo esc_attr(S::OPTION); ?>[vote_min]" value="<?php echo (int) $s['vote_min']; ?>" class="small-text"> voturi
                            <p class="description">Rata „A funcționat” se afișează doar de la acest număr de voturi reale.</p>
                        </td>
                    </tr>
                    <tr>
                        <th>Cloudflare</th>
                        <td>
                            <label><input type="checkbox" name="<?php echo esc_attr(S::OPTION); ?>[trust_cf_ip]" value="1" <?php checked((int) $s['trust_cf_ip'], 1); ?>> Site-ul e în spatele Cloudflare (folosește CF-Connecting-IP pentru limitarea voturilor)</label>
                            <p class="description">Activează doar dacă tot traficul trece prin Cloudflare; altfel IP-ul poate fi falsificat.</p>
                        </td>
                    </tr>
                </table>

                <h2>Rețele de afiliere</h2>
                <p class="description">Datele de acces se păstrează doar în baza de date a site-ului (nu în cod). Câmpurile secrete nu se mai afișează după salvare; lasă-le goale ca să rămână neschimbate.</p>
                <?php foreach (Importer::connectors() as $connector) : ?>
                    <h3><?php echo esc_html($connector->label()); ?> <?php echo $connector->is_configured() ? '<span class="cupons-badge cupons-badge--ok">configurat</span>' : ''; ?></h3>
                    <table class="form-table">
                        <?php foreach ($connector->settings_fields() as $key => $field) :
                            $saved = (string) ($s[$key] ?? ''); ?>
                            <tr>
                                <th><label for="cupons-<?php echo esc_attr($key); ?>"><?php echo esc_html($field['label']); ?></label></th>
                                <td>
                                    <input type="<?php echo $field['secret'] ? 'password' : 'text'; ?>" id="cupons-<?php echo esc_attr($key); ?>" class="regular-text" autocomplete="off"
                                           name="<?php echo esc_attr(S::OPTION . '[' . $key . ']'); ?>"
                                           value="<?php echo $field['secret'] ? '' : esc_attr($saved); ?>"
                                           placeholder="<?php echo ($field['secret'] && $saved !== '') ? '•••••••• (salvat)' : ''; ?>">
                                    <?php if ($field['help']) : ?><p class="description"><?php echo esc_html($field['help']); ?></p><?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                        <?php if ($connector->is_configured()) : ?>
                            <tr><th></th><td><label><input type="checkbox" name="<?php echo esc_attr(S::OPTION . '[clear_' . $connector->id() . ']'); ?>" value="1"> Șterge datele de acces <?php echo esc_html($connector->label()); ?></label></td></tr>
                        <?php endif; ?>
                    </table>
                <?php endforeach; ?>

                <?php submit_button('Salvează'); ?>
            </form>

            <hr>
            <h2>Curățare zilnică</h2>
            <p>
                Următoarea rulare: <strong><?php echo $next ? esc_html(wp_date('j M Y, H:i', $next)) : 'neprogramată'; ?></strong><br>
                <?php if (is_array($last)) : ?>
                    Ultima rulare: <strong><?php echo esc_html(wp_date('j M Y, H:i', (int) $last['time'])); ?></strong> —
                    <?php printf('%d expirate mutate în coș, %d trecute la reverificare.', (int) $last['expired'], (int) $last['stale']); ?>
                <?php else : ?>
                    Nu a rulat încă.
                <?php endif; ?>
            </p>
            <?php if (defined('DISABLE_WP_CRON') && DISABLE_WP_CRON) : ?>
                <p class="description">WP-Cron e declanșat de cron-ul de sistem (DISABLE_WP_CRON activ).</p>
            <?php else : ?>
                <p class="description">Recomandat pe producție: cron de sistem + DISABLE_WP_CRON (vezi README).</p>
            <?php endif; ?>
            <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>">
                <input type="hidden" name="action" value="<?php echo esc_attr(self::RUN_NOW); ?>">
                <?php wp_nonce_field(self::RUN_NOW); ?>
                <?php submit_button('Rulează curățarea acum', 'secondary', 'submit', false); ?>
            </form>
        </div>
        <?php
    }

    public static function run_now(): void
    {
        check_admin_referer(self::RUN_NOW);
        if (!current_user_can('manage_options')) {
            wp_die('Nu ai acces.');
        }
        Cleanup::run();
        wp_safe_redirect(add_query_arg(['post_type' => Keys::COUPON, 'page' => self::SLUG], admin_url('edit.php')));
        exit;
    }
}
