<?php

namespace App\Http\Middleware;

use App\Support\PageCache;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cache complet de pagini: prima vizită generează HTML-ul, următoarele îl primesc direct din cache
 * (fără interogări în baza de date). Golit automat la orice modificare de conținut.
 * Antetele permit și cache-ul serverului (LiteSpeed) / CDN-ului (Cloudflare).
 */
class CachePage
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->isMethod('GET') || $request->query('nocache')) {
            return $next($request);
        }

        $key = PageCache::key($request->fullUrl());
        if ($hit = PageCache::store()->get($key)) {
            return response($hit['body'], $hit['status'], $hit['headers'] + ['X-Page-Cache' => 'HIT']);
        }

        $response = $next($request);

        if ($response->getStatusCode() === 200 && ! $response->headers->has('Set-Cookie')) {
            $headers = [
                'Content-Type' => $response->headers->get('Content-Type', 'text/html; charset=UTF-8'),
                'Cache-Control' => 'public, max-age=0, s-maxage=3600',
                'X-LiteSpeed-Cache-Control' => 'public,max-age=86400',
            ];
            if ($robots = $response->headers->get('X-Robots-Tag')) {
                $headers['X-Robots-Tag'] = $robots;
            }
            PageCache::store()->put($key, ['body' => $response->getContent(), 'status' => 200, 'headers' => $headers], PageCache::TTL);
            foreach ($headers as $name => $value) {
                $response->headers->set($name, $value);
            }
            $response->headers->set('X-Page-Cache', 'MISS');
        }

        return $response;
    }
}
