<?php

use App\Http\Controllers\GoController;
use App\Http\Controllers\SiteController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\VoteController;
use App\Http\Middleware\CachePage;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

/*
 * Site-ul public: fără sesiune, fără cookie-uri, fără CSRF → fiecare pagină poate fi cache-uită complet
 * (în aplicație, pe LiteSpeed și pe Cloudflare). Adminul (/admin) are stiva lui proprie, din Filament.
 */
$stateless = [StartSession::class, ShareErrorsFromSession::class, ValidateCsrfToken::class, AddQueuedCookiesToResponse::class, EncryptCookies::class];

Route::withoutMiddleware($stateless)->group(function () {
    // Redirect de afiliere și vot: niciodată din cache.
    Route::get('/go/{key}', GoController::class)->where('key', '[A-Za-z0-9\-]+')->name('go');
    Route::post('/api/vote', VoteController::class)->middleware('throttle:30,1')->name('vote');
    // Căutarea nu intră în cache (fiecare text ar crea o intrare nouă); e noindex și limitată.
    Route::get('/cauta', [SiteController::class, 'search'])->middleware('throttle:60,1')->name('search');

    Route::middleware(CachePage::class)->group(function () {
        Route::get('/', [SiteController::class, 'home'])->name('home');
        Route::get('/magazine', [SiteController::class, 'stores'])->name('stores');
        Route::get('/magazin/{store:slug}', [SiteController::class, 'store'])->name('store');
        Route::get('/categorii', [SiteController::class, 'categories'])->name('categories');
        Route::get('/categorie/{category:slug}', [SiteController::class, 'category'])->name('category');
        Route::get('/oferte/{tag:slug}', [SiteController::class, 'tag'])->name('tag');
        Route::get('/deal-uri', [SiteController::class, 'deals'])->name('deals');
        Route::get('/top-oferte', [SiteController::class, 'top'])->name('top');
        Route::get('/magazine.json', [SiteController::class, 'searchIndex'])->name('search.index');
        Route::get('/sitemap.xml', [SitemapController::class, 'sitemap']);
        Route::get('/robots.txt', [SitemapController::class, 'robots']);
        // Paginile statice (Despre noi, Contact…) — ultima rută, doar pentru slug-urile din tabela pages.
        Route::get('/{page:slug}', [SiteController::class, 'page'])->where('page', '[a-z0-9\-]+')->name('page');
    });
});
