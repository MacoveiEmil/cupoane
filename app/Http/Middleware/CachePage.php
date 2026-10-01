<?php

namespace App\Http\Middleware;

use App\Support\PageCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cache complet de pagini: prima vizită generează HTML-ul, următoarele îl primesc direct din cache
 * (fără interogări în baza de date). Golit automat la orice modificare de conținut și expirat singur la miezul nopții.
 * Antetele permit și cache-ul serverului (LiteSpeed, până la miezul nopții) și al CDN-ului (Cloudflare, câteva minute).
 */
class CachePage
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || $request->query('nocache')) {
            return $next($request);
        }

        $key = PageCache::key($request);
        if ($hit = PageCache::store()->get($key)) {
            return $this->withCacheHeaders(response($hit['body'], $hit['status'], $hit['headers']), 'HIT');
        }

        $response = $next($request);

        if ($response->getStatusCode() === 200 && ! $response->headers->has('Set-Cookie')) {
            $headers = ['Content-Type' => $response->headers->get('Content-Type', 'text/html; charset=UTF-8')];
            if ($robots = $response->headers->get('X-Robots-Tag')) {
                $headers['X-Robots-Tag'] = $robots;
            }
            PageCache::store()->put($key, ['body' => $response->getContent(), 'status' => 200, 'headers' => $headers], PageCache::ttl());
            $this->withCacheHeaders($response, 'MISS');
        }

        return $response;
    }

    /** Duratele se calculează la fiecare răspuns, ca să nu treacă niciodată de miezul nopții. */
    private function withCacheHeaders(Response $response, string $state): Response
    {
        $ttl = PageCache::ttl();
        $response->headers->set('Cache-Control', 'public, max-age=0, s-maxage='.min(PageCache::EDGE_TTL, $ttl));
        $response->headers->set('X-LiteSpeed-Cache-Control', 'public,max-age='.$ttl);
        $response->headers->set('X-Page-Cache', $state);

        return $response;
    }
}
