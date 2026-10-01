<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Cache;

/**
 * Cache complet de pagini pentru vizitatori: HTML gata făcut, servit în milisecunde pe orice hosting.
 * Are un spațiu de cache separat („pages”), golit integral la orice modificare de conținut
 * (cupon, magazin, setare, curățare zilnică). Simplu și fără risc de pagini vechi.
 */
final class PageCache
{
    public const TTL = 86400; // o zi; curățarea de la 00:05 golește oricum cache-ul

    public static function store(): Repository
    {
        return Cache::store('pages');
    }

    /** Cheia include versiunea CSS/JS: după o schimbare de design, paginile se regenerează singure. */
    public static function key(string $url): string
    {
        $assets = (int) @filemtime(resource_path('css/site.css')).'-'.(int) @filemtime(public_path('js/site.js'));

        return 'p:'.sha1($url.'|'.$assets);
    }

    public static function flush(): void
    {
        self::store()->flush();
        // Pe LiteSpeed, același răspuns cere și golirea cache-ului serverului (dacă e folosit).
        if (! app()->runningInConsole() && ! headers_sent()) {
            header('X-LiteSpeed-Purge: *', false);
        }
    }
}
