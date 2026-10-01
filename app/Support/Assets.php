<?php

namespace App\Support;

/**
 * CSS-ul e scris inline în <head> (minificat): zero requesturi blocante.
 * JS-ul e un singur fișier mic, încărcat cu defer și versionat după data modificării.
 *
 * CSS-ul minificat și politica CSP se compilează o singură dată într-un fișier PHP din bootstrap/cache
 * (citit din OPcache): o pagină servită din cache nu atinge deloc baza de date.
 */
final class Assets
{
    /** Singurul JS inline (clasa „js” pe <html>, înainte de randare: fără layout shift). */
    public const INLINE_JS = "document.documentElement.classList.add('js')";

    private static ?array $compiled = null;

    public static function css(): string
    {
        return self::compiled()['css'];
    }

    /** Politica CSP a site-ului public: doar resurse proprii; CSS-ul și scriptul inline sunt permise prin hash. */
    public static function csp(): string
    {
        return self::compiled()['csp'];
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

    /** @return array{css:string, csp:string} */
    private static function compiled(): array
    {
        $version = self::version();
        if (self::$compiled !== null && self::$compiled['version'] === $version) {
            return self::$compiled;
        }

        $file = app()->bootstrapPath('cache/assets.php');
        $data = is_file($file) ? require $file : null;
        if (! is_array($data) || ($data['version'] ?? null) !== $version) {
            $css = self::minify((string) @file_get_contents(resource_path('css/site.css')));
            $data = ['version' => $version, 'css' => $css, 'csp' => self::buildCsp($css)];
            $tmp = $file.'.'.getmypid().'.tmp';
            if (@file_put_contents($tmp, '<?php return '.var_export($data, true).';'.PHP_EOL) !== false) {
                @rename($tmp, $file); // atomic: niciun request nu citește un fișier pe jumătate scris
                if (function_exists('opcache_invalidate')) {
                    @opcache_invalidate($file, true);
                }
            }
        }

        return self::$compiled = $data;
    }

    private static function minify(string $css): string
    {
        $css = preg_replace('!/\*.*?\*/!s', '', $css) ?? $css;
        $css = preg_replace('/\s+/', ' ', $css) ?? $css;
        $css = preg_replace('/\s*([{};:,>])\s*/', '$1', $css) ?? $css;

        return str_replace(';}', '}', trim($css));
    }

    private static function buildCsp(string $css): string
    {
        $hash = fn (string $s) => "'sha256-".base64_encode(hash('sha256', $s, true))."'";

        return implode('; ', [
            "default-src 'self'",
            "script-src 'self' ".$hash(self::INLINE_JS),
            "style-src 'self' ".$hash($css),
            "img-src 'self' data:",
            "connect-src 'self'",
            "form-action 'self'",
            "base-uri 'self'",
            "object-src 'none'",
            "frame-ancestors 'none'",
        ]);
    }
}
