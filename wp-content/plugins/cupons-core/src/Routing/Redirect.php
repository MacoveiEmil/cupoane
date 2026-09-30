<?php

declare(strict_types=1);

namespace Cupons\Core\Routing;

use Cupons\Core\Content\Links;
use Cupons\Core\Keys;
use Cupons\Core\Query\Offers;
use Cupons\Core\Stats\Clicks;
use Cupons\Core\Support\Request;

/**
 * /go/{id}/   → cupon sau deal (ID numeric)
 * /go/{slug}/ → magazin (butonul „Mergi la magazin”)
 *
 * Redirect 302 la linkul de afiliere, click numărat, noindex, fără cache.
 * Rulează pe parse_request, înainte de query-ul principal: nu se încarcă nimic inutil.
 */
final class Redirect
{
    public const QV = 'cupons_go';

    public static function register_hooks(): void
    {
        add_action('init', [self::class, 'add_rules']);
        add_filter('query_vars', static fn(array $vars) => [...$vars, self::QV]);
        add_action('parse_request', [self::class, 'maybe_redirect'], 1);
        add_filter('robots_txt', [self::class, 'robots'], 10, 2);
    }

    public static function add_rules(): void
    {
        add_rewrite_rule('^go/([^/]+)/?$', 'index.php?' . self::QV . '=$matches[1]', 'top');
    }

    public static function robots(string $output, mixed $public): string
    {
        if (!$public) {
            return $output;
        }
        $path = (string) wp_parse_url(home_url('/go/'), PHP_URL_PATH);
        $rule = "Disallow: {$path}";
        if (str_contains($output, $rule)) {
            return $output;
        }
        // În grupul „User-agent: *”, nu după directiva Sitemap.
        if (preg_match('/^User-agent:\s*\*\s*$/mi', $output)) {
            return (string) preg_replace('/^(User-agent:\s*\*\s*)$/mi', "$1\n{$rule}", $output, 1);
        }
        return "User-agent: *\n{$rule}\n\n" . ltrim($output);
    }

    public static function maybe_redirect(\WP $wp): void
    {
        if (!isset($wp->query_vars[self::QV])) {
            return;
        }
        $key = sanitize_title((string) $wp->query_vars[self::QV]);

        Request::no_cache();
        if (!headers_sent()) {
            header('X-Robots-Tag: noindex, nofollow', true);
        }

        [$target, $type, $object_id, $fallback] = self::resolve($key);

        $countable = in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'POST'], true) && !Request::is_bot();
        if ($target !== '' && $countable) {
            Clicks::increment($type, $object_id);
        }

        wp_redirect($target !== '' ? $target : $fallback, 302, false);
        exit;
    }

    /**
     * @return array{0:string,1:int,2:int,3:string} [destinație, tip click, ID obiect, fallback]
     */
    private static function resolve(string $key): array
    {
        $home = home_url('/');

        if (ctype_digit($key)) {
            $post = get_post((int) $key);
            if ($post instanceof \WP_Post && in_array($post->post_type, Keys::offer_types(), true)) {
                $store    = Links::store_of($post->ID);
                $fallback = $store ? (string) get_term_link($store) : $home;

                // Oferta expirată sau nepublicată: nu trimitem vizitatorul la ceva ce nu mai e valabil.
                if ($post->post_status !== 'publish' || !Offers::is_active($post->ID)) {
                    return ['', 0, 0, $fallback];
                }

                $type = $post->post_type === Keys::DEAL ? Keys::CLICK_DEAL : Keys::CLICK_COUPON;
                return [self::safe(Links::target_for_post($post)), $type, $post->ID, $fallback];
            }
        }

        $store = get_term_by('slug', $key, Keys::STORE);
        if ($store instanceof \WP_Term) {
            return [self::safe(Links::target_for_store($store)), Keys::CLICK_STORE, $store->term_id, (string) get_term_link($store)];
        }

        return ['', 0, 0, $home];
    }

    private static function safe(string $url): string
    {
        return \Cupons\Core\Support\Sanitize::is_http_url($url) ? $url : '';
    }
}
