<?php

namespace App\Providers;

use App\Models\Page;
use App\Support\Settings;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        if ($this->app->isProduction()) {
            // Domeniul și schema vin doar din APP_URL, niciodată din antetele cererii (fără canonical / linkuri falsificabile).
            URL::forceRootUrl(config('app.url'));
            if (str_starts_with((string) config('app.url'), 'https://')) {
                URL::forceScheme('https');
            }
            // Plasă de siguranță: în producție nu se afișează niciodată detaliile erorilor.
            config(['app.debug' => false]);
        }

        // Datele comune layout-ului public (meniu, footer, texte editabile).
        View::composer(['layouts.app', 'home'], function ($view) {
            $view->with([
                'site' => Settings::all(),
                // „Deal-uri” apare doar când există deal-uri active.
                'menu' => array_values(array_filter([
                    ['Magazine', route('stores')],
                    ['Categorii', route('categories')],
                    \App\Models\Deal::active()->exists() ? ['Deal-uri', route('deals')] : null,
                    ['Top oferte', route('top')],
                ])),
                'footerPages' => Page::where('in_footer', true)->orderBy('sort')->get(['slug', 'title']),
                // Linkare internă: magazinele importante, la un click de orice pagină.
                'footerStores' => \App\Support\Catalog::popular(20),
            ]);
        });
    }
}
