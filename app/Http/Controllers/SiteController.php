<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Deal;
use App\Models\Page;
use App\Models\Store;
use App\Models\Tag;
use App\Support\Catalog;
use App\Support\Day;
use App\Support\Seo;
use App\Support\Settings;
use Illuminate\Http\Request;

/** Paginile publice. Toate sunt servite prin cache-ul de pagini (App\Http\Middleware\CachePage). */
class SiteController extends Controller
{
    private const PER_PAGE = 24;

    public function home()
    {
        $top = Catalog::topOffers(8);
        $exclusive = Catalog::exclusive(6, $top->pluck('id'));
        $recent = Catalog::recent(8, $top->pluck('id')->merge($exclusive->pluck('id')));
        $counts = Catalog::storeCounts();

        return view('home', [
            'seo' => new Seo(
                title: Seo::site().' — Vouchere, reduceri și cupoane verificate zilnic',
                description: 'Vouchere, reduceri și cupoane verificate pentru magazinele online din România. Coduri actualizate zilnic, fără oferte expirate.',
            ),
            'popular' => Catalog::popular(3),
            'top' => $top,
            'exclusive' => $exclusive,
            'recent' => $recent,
            'deals' => Catalog::deals(6),
            'categories' => Catalog::categoryTree(true),
            'stats' => ['offers' => array_sum($counts), 'stores' => count($counts)],
            'votes' => Catalog::votes($top->concat($exclusive)->concat($recent)->pluck('id')),
        ]);
    }

    public function store(Store $store)
    {
        $coupons = $store->activeCoupons()->with('tags')->ranked()->get();
        $count = $coupons->count();
        $ll = Day::monthYear();
        $faq = collect($store->faq ?? [])->filter(fn ($r) => ! empty($r['q']) && ! empty($r['a']))->map(fn ($r) => [$r['q'], $r['a']])->values()->all();

        return view('store', [
            'seo' => new Seo(
                title: "Cod reducere {$store->name} {$ll} — vouchere, reduceri și cupoane",
                description: "Vouchere, reduceri și cupoane {$store->name} verificate: ".Seo::plural($count, 'ofertă activă', 'oferte active')." în {$ll}. Actualizat la ".Day::human($store->updated_on ?? Day::today()).'.',
                canonical: $store->url(),
                noindex: $count < Seo::MIN_OFFERS_INDEX,
                breadcrumbs: [['Magazine', route('stores')], [$store->name, $store->url()]],
                faq: $faq,
            ),
            'store' => $store,
            'coupons' => $coupons,
            'deals' => $store->deals()->active()->latest('id')->take(6)->get(),
            'similar' => Catalog::similar($store, 6),
            'votes' => Catalog::votes($coupons->pluck('id')),
            'faq' => $faq,
        ]);
    }

    public function stores()
    {
        $groups = Catalog::storesWithOffers()->groupBy(function ($s) {
            $l = mb_strtoupper(mb_substr(\Illuminate\Support\Str::ascii($s->name), 0, 1));

            return ctype_alpha($l) ? $l : '0–9';
        })->sortKeys();

        return view('stores', [
            'seo' => new Seo(title: 'Magazine A–Z — vouchere, reduceri și cupoane | '.Seo::site(), description: 'Toate magazinele online cu vouchere, reduceri și cupoane active, în ordine alfabetică.', breadcrumbs: [['Magazine', route('stores')]]),
            'groups' => $groups,
        ]);
    }

    public function categories()
    {
        return view('categories', [
            'seo' => new Seo(title: 'Categorii — vouchere, reduceri și cupoane | '.Seo::site(), description: 'Vouchere, reduceri și cupoane pe categorii de magazine.', breadcrumbs: [['Categorii', route('categories')]]),
            'tree' => Catalog::categoryTree(true),
        ]);
    }

    public function category(Category $category, Request $request)
    {
        $storeIds = $category->storeIds();
        $coupons = Coupon::active()->with('store')->whereIn('store_id', $storeIds)->orderByDesc('top')->ranked()->paginate(self::PER_PAGE);
        $page = $coupons->currentPage();
        $crumbs = [['Categorii', route('categories')]];
        if ($category->parent) {
            $crumbs[] = [$category->parent->name, route('category', $category->parent)];
        }
        $crumbs[] = [$category->name, route('category', $category)];

        return view('category', [
            'seo' => new Seo(
                title: "Vouchere, reduceri și cupoane {$category->name} — ".Day::monthYear().($page > 1 ? " — pagina {$page}" : ''),
                description: "Vouchere, reduceri și cupoane {$category->name}: ".Seo::plural($coupons->total(), 'ofertă activă', 'oferte active').' verificate în '.Day::monthYear().'.',
                canonical: $page > 1 ? $coupons->url($page) : route('category', $category),
                noindex: $page > 1 || $coupons->total() < Seo::MIN_OFFERS_INDEX,
                breadcrumbs: $crumbs,
            ),
            'category' => $category,
            'coupons' => $coupons,
            'stores' => Catalog::storesWithOffers()->whereIn('id', $storeIds)->sortByDesc(fn ($s) => Catalog::count($s))->take(12)->values(),
            'votes' => Catalog::votes(collect($coupons->items())->pluck('id')),
        ]);
    }

    public function tag(Tag $tag)
    {
        $coupons = $tag->coupons()->active()->with('store')->ranked()->paginate(self::PER_PAGE);
        $page = $coupons->currentPage();

        return view('tag', [
            'seo' => new Seo(
                title: "{$tag->name}: vouchere, reduceri și cupoane — ".Day::monthYear(),
                description: "Vouchere, reduceri și cupoane cu {$tag->name}: ".Seo::plural($coupons->total(), 'ofertă activă', 'oferte active').'.',
                canonical: $page > 1 ? $coupons->url($page) : route('tag', $tag),
                noindex: $page > 1 || $coupons->total() < Seo::MIN_OFFERS_INDEX,
                breadcrumbs: [[$tag->name, route('tag', $tag)]],
            ),
            'tag' => $tag,
            'coupons' => $coupons,
            'votes' => Catalog::votes(collect($coupons->items())->pluck('id')),
        ]);
    }

    public function deals()
    {
        $deals = Deal::active()->with('store')->latest('id')->paginate(self::PER_PAGE);
        $page = $deals->currentPage();

        return view('deals', [
            'seo' => new Seo(title: 'Deal-uri: produse la preț redus — '.Day::monthYear().' | '.Seo::site(), description: 'Produse cu preț redus în magazinele online din România, verificate.', canonical: $page > 1 ? $deals->url($page) : route('deals'), noindex: $page > 1, breadcrumbs: [['Deal-uri', route('deals')]]),
            'deals' => $deals,
        ]);
    }

    public function top()
    {
        $top = Catalog::topOffers(24);
        $exclusive = Catalog::exclusive(24, $top->pluck('id'));
        $recent = Catalog::recent(24, $top->pluck('id')->merge($exclusive->pluck('id')));

        return view('top', [
            'seo' => new Seo(title: 'Top oferte '.Day::monthYear().' — vouchere, reduceri și cupoane | '.Seo::site(), description: 'Cele mai bune vouchere, reduceri și cupoane active acum.', breadcrumbs: [['Top oferte', route('top')]]),
            'top' => $top, 'exclusive' => $exclusive, 'recent' => $recent,
            'votes' => Catalog::votes($top->concat($exclusive)->concat($recent)->pluck('id')),
        ]);
    }

    public function search(Request $request)
    {
        $q = trim(mb_substr((string) $request->query('q', ''), 0, 60));
        $stores = collect();
        $cats = collect();
        if (mb_strlen($q) >= 2) {
            $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $q).'%';
            $stores = Catalog::storesWithOffers()->filter(fn ($s) => mb_stripos(\Illuminate\Support\Str::ascii($s->name), \Illuminate\Support\Str::ascii($q)) !== false)->values();
            $cats = Category::where('name', 'like', $like)->take(10)->get();
        }

        return response()->view('search', [
            'seo' => new Seo(title: ($q !== '' ? "Rezultate pentru „{$q}”" : 'Caută un magazin').' | '.Seo::site(), noindex: true, breadcrumbs: [['Caută', route('search')]]),
            'q' => $q, 'stores' => $stores, 'cats' => $cats,
        ])->header('X-Robots-Tag', 'noindex, follow');
    }

    public function page(Page $page)
    {
        return view('page', [
            'seo' => new Seo(title: ($page->meta_title ?: $page->title).' | '.Seo::site(), description: (string) $page->meta_description, breadcrumbs: [[$page->title, url('/'.$page->slug)]], type: 'article'),
            'page' => $page,
        ]);
    }

    /** JSON mic pentru sugestiile din căutare: [[nume, cale, oferte], …] — încărcat la primul focus. */
    public function searchIndex()
    {
        $rows = Catalog::storesWithOffers()->map(fn ($s) => [$s->name, '/magazin/'.$s->slug, Catalog::count($s)])->sortByDesc(2)->values();

        return response()->json($rows, 200, [], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
