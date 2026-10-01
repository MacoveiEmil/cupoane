<?php

namespace App\Support;

use Illuminate\Support\Facades\Cache;

/**
 * CSS-ul e scris inline în <head> (bază comună + tema aleasă, minificat): zero requesturi blocante.
 * JS-ul e un singur fișier mic, încărcat cu defer și versionat după data modificării.
 */
final class Assets
{
    public const THEMES = [
        'a' => 'Dulce — pasteluri calde, colțuri rotunde, umbre moi',
        'b' => 'Vitrină — grilă de oferte cu bandă colorată (stil aplicație de shopping)',
        'c' => 'Simplu — listă aerisită, fără cutii, linii fine',
    ];

    /** Tema activă: ?tema=x pentru previzualizare, altfel setarea din admin. */
    public static function theme(): string
    {
        $preview = (string) request()->query('tema', '');
        if (isset(self::THEMES[$preview])) {
            return $preview;
        }
        $saved = (string) Settings::get('theme', 'a');

        return isset(self::THEMES[$saved]) ? $saved : 'a';
    }

    public static function css(string $theme): string
    {
        $files = [resource_path('css/base.css'), resource_path('css/theme-'.$theme.'.css')];

        return Cache::rememberForever('css:'.$theme.':'.self::version(), function () use ($files) {
            $css = implode("\n", array_map(fn ($f) => (string) @file_get_contents($f), $files));
            $css = preg_replace('!/\*.*?\*/!s', '', $css) ?? $css;
            $css = preg_replace('/\s+/', ' ', $css) ?? $css;
            $css = preg_replace('/\s*([{};:,>])\s*/', '$1', $css) ?? $css;

            return str_replace(';}', '}', trim($css));
        });
    }

    /** Versiunea fișierelor de stil și JS (se schimbă la orice modificare de design). */
    public static function version(): string
    {
        $m = (int) @filemtime(public_path('js/site.js'));
        foreach (glob(resource_path('css/*.css')) ?: [] as $f) {
            $m = max($m, (int) filemtime($f));
        }

        return (string) $m;
    }

    public static function js(): string
    {
        return asset('js/site.js').'?v='.(int) @filemtime(public_path('js/site.js'));
    }
}
