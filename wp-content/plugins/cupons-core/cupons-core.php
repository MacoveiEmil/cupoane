<?php
/**
 * Plugin Name:       Cupons Core
 * Description:       Toată logica site-ului de cupoane: magazine, cupoane, deal-uri, redirect de afiliere, voturi, curățare automată și import din rețele.
 * Version:           0.1.0
 * Requires at least: 6.5
 * Requires PHP:      8.2
 * Text Domain:       cupons-core
 * License:           GPL-2.0-or-later
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

const CUPONS_CORE_VERSION = '0.1.0';
const CUPONS_CORE_FILE    = __FILE__;
define('CUPONS_CORE_DIR', plugin_dir_path(__FILE__));
define('CUPONS_CORE_URL', plugin_dir_url(__FILE__));

spl_autoload_register(static function (string $class): void {
    $prefix = 'Cupons\\Core\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }
    $file = CUPONS_CORE_DIR . 'src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';
    if (is_readable($file)) {
        require $file;
    }
});

register_activation_hook(__FILE__, [Cupons\Core\Install\Installer::class, 'activate']);
register_deactivation_hook(__FILE__, [Cupons\Core\Install\Installer::class, 'deactivate']);

add_action('plugins_loaded', [Cupons\Core\Plugin::class, 'boot']);
