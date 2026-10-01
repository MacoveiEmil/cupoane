<?php

namespace App\Http\Middleware;

use Illuminate\Http\Middleware\TrustProxies as Middleware;
use Illuminate\Http\Request;

/**
 * Producție: aplicația are încredere DOAR în Cloudflare (listă oficială: cloudflare.com/ips), și doar pentru
 * IP-ul vizitatorului și schema (https). Host-ul vine mereu din APP_URL (AppServiceProvider), nu din antete.
 * Astfel $request->ip() e IP-ul real (voturi, limitări), iar cine ajunge direct la server nu-l poate falsifica.
 *
 * Local / tunel de preview: orice proxy, inclusiv host-ul, ca linkurile din tunel să fie corecte.
 */
class TrustProxies extends Middleware
{
    public const CLOUDFLARE = [
        '173.245.48.0/20', '103.21.244.0/22', '103.22.200.0/22', '103.31.4.0/22', '141.101.64.0/18',
        '108.162.192.0/18', '190.93.240.0/20', '188.114.96.0/20', '197.234.240.0/22', '198.41.128.0/17',
        '162.158.0.0/15', '104.16.0.0/13', '104.24.0.0/14', '172.64.0.0/13', '131.0.72.0/22',
        '2400:cb00::/32', '2606:4700::/32', '2803:f800::/32', '2405:b500::/32', '2405:8100::/32',
        '2a06:98c0::/29', '2c0f:f248::/32',
    ];

    protected function proxies()
    {
        return app()->isProduction() ? [...self::CLOUDFLARE, '127.0.0.1', '::1'] : '*';
    }

    protected function headers()
    {
        return app()->isProduction()
            ? Request::HEADER_X_FORWARDED_FOR | Request::HEADER_X_FORWARDED_PROTO
            : Request::HEADER_X_FORWARDED_PROTO | Request::HEADER_X_FORWARDED_HOST | Request::HEADER_X_FORWARDED_PORT;
    }
}
