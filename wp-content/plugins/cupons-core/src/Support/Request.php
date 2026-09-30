<?php

declare(strict_types=1);

namespace Cupons\Core\Support;

final class Request
{
    /**
     * IP-ul vizitatorului. În spatele Cloudflare, REMOTE_ADDR e IP-ul Cloudflare;
     * activează „Site în spatele Cloudflare” din Setări ca să se folosească CF-Connecting-IP.
     */
    public static function client_ip(): string
    {
        $ip = (string) ($_SERVER['REMOTE_ADDR'] ?? '');
        if (Settings::int('trust_cf_ip') && !empty($_SERVER['HTTP_CF_CONNECTING_IP'])) {
            $cf = trim((string) wp_unslash($_SERVER['HTTP_CF_CONNECTING_IP']));
            if (filter_var($cf, FILTER_VALIDATE_IP)) {
                $ip = $cf;
            }
        }
        return (string) apply_filters('cupons_client_ip', $ip);
    }

    /** Roboții nu se numără la click-uri (statistici oneste). */
    public static function is_bot(): bool
    {
        $ua = (string) ($_SERVER['HTTP_USER_AGENT'] ?? '');
        if ($ua === '') {
            return true;
        }
        return (bool) preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|headless|lighthouse|curl|wget|python|httpclient|monitor/i', $ua);
    }

    /** Cererea vine de pe propriul site (Origin sau Referer cu același host). */
    public static function is_same_origin(): bool
    {
        $home   = wp_parse_url(home_url(), PHP_URL_HOST);
        $origin = (string) ($_SERVER['HTTP_ORIGIN'] ?? '');
        $source = $origin !== '' ? $origin : (string) ($_SERVER['HTTP_REFERER'] ?? '');
        if ($source === '' || !$home) {
            return false;
        }
        return strcasecmp((string) wp_parse_url($source, PHP_URL_HOST), $home) === 0;
    }

    /** Anunță LiteSpeed Cache și orice alt cache că răspunsul nu se păstrează. */
    public static function no_cache(): void
    {
        if (!defined('DONOTCACHEPAGE')) {
            define('DONOTCACHEPAGE', true);
        }
        do_action('litespeed_control_set_nocache', 'cupons: raspuns dinamic');
        if (!headers_sent()) {
            nocache_headers();
            header('X-LiteSpeed-Cache-Control: no-cache');
        }
    }
}
