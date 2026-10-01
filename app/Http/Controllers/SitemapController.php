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
        // lastmod = ultima modificare reală de oferte (Google ignoră lastmod-ul care se schimbă la fiecare citire).
        $last = Catalog::lastChange();
        $urls = [[url('/'), $last], [route('stores'), $last], [route('categories'), $last], [route('top'), $last]];
        if (\App\Models\Deal::active()->exists()) {
            $urls[] = [route('deals'), \App\Models\Deal::max('updated_at') ? \Illuminate\Support\Carbon::parse(\App\Models\Deal::max('updated_at')) : null];
        }

        foreach (\App\Models\Store::orderBy('name')->get() as $s) {
            if ($s->indexable(Catalog::count($s))) {
                $urls[] = [$s->url(), $s->updated_on ?? $s->updated_at];
            }
        }
        foreach (Catalog::categoryTree(true) as $row) {
            if ($row['offers'] >= Seo::MIN_OFFERS_INDEX) {
                $urls[] = [route('category', $row['category']), Catalog::lastChange($row['ids'])];
            }
            foreach ($row['children'] as $ch) {
                if ($ch['offers'] >= Seo::MIN_OFFERS_INDEX) {
                    $urls[] = [route('category', $ch['category']), Catalog::lastChange($ch['ids'])];
                }
            }
        }
        foreach (Tag::all() as $t) {
            if ($t->coupons()->active()->count() >= Seo::MIN_OFFERS_INDEX) {
                $urls[] = [route('tag', $t), null];
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
        // de indexare Google ne ferește meta noindex, pus pe toate paginile din afara producției (Seo::isPreview).
        $body = "User-agent: *\nDisallow: /go/\nDisallow: /admin\nDisallow: /cauta\nDisallow: /api/\n\nSitemap: ".url('/sitemap.xml')."\n";

        return response($body, 200, ['Content-Type' => 'text/plain; charset=UTF-8']);
    }
}
