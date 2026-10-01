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

    public static function js(): string
    {
        return asset('js/site.js').'?v='.(int) @filemtime(public_path('js/site.js'));
    }
}
