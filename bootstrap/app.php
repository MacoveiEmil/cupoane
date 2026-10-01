<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Proxy-uri de încredere: în producție doar Cloudflare (IP + https), local orice (tunel). Vezi TrustProxies.
        $middleware->replace(\Illuminate\Http\Middleware\TrustProxies::class, \App\Http\Middleware\TrustProxies::class);
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);
        // Cache-ul de pagini rulează înaintea încărcării modelelor din URL: o pagină din cache = zero interogări.
        $middleware->prependToPriorityList(before: \Illuminate\Routing\Middleware\SubstituteBindings::class, prepend: \App\Http\Middleware\CachePage::class);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        //
    })->create();
