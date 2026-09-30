<?php

declare(strict_types=1);

namespace Cupons\Core\Front;

use Cupons\Core\Keys;

/**
 * Frontend minimal: scoate tot ce WordPress încarcă implicit și nu folosim
 * (emoji, oEmbed, jQuery, dashicons, CSS de blocuri, XML-RPC, linkuri inutile din <head>, feed-uri).
 */
final class HeadCleanup
{
    public static function register_hooks(): void
    {
        // Emoji
        remove_action('wp_head', 'print_emoji_detection_script', 7);
        remove_action('wp_print_styles', 'print_emoji_styles');
        remove_action('admin_print_scripts', 'print_emoji_detection_script');
        remove_action('admin_print_styles', 'print_emoji_styles');
        remove_filter('the_content_feed', 'wp_staticize_emoji');
        remove_filter('comment_text_rss', 'wp_staticize_emoji');
        remove_filter('wp_mail', 'wp_staticize_emoji_for_email');
        add_filter('emoji_svg_url', '__return_false');
        add_filter('wp_resource_hints', [self::class, 'resource_hints'], 10, 2);

        // oEmbed
        remove_action('wp_head', 'wp_oembed_add_discovery_links');
        remove_action('wp_head', 'wp_oembed_add_host_js');
        remove_action('rest_api_init', 'wp_oembed_register_route');
        remove_filter('oembed_dataparse', 'wp_filter_oembed_result', 10);
        add_filter('embed_oembed_discover', '__return_false');

        // Linkuri inutile din <head>
        remove_action('wp_head', 'rsd_link');
        remove_action('wp_head', 'wlwmanifest_link');
        remove_action('wp_head', 'wp_generator');
        remove_action('wp_head', 'wp_shortlink_wp_head');
        remove_action('wp_head', 'rest_output_link_wp_head');
        remove_action('wp_head', 'adjacent_posts_rel_link_wp_head');
        remove_action('wp_head', 'feed_links', 2);
        remove_action('wp_head', 'feed_links_extra', 3);
        remove_action('template_redirect', 'rest_output_link_header', 11);
        remove_action('template_redirect', 'wp_shortlink_header', 11);

        // XML-RPC
        add_filter('xmlrpc_enabled', '__return_false');
        add_filter('xmlrpc_methods', '__return_empty_array');
        add_filter('wp_headers', static function (array $h) {
            unset($h['X-Pingback']);
            return $h;
        });
        add_filter('pings_open', '__return_false');

        // Feed-uri: doar feed-ul principal de articole rămâne (dacă e folosit); taxonomiile noastre nu au feed.
        add_action('template_redirect', [self::class, 'block_feeds'], 1);

        // Stilurile globale / de blocuri (theme.json) nu sunt folosite de temă.
        remove_action('wp_enqueue_scripts', 'wp_enqueue_global_styles');
        remove_action('wp_footer', 'wp_enqueue_global_styles', 1);
        remove_action('wp_enqueue_scripts', 'wp_enqueue_classic_theme_styles');
        remove_action('wp_body_open', 'wp_global_styles_render_svg_filters');
        remove_action('wp_footer', 'wp_enqueue_stored_styles', 1);
        add_filter('should_load_separate_core_block_assets', '__return_true');

        add_action('wp_enqueue_scripts', [self::class, 'dequeue'], 100);
        add_action('wp_footer', [self::class, 'dequeue_footer'], 1);

        // Fără comentarii pe frontend.
        add_filter('comments_open', '__return_false', 20);
        add_filter('comments_array', '__return_empty_array', 10);
    }

    public static function resource_hints(array $urls, string $relation): array
    {
        if ($relation === 'dns-prefetch') {
            $urls = array_filter($urls, static fn($u) => !is_string($u) || !str_contains($u, 's.w.org'));
        }
        return $urls;
    }

    public static function dequeue(): void
    {
        if (is_admin()) {
            return;
        }
        foreach (['wp-block-library', 'wp-block-library-theme', 'classic-theme-styles', 'global-styles', 'core-block-supports', 'wc-blocks-style'] as $h) {
            wp_dequeue_style($h);
            wp_deregister_style($h);
        }
        if (!is_user_logged_in()) {
            wp_dequeue_style('dashicons');
            wp_deregister_style('dashicons');
        }
        if (!is_admin_bar_showing()) {
            wp_dequeue_script('jquery');
            wp_deregister_script('jquery');
        }
        wp_dequeue_script('wp-embed');
    }

    public static function dequeue_footer(): void
    {
        wp_dequeue_style('core-block-supports');
        wp_dequeue_style('core-block-supports-duotone');
    }

    public static function block_feeds(): void
    {
        if (!is_feed()) {
            return;
        }
        if (is_tax([Keys::STORE, Keys::CATEGORY, Keys::TAG]) || is_comment_feed() || is_post_type_archive(Keys::offer_types())) {
            $term = get_queried_object();
            $to   = $term instanceof \WP_Term ? get_term_link($term) : home_url('/');
            wp_safe_redirect(is_string($to) ? $to : home_url('/'), 301);
            exit;
        }
    }
}
