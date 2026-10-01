<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Page;
use App\Models\Tag;
use App\Support\Catalog;
use App\Support\Seo;

/** sitemap.xml (doar paginile indexabile) și robots.txt. */
class SitemapController extends Controller
{
    public function sitemap()
    {
        $urls = [[url('/'), now()], [route('stores'), now()], [route('categories'), now()], [route('top'), now()]];
        if (\App\Models\Deal::active()->exists()) {
            $urls[] = [route('deals'), now()];
        }

        foreach (Catalog::storesWithOffers() as $s) {
            if (Catalog::count($s) >= Seo::MIN_OFFERS_INDEX) {
                $urls[] = [$s->url(), $s->updated_on ?? $s->updated_at];
            }
        }
        foreach (Catalog::categoryTree(true) as $row) {
            if ($row['offers'] >= Seo::MIN_OFFERS_INDEX) {
                $urls[] = [route('category', $row['category']), now()];
            }
            foreach ($row['children'] as $ch) {
                if ($ch['offers'] >= Seo::MIN_OFFERS_INDEX) {
                    $urls[] = [route('category', $ch['category']), now()];
                }
            }
        }
        foreach (Tag::all() as $t) {
            if ($t->coupons()->active()->count() >= Seo::MIN_OFFERS_INDEX) {
                $urls[] = [route('tag', $t), now()];
            }
        }
        foreach (Page::where('body', '!=', '')->get() as $p) {
            $urls[] = [url('/'.$p->slug), $p->updated_at];
        }

        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        foreach ($urls as [$loc, $mod]) {
            $xml .= '<url><loc>'.e($loc).'</loc>'.($mod ? '<lastmod>'.$mod->toDateString().'</lastmod>' : '').'</url>';
        }
        $xml .= '</urlset>';

        return response($xml, 200, ['Content-Type' => 'application/xml; charset=UTF-8']);
    }

    public function robots()
    {
        // Și pe linkul de preview (trycloudflare) roboții pot citi paginile, ca un AI să poată verifica site-ul;
        // de indexare Google ne ferește meta noindex, pus pe toate paginile de preview (Seo::isPreviewHost).
        $body = "User-agent: *\nDisallow: /go/\nDisallow: /admin\nDisallow: /cauta\nDisallow: /api/\n\nSitemap: ".url('/sitemap.xml')."\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
