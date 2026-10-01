<?php

namespace App\Http\Middleware;

use App\Support\Assets;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Antete de securitate pe toate răspunsurile. CSP strict doar pe site-ul public (fără scripturi terțe);
 * adminul Filament / Livewire are nevoie de scripturi inline, deci primește doar antetele de bază.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;
        $admin = $request->is('admin', 'admin/*', 'livewire*', 'filament/*');

        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=()');
        $h->set('X-Frame-Options', 'SAMEORIGIN'); // pe site-ul public, CSP frame-ancestors 'none' e mai strict
        if (! $admin && str_contains((string) $h->get('Content-Type'), 'text/html')) {
            $h->set('Content-Security-Policy', Assets::csp());
        }
        if ($request->secure() && app()->isProduction()) {
            $h->set('Strict-Transport-Security', 'max-age=31536000');
        }

        return $response;
    }
}
