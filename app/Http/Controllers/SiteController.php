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
        // Ordinea paginii: întâi prospețimea (cele mai noi), apoi cele mai mari reduceri, apoi magazinele.
        // 4 căsuțe pe secțiune: 2 × 2 pe mobil, un rând de 4 pe ecrane mai late (fără căsuțe rămase singure pe rând).
        $recent = Catalog::recent(4, collect());
        $top = Catalog::topOffers(4, $recent->pluck('id'));
        if ($top->isEmpty()) {
            $top = Catalog::topOffers(4); // inventar mic: mai bine o repetare decât o secțiune lipsă
        }
        $exclusive = Catalog::exclusive(6, $recent->pluck('id')->merge($top->pluck('id')));
        $counts = Catalog::storeCounts();
        $faqBlock = Settings::block('home_faq');
        $faq = Seo::faqHtml(self::faqItems($faqBlock));

        return view('home', [
            'seo' => new Seo(
                title: Seo::site().' — Vouchere, reduceri și cupoane verificate zilnic',
                description: 'Vouchere, reduceri și cupoane verificate pentru magazinele online din România. Coduri actualizate zilnic, fără oferte expirate.',
                faq: $faq,
            ),
            'faq' => $faq,
            'faqTitle' => $faqBlock['title'] ?? 'Întrebări frecvente',
            'popular' => Catalog::popular(10),
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
        $coupons = $store->activeCoupons()->with('tags')->ranked()->get()->each->setRelation('store', $store);
        $count = $coupons->count();
        $ll = Day::monthYear();
        $vars = ['magazin' => $store->name, 'data' => Day::long($store->updated_on ?? Day::today())];
        // Doar conținut propriu al magazinului (fără texte generale repetate pe toate paginile); gol = secțiunea nu apare.
        $faq = Seo::faqHtml($store->pairs('faq', 'q', 'a'));

        return view('store', [
            'seo' => new Seo(
                title: "Cod reducere {$store->name} {$ll} — vouchere, reduceri și cupoane",
                description: $count > 0
                    ? "Vouchere, reduceri și cupoane {$store->name} verificate: ".Seo::plural($count, 'ofertă activă', 'oferte active')." în {$ll}. Actualizat la ".Day::human($store->updated_on ?? Day::today()).'.'
                    : "Coduri de reducere {$store->name}: cum economisești la comenzi, transport, retur și reducerile obișnuite ale magazinului, plus magazine similare cu oferte active în {$ll}.",
                canonical: $store->url(),
                noindex: ! $store->indexable($count),
                breadcrumbs: [['Magazine', route('stores')], [$store->name, $store->url()]],
                faq: $faq,
            ),
            'store' => $store,
            'coupons' => $coupons,
            'deals' => $store->deals()->active()->latest('id')->take(6)->get(),
            // Fără categorii comune, pagina goală primește măcar magazinele populare (nu rămâne fundătură).
            'similar' => ($sim = Catalog::similar($store, 6))->isNotEmpty() || $count > 0 ? $sim : Catalog::popular(4)->where('id', '!=', $store->id)->values(),
            'votes' => Catalog::votes($coupons->pluck('id')),
            'h1' => Settings::text('store_h1', $vars),
            'guides' => [
                'howto' => ($h = $store->pairs('howto', 't', 'p')) ? ['title' => "Cum folosești codurile de reducere {$store->name}?", 'items' => $h] : null,
                'tips' => ($t = $store->pairs('tips', 't', 'p')) ? ['title' => "Cum economisești la {$store->name}", 'items' => $t] : null,
            ],
            'faq' => $faq,
            'faqTitle' => 'Întrebări frecvente despre codurile '.$store->name,
        ]);
    }

    /** Întrebările dintr-un bloc de text („Întrebare | Răspuns” pe fiecare rând). */
    private static function faqItems(?array $block): array
    {
        return collect($block['items'] ?? [])->filter(fn ($r) => $r[0] !== null)->values()->all();
    }

    public function stores()
    {
        $groups = Catalog::storesIndexable()->groupBy(function ($s) {
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
            'h1' => Settings::text('category_h1', ['categorie' => $category->name]),
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
        // Primele 3 oferte (în ordinea top → exclusive → recente) devin căsuțele mari de sus.
        $featured = $top->concat($exclusive)->concat($recent)->take(3);
        $skip = $featured->pluck('id');
        [$top, $exclusive, $recent] = [$top->whereNotIn('id', $skip), $exclusive->whereNotIn('id', $skip), $recent->whereNotIn('id', $skip)];

        return view('top', [
            'featured' => $featured,
            'seo' => new Seo(title: 'Top oferte '.Day::monthYear().' — vouchere, reduceri și cupoane | '.Seo::site(), description: 'Cele mai bune vouchere, reduceri și cupoane active acum.', breadcrumbs: [['Top oferte', route('top')]]),
            'top' => $top, 'exclusive' => $exclusive, 'recent' => $recent,
            'votes' => Catalog::votes($featured->concat($top)->concat($exclusive)->concat($recent)->pluck('id')),
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
