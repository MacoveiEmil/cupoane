<?php

namespace App\Providers;

use App\Models\Page;
use App\Support\Settings;
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
        // Datele comune layout-ului public (meniu, footer, texte editabile).
        View::composer(['layouts.app', 'home'], function ($view) {
            $view->with([
                'site' => Settings::all(),
                'menu' => [
                    ['Magazine', route('stores')],
                    ['Categorii', route('categories')],
                    ['Deal-uri', route('deals')],
                    ['Top oferte', route('top')],
                ],
                'footerPages' => Page::where('in_footer', true)->orderBy('sort')->get(['slug', 'title']),
            ]);
        });
    }
}
