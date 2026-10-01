<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/** Zile calendaristice în fusul orar al site-ului (Europe/Bucharest). Expirarea e inclusivă. */
final class Day
{
    private const MONTHS = ['', 'ian.', 'feb.', 'mar.', 'apr.', 'mai', 'iun.', 'iul.', 'aug.', 'sept.', 'oct.', 'nov.', 'dec.'];

    private const MONTHS_LONG = ['', 'ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];

    public static function today(): CarbonImmutable
    {
        return CarbonImmutable::now(config('app.timezone'))->startOfDay();
    }

    /** 5 oct. 2026 */
    public static function human(?CarbonInterface $d): string
    {
        return $d ? $d->day.' '.self::MONTHS[$d->month].' '.$d->year : '';
    }

    /** octombrie 2026 */
    public static function monthYear(?CarbonInterface $d = null): string
    {
        $d ??= self::today();

        return self::MONTHS_LONG[$d->month].' '.$d->year;
    }

    /** 1 octombrie */
    public static function dayMonth(?CarbonInterface $d = null): string
    {
        $d ??= self::today();

        return $d->day.' '.self::MONTHS_LONG[$d->month];
    }
}
