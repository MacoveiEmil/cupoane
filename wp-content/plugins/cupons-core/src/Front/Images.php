<?php

declare(strict_types=1);

namespace Cupons\Core\Front;

/**
 * Dimensiuni de imagine pentru logo-uri și deal-uri; variantele generate sunt WebP.
 */
final class Images
{
    public const LOGO    = 'cupons-logo';     // 120×120, grilă homepage + header magazin
    public const LOGO_SM = 'cupons-logo-sm';  // 56×56, carduri
    public const DEAL    = 'cupons-deal';     // 320×320, carduri deal

    public static function register_hooks(): void
    {
        add_action('after_setup_theme', [self::class, 'sizes'], 20);
        add_filter('image_editor_output_format', [self::class, 'webp'], 10, 3);
    }

    public static function sizes(): void
    {
        add_image_size(self::LOGO, 120, 120, false);
        add_image_size(self::LOGO_SM, 56, 56, false);
        add_image_size(self::DEAL, 320, 320, false);
    }

    /** JPEG/PNG încărcate → dimensiunile generate se salvează WebP (dacă serverul suportă). */
    public static function webp(array $formats, mixed $filename = null, mixed $mime = null): array
    {
        static $supported = null, $checking = false;
        if ($checking) {
            return $formats; // wp_image_editor_supports() reaplică acest filtru
        }
        // GD nu poate salva ca WebP un PNG cu paletă de culori: îl lăsăm PNG.
        if ($mime === 'image/png' && is_string($filename) && self::is_palette_png($filename)) {
            return $formats;
        }
        if ($supported === null) {
            $checking  = true;
            $supported = wp_image_editor_supports(['mime_type' => 'image/webp']);
            $checking  = false;
        }
        if ($supported) {
            $formats['image/jpeg'] = 'image/webp';
            $formats['image/png']  = 'image/webp';
        }
        return $formats;
    }

    /** Tipul de culoare din antetul IHDR al PNG-ului (octetul 25): 3 = paletă. */
    private static function is_palette_png(string $file): bool
    {
        if (!is_readable($file)) {
            return false;
        }
        $head = (string) file_get_contents($file, false, null, 0, 26);
        return strlen($head) === 26 && ord($head[25]) === 3;
    }
}
