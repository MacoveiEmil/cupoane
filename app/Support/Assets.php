<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * CSS-ul site-ului e scris inline în <head> (minificat, ~6 KB gzip): zero requesturi blocante, LCP rapid.
 * JS-ul e un singur fișier mic, încărcat cu defer și versionat după data modificării.
 */
final class Assets
{
    public static function css(): string
    {
        $file = resource_path('css/site.css');
        $mtime = (int) @filemtime($file);

        return Cache::rememberForever('css:'.$mtime, function () use ($file) {
            $css = (string) file_get_contents($file);
            $css = preg_replace('!/\*.*?\*/!s', '', $css) ?? $css;
            $css = preg_replace('/\s+/', ' ', $css) ?? $css;
            $css = preg_replace('/\s*([{};:,>])\s*/', '$1', $css) ?? $css;

            return str_replace(';}', '}', trim($css));
        });
    }

    public static function js(): string
    {
        return asset('js/site.js').'?v='.(int) @filemtime(public_path('js/site.js'));
    }
}
