<?php

declare(strict_types=1);

namespace Cupons\Core\Freshness;

/**
 * Lock atomic bazat pe wp_options (merge pe orice shared hosting, fără object cache).
 * add_option() eșuează dacă rândul există deja (UNIQUE pe option_name).
 */
final class Lock
{
    public static function acquire(string $name, int $ttl): bool
    {
        global $wpdb;
        $key = 'cupons_lock_' . $name;
        $now = time();

        $inserted = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$wpdb->options} (option_name, option_value, autoload) VALUES (%s, %s, 'off')",
            $key, (string) $now
        ));
        if ($inserted === 1) {
            return true;
        }

        // Lock rămas de la o rulare care a murit: îl preluăm atomic dacă e mai vechi decât TTL.
        $held = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT option_value FROM {$wpdb->options} WHERE option_name = %s", $key
        ));
        if ($held > 0 && ($now - $held) > $ttl) {
            $taken = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->options} SET option_value = %s WHERE option_name = %s AND option_value = %s",
                (string) $now, $key, (string) $held
            ));
            return $taken === 1;
        }

        return false;
    }

    public static function release(string $name): void
    {
        global $wpdb;
        $wpdb->delete($wpdb->options, ['option_name' => 'cupons_lock_' . $name]);
        wp_cache_delete('cupons_lock_' . $name, 'options');
    }
}
