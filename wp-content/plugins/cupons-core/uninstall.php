<?php
/**
 * Dezinstalare: șterge setările și programările. Conținutul (magazine, cupoane)
 * și tabelele de statistici rămân, ca să nu se piardă date din greșeală.
 * Pentru ștergere completă, definește în wp-config.php: define('CUPONS_UNINSTALL_PURGE', true);
 */

declare(strict_types=1);

defined('WP_UNINSTALL_PLUGIN') || exit;

global $wpdb;

wp_clear_scheduled_hook('cupons_daily');

foreach (['cupons_settings', 'cupons_db_version', 'cupons_flush_rewrites', 'cupons_last_cleanup', 'cupons_lock_cleanup'] as $option) {
    delete_option($option);
}

if (defined('CUPONS_UNINSTALL_PURGE') && CUPONS_UNINSTALL_PURGE) {
    delete_option('cupons_ip_salt');
    foreach (['cupons_clicks', 'cupons_votes', 'cupons_vote_guard', 'cupons_programs'] as $table) {
        $wpdb->query("DROP TABLE IF EXISTS {$wpdb->prefix}{$table}"); // phpcs:ignore WordPress.DB.PreparedSQL
    }
}
