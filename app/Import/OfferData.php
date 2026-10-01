<?php

namespace App\Import;

use Carbon\CarbonImmutable;

/** O ofertă normalizată, indiferent de rețea. Datele sunt Y-m-d (ora României) sau ''. */
final class OfferData
{
    public function __construct(
        public readonly string $extId,
        public readonly string $programId,
        public readonly string $title,
        public readonly string $code,
        public readonly ?float $value,
        public readonly string $valueType,
        public readonly bool $upto,
        public readonly string $terms,
        public readonly string $link,
        public readonly string $start,
        public readonly string $end,
    ) {
    }

    /**
     * Valoarea reducerii dintr-un text („45 %”, „până la 47%”, „50 lei”, „transport gratuit”).
     * @return array{0: ?float, 1: string, 2: bool} [valoare, tip, „până la”]
     */
    public static function parseValue(string $text): array
    {
        $t = mb_strtolower($text);
        $upto = (bool) preg_match('/(p[aâ]n[aă]\s+la|up\s+to)\s*-?\s*\d/u', $t);
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*%/u', $t, $m)) {
            return [(float) str_replace(',', '.', $m[1]), 'procent', $upto];
        }
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(lei|ron)\b/u', $t, $m)) {
            return [(float) str_replace(',', '.', $m[1]), 'lei', $upto];
        }
        if (preg_match('/(transport|livrare)\s+gratuit/u', $t)) {
            return [null, 'transport', false];
        }

        return [null, 'procent', false];
    }

    /** Dată ISO (UTC) → Y-m-d în fusul site-ului. */
    public static function localDate(?string $value): string
    {
        $value = trim((string) $value);
        if ($value === '' || str_starts_with($value, '0000')) {
            return '';
        }
        try {
            return CarbonImmutable::parse($value, 'UTC')->setTimezone(config('app.timezone'))->toDateString();
        } catch (\Throwable) {
            return '';
        }
    }
}
