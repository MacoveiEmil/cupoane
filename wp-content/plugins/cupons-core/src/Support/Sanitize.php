<?php

declare(strict_types=1);

namespace Cupons\Core\Support;

final class Sanitize
{
    public static function url(mixed $v): string
    {
        $v = is_string($v) ? trim($v) : '';
        if ($v === '') {
            return '';
        }
        $url = esc_url_raw($v, ['http', 'https']);
        return self::is_http_url($url) ? $url : '';
    }

    /**
     * Validare structurală (schemă http/https + host). Intenționat fără wp_http_validate_url(),
     * care face interogări DNS: lent la fiecare redirect și respinge domenii încă nerezolvabile.
     */
    public static function is_http_url(string $url): bool
    {
        if ($url === '' || strlen($url) > 2048) {
            return false;
        }
        $p = wp_parse_url($url);
        return is_array($p)
            && in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true)
            && !empty($p['host'])
            && !str_contains($p['host'], ' ');
    }

    public static function flag(mixed $v): string
    {
        return (!empty($v) && $v !== '0') ? '1' : '0';
    }

    public static function date(mixed $v): string
    {
        return Dates::normalize($v);
    }

    /** Număr pozitiv cu maxim 2 zecimale, ca string ('' dacă lipsește). Acceptă virgulă. */
    public static function amount(mixed $v): string
    {
        $v = is_scalar($v) ? str_replace([' ', ','], ['', '.'], (string) $v) : '';
        if ($v === '' || !is_numeric($v) || (float) $v < 0) {
            return '';
        }
        return rtrim(rtrim(number_format((float) $v, 2, '.', ''), '0'), '.');
    }

    public static function code(mixed $v): string
    {
        $v = is_string($v) ? sanitize_text_field($v) : '';
        return mb_substr(preg_replace('/\s+/u', '', $v) ?? '', 0, 64);
    }

    public static function enum(mixed $v, array $allowed, string $default): string
    {
        $v = is_string($v) ? $v : '';
        return in_array($v, $allowed, true) ? $v : $default;
    }

    /** Text scurt cu linkuri și accent minim (condiții, FAQ). */
    public static function rich(mixed $v): string
    {
        $v = is_string($v) ? $v : '';
        return trim(wp_kses($v, [
            'a'      => ['href' => true],
            'strong' => [],
            'em'     => [],
            'br'     => [],
        ]));
    }
}
