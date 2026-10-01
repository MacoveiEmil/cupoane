<?php

namespace App\Support;

use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

/**
 * Cache complet de pagini pentru vizitatori: HTML gata făcut, servit în milisecunde pe orice hosting.
 * Are un spațiu de cache separat („pages”), golit integral la orice modificare de conținut
 * (cupon, magazin, setare, curățare zilnică). Simplu și fără risc de pagini vechi.
 */
final class PageCache
{
    /** Query-urile care schimbă conținutul; restul (?ref=, ?utm_…) nu creează copii separate în cache. */
    public const PARAMS = ['q', 'page'];

    /** Cât poate CDN-ul (Cloudflare) să țină HTML-ul fără să întrebe serverul. */
    public const EDGE_TTL = 300;

    /**
     * Secunde până la miezul nopții (ora României). Ofertele expiră la nivel de zi, deci orice pagină din cache
     * (aplicație sau LiteSpeed) expiră singură exact când s-ar putea schimba — și la trecerea în luna următoare.
     */
    public static function ttl(): int
    {
        return max(60, min(86400, (int) Day::now()->diffInSeconds(Day::today()->addDay()->addSeconds(30), true)));
    }

    public static function store(): Repository
    {
        return Cache::store('pages');
    }

    /** Cheia include versiunea CSS/JS: după o schimbare de design, paginile se regenerează singure. */
    public static function key(Request $request): string
    {
        $query = array_filter(Arr::only($request->query(), self::PARAMS), fn ($v) => is_string($v) && $v !== '');
        ksort($query);

        return 'p:'.sha1($request->url().'?'.http_build_query($query).'|'.Assets::version());
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
