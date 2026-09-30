<?php
/**
 * Tema doar afișează. Toată logica e în pluginul cupons-core.
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('CUPONS_THEME_DIR', get_template_directory());
define('CUPONS_THEME_URI', get_template_directory_uri());

require CUPONS_THEME_DIR . '/inc/icons.php';
require CUPONS_THEME_DIR . '/inc/template.php';

add_action('after_setup_theme', static function (): void {
    add_theme_support('title-tag');
    add_theme_support('html5', ['search-form', 'gallery', 'caption', 'style', 'script', 'navigation-widgets']);
    add_theme_support('custom-logo', ['height' => 40, 'width' => 200, 'flex-width' => true, 'flex-height' => true]);
    remove_theme_support('core-block-patterns');
    register_nav_menus([
        'primary' => 'Meniu principal (maxim 5 elemente)',
    ]);
});

/** Un singur CSS, un singur JS (defer). Versiunea = data modificării fișierului (cache-busting). */
add_action('wp_enqueue_scripts', static function (): void {
    $css = CUPONS_THEME_DIR . '/style.css';
    $js  = CUPONS_THEME_DIR . '/assets/app.js';
    wp_enqueue_style('cupons', get_stylesheet_uri(), [], (string) filemtime($css));
    wp_enqueue_script('cupons', CUPONS_THEME_URI . '/assets/app.js', [], (string) filemtime($js), [
        'strategy'  => 'defer',
        'in_footer' => false,
    ]);
});

/** Clasa „js” înainte de randare: blocurile care au nevoie de JS apar fără layout shift. */
add_action('wp_head', static function (): void {
    echo "<script>document.documentElement.classList.add('js')</script>\n";
}, 0);

/** Favicon SVG inline (zero requesturi) dacă nu e setată o iconiță de site. */
add_action('wp_head', static function (): void {
    if (has_site_icon()) {
        return;
    }
    $svg = "<svg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 32 32'><rect width='32' height='32' rx='7' fill='%230b6e4f'/><path d='M9 16h14M16 9v14' stroke='white' stroke-width='3' stroke-linecap='round' transform='rotate(45 16 16)'/></svg>";
    echo '<link rel="icon" href="data:image/svg+xml,' . esc_attr($svg) . "\">\n";
}, 2);

add_action('wp_body_open', 'ct_icon_sprite', 1);

/** Fără pluginul cupons-core tema nu are ce afișa: avertizăm adminul. */
add_action('admin_notices', static function (): void {
    if (!ct_ready()) {
        echo '<div class="notice notice-error"><p>Tema <strong>Cupons</strong> are nevoie de pluginul <strong>Cupons Core</strong> activ.</p></div>';
    }
});

/** Paginile statice: id-uri pe H2 pentru cuprins. */
add_filter('the_content', 'ct_heading_ids', 20);

/** Fără clase de blocuri inutile pe body. */
add_filter('body_class', static function (array $classes): array {
    return array_values(array_filter($classes, static fn($c) => !str_starts_with($c, 'wp-theme-') && !str_starts_with($c, 'wp-custom-logo')));
});
