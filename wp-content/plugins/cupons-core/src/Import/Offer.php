<?php

declare(strict_types=1);

namespace Cupons\Core\Import;

/** O ofertă normalizată, indiferent de rețea. Datele sunt Y-m-d (ora României) sau ''. */
final class Offer
{
    public function __construct(
        public readonly string $ext_id,      // „retea:id” — cheia de deduplicare
        public readonly string $program_id,
        public readonly string $title,
        public readonly string $code,
        public readonly string $value,       // număr ca string sau ''
        public readonly string $value_type,  // procent | lei | transport
        public readonly string $terms,
        public readonly string $link,        // link de afiliere gata făcut
        public readonly string $start,
        public readonly string $end,
        public readonly bool $exclusive = false,
        public readonly bool $upto = false,       // „până la X%”
    ) {
    }

    /**
     * Extrage valoarea reducerii dintr-un text („45 %”, „-20%”, „50 lei”, „transport gratuit”).
     * @return array{0:string,1:string,2:bool} [valoare, tip, „până la”]
     */
    public static function parse_value(string $text): array
    {
        $t    = mb_strtolower($text);
        $upto = (bool) preg_match('/(p[aâ]n[aă]\s+la|up\s+to)\s*-?\s*\d/u', $t);
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*%/u', $t, $m)) {
            return [str_replace(',', '.', $m[1]), 'procent', $upto];
        }
        if (preg_match('/(\d+(?:[.,]\d+)?)\s*(lei|ron)\b/u', $t, $m)) {
            return [str_replace(',', '.', $m[1]), 'lei', $upto];
        }
        if (preg_match('/(transport|livrare)\s+gratuit/u', $t)) {
            return ['', 'transport', false];
        }
        return ['', 'procent', false];
    }

    /** Dată ISO / „Y-m-d H:i:s” → Y-m-d în fusul site-ului. */
    public static function local_date(string $value): string
    {
        $value = trim($value);
        if ($value === '' || str_starts_with($value, '0000')) {
            return '';
        }
        try {
            $d = new \DateTimeImmutable($value, new \DateTimeZone('UTC'));
            return $d->setTimezone(wp_timezone())->format('Y-m-d');
        } catch (\Exception) {
            return '';
        }
    }
}
