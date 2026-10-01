<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * CSS-ul e scris inline în <head> (minificat): zero requesturi blocante.
 * JS-ul e un singur fișier mic, încărcat cu defer și versionat după data modificării.
 */
final class Assets
{
    public static function css(): string
    {
        $file = resource_path('css/site.css');

        return Cache::rememberForever('css:'.self::version(), function () use ($file) {
            $css = (string) @file_get_contents($file);
            $css = preg_replace('!/\*.*?\*/!s', '', $css) ?? $css;
            $css = preg_replace('/\s+/', ' ', $css) ?? $css;
            $css = preg_replace('/\s*([{};:,>])\s*/', '$1', $css) ?? $css;

            return str_replace(';}', '}', trim($css));
        });
    }

    /** Versiunea CSS/JS: se schimbă la orice modificare de design (și regenerează cache-ul de pagini). */
    public static function version(): string
    {
        return (int) @filemtime(resource_path('css/site.css')).'-'.(int) @filemtime(public_path('js/site.js'));
    }

    /** Singurul JS inline (clasa „js” pe <html>, înainte de randare: fără layout shift). */
    public const INLINE_JS = "document.documentElement.classList.add('js')";

    /** Politica CSP a site-ului public: doar resurse proprii; CSS-ul și scriptul inline sunt permise prin hash. */
    public static function csp(): string
    {
        $hash = fn (string $s) => "'sha256-".base64_encode(hash('sha256', $s, true))."'";

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' ".$hash(self::INLINE_JS),
            "style-src 'self' ".$hash(self::css()),
            "img-src 'self' data:",
            "connect-src 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
        ]);
    }

    public static function js(): string
    {
        return asset('js/site.js').'?v='.(int) @filemtime(public_path('js/site.js'));
    }
}
