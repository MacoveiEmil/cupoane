<?php

declare(strict_types=1);

namespace Cupons\Core\Support;

/**
 * Toate datele sunt zile calendaristice în fusul orar al site-ului (Europe/Bucharest).
 * O ofertă cu expirare 2026-10-05 e validă toată ziua de 5 octombrie.
 */
final class Dates
{
    public static function today(): string
    {
        return current_datetime()->format('Y-m-d');
    }

    public static function offset(int $days, ?string $from = null): string
    {
        $base = $from ? new \DateTimeImmutable($from, wp_timezone()) : current_datetime();
        return $base->modify(sprintf('%+d days', $days))->format('Y-m-d');
    }

    /** Returnează data validă Y-m-d sau ''. */
    public static function normalize(mixed $value): string
    {
        $value = is_string($value) ? trim($value) : '';
        if ($value === '') {
            return '';
        }
        $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, wp_timezone());
        return ($d && $d->format('Y-m-d') === $value) ? $value : '';
    }

    /** Formatul afișat: 5 oct. 2026 */
    public static function human(string $ymd): string
    {
        if (self::normalize($ymd) === '') {
            return '';
        }
        $d = new \DateTimeImmutable($ymd, wp_timezone());
        return wp_date('j M Y', $d->getTimestamp());
    }

    /** Timestamp pentru următoarea rulare la ora dată (ora locală a site-ului). */
    public static function next_local_time(string $hm): int
    {
        $tz   = wp_timezone();
        $now  = new \DateTimeImmutable('now', $tz);
        $next = new \DateTimeImmutable('today ' . $hm, $tz);
        if ($next <= $now) {
            $next = $next->modify('+1 day');
        }
        return $next->getTimestamp();
    }
}
