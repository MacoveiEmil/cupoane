<?php

declare(strict_types=1);

namespace Cupons\Core\Support;

/**
 * Setările din admin (o singură opțiune serializată, fără autoload pe frontend inutil).
 * Cheile API vor fi tot aici (Faza 3), niciodată în cod.
 */
final class Settings
{
    public const OPTION = 'cupons_settings';

    public static function defaults(): array
    {
        return [
            'recheck_days' => 30,   // oferte fără expirare, neverificate de N zile → ciornă
            'vote_min'     => 5,    // rata de succes apare de la N voturi
            'trust_cf_ip'  => 0,    // folosește CF-Connecting-IP (doar dacă site-ul e în spatele Cloudflare)
            'auto_stores'  => 1,    // la import, creează magazine pentru programele acceptate neasociate
            'networks_off' => [],   // rețele dezactivate la import (ID-uri conector)
        ];
    }

    public static function all(): array
    {
        $saved = get_option(self::OPTION, []);
        return array_merge(self::defaults(), is_array($saved) ? $saved : []);
    }

    public static function get(string $key): mixed
    {
        return self::all()[$key] ?? null;
    }

    public static function int(string $key): int
    {
        return (int) self::get($key);
    }
}
