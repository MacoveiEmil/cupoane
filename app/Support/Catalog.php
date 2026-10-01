<?php

namespace App\Support;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Deal;
use App\Models\Store;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Toate interogările site-ului public, într-un singur loc (regulile de „activ” și ordinea sunt aici).
 * Memorate per request; paginile întregi sunt oricum în PageCache.
 */
final class Catalog
{
    private static array $memo = [];

    /** Golește memoria per request (teste, procese de lungă durată). */
    public static function reset(): void
    {
        self::$memo = [];
    }

    /** @return array<int,int> store_id => oferte active */
    public static function storeCounts(): array
    {
        return self::$memo['counts'] ??= Coupon::active()->selectRaw('store_id, COUNT(*) c')->groupBy('store_id')->pluck('c', 'store_id')->map(fn ($c) => (int) $c)->all();
    }

    public static function count(Store $s): int
    {
        return self::storeCounts()[$s->id] ?? 0;
    }

    /** Doar magazinele cu oferte active (cele goale nu apar în liste). */
    public static function storesWithOffers(): Collection
    {
        return self::$memo['stores'] ??= Store::whereIn('id', array_keys(self::storeCounts()))->orderBy('name')->get();
    }

    /**
     * Indexul A–Z: magazinele cu oferte + cele fără oferte dar indexabile (text „Despre” / forțate din admin),
     * ca acestea să aibă măcar un link intern. Pe homepage, footer, căutare și „similare” rămân doar cele cu oferte.
     */
    public static function storesIndexable(): Collection
    {
        return self::$memo['indexable'] ??= Store::orderBy('name')->get()->filter(fn (Store $s) => $s->indexable(self::count($s)))->values();
    }

    /** Ultima modificare reală a ofertelor (pentru lastmod în sitemap). */
    public static function lastChange(?array $storeIds = null): ?\Carbon\CarbonInterface
    {
        $q = Coupon::withTrashed();
        if ($storeIds !== null) {
            $q->whereIn('store_id', $storeIds);
        }
        $max = $q->max('updated_at');

        return $max ? \Illuminate\Support\Carbon::parse($max) : null;
    }

    /** Magazine populare: ordinea din admin (home_order), completate cu cele mai multe oferte. */
    public static function popular(int $limit): Collection
    {
        $stores = self::storesWithOffers();
        $ordered = $stores->where('home_order', '>', 0)->sortBy('home_order')->values();
        $rest = $stores->where('home_order', 0)->sortByDesc(fn ($s) => self::count($s))->values();

        return $ordered->concat($rest)->take($limit)->values();
    }

    /** Cea mai bună reducere activă a unui magazin: „până la −40%”, „până la 50 lei”, „Transport gratuit”. */
    public static function bestDeal(Store $s): string
    {
        $c = Coupon::active()->where('store_id', $s->id)->get(['value', 'value_type']);
        $pct = (float) $c->where('value_type', 'procent')->max('value');
        $lei = (float) $c->where('value_type', 'lei')->max('value');
        $fmt = fn ($v) => str_replace('.', ',', rtrim(rtrim(number_format($v, 2, '.', ''), '0'), '.'));

        return match (true) {
            $pct > 0 => 'până la −'.$fmt($pct).'%',
            $lei > 0 => 'până la '.$fmt($lei).' lei',
            $c->contains('value_type', 'transport') => 'Transport gratuit',
            default => '',
        };
    }

    /** Top: întâi ofertele marcate „Top” în admin, apoi cele mai mari reduceri (procent, apoi lei). */
    public static function topOffers(int $limit, ?Collection $exclude = null): Collection
    {
        $exclude ??= collect();
        $manual = Coupon::active()->with('store')->where('top', true)->whereNotIn('id', $exclude)->latest('updated_at')->take($limit)->get();
        if ($manual->count() >= $limit) {
            return $manual;
        }
        $more = Coupon::active()->with('store')->whereNotIn('id', $exclude->merge($manual->pluck('id')))
            ->whereIn('value_type', ['procent', 'lei'])->where('value', '>', 0)
            ->orderByRaw("CASE WHEN value_type = 'procent' THEN 0 ELSE 1 END")->orderByDesc('value')
            ->take($limit - $manual->count())->get();

        return $manual->concat($more);
    }

    public static function exclusive(int $limit, Collection $exclude): Collection
    {
        return Coupon::active()->with('store')->where('exclusive', true)->whereNotIn('id', $exclude)->latest('id')->take($limit)->get();
    }

    public static function recent(int $limit, Collection $exclude): Collection
    {
        return Coupon::active()->with('store')->whereNotIn('id', $exclude)->latest('id')->take($limit)->get();
    }

    public static function deals(int $limit): Collection
    {
        return Deal::active()->with('store')->latest('id')->take($limit)->get();
    }

    /** Categoriile de nivel 1 cu numărul de magazine și oferte active (inclusiv subcategorii). */
    public static function categoryTree(bool $onlyWithOffers = false): Collection
    {
        $counts = self::storeCounts();
        $pivot = DB::table('category_store')->get()->groupBy('category_id')->map(fn ($rows) => $rows->pluck('store_id')->all());
        $stat = function (Category $c) use ($counts, $pivot) {
            $ids = collect($pivot[$c->id] ?? []);
            foreach ($c->children as $ch) {
                $ids = $ids->merge($pivot[$ch->id] ?? []);
            }
            $ids = $ids->unique()->filter(fn ($id) => ($counts[$id] ?? 0) > 0);

            return ['stores' => $ids->count(), 'offers' => $ids->sum(fn ($id) => $counts[$id]), 'ids' => $ids->values()->all()];
        };

        return Category::with('children')->whereNull('parent_id')->orderBy('sort')->orderBy('name')->get()
            ->map(fn ($c) => ['category' => $c] + $stat($c) + ['children' => $c->children->map(fn ($ch) => ['category' => $ch] + $stat($ch))])
            ->when($onlyWithOffers, fn ($col) => $col->where('offers', '>', 0))
            ->values();
    }

    public static function similar(Store $store, int $limit): Collection
    {
        $catIds = $store->categories->pluck('id');
        if ($catIds->isEmpty()) {
            return collect();
        }
        $ids = DB::table('category_store')->whereIn('category_id', $catIds)->where('store_id', '!=', $store->id)->pluck('store_id');

        return self::storesWithOffers()->whereIn('id', $ids)->sortByDesc(fn ($s) => self::count($s))->take($limit)->values();
    }

    /** De câte ori a fost folosit fiecare cupon (click-uri pe „Arată codul” / „Vezi oferta”, fără roboți). @return array<int,int> */
    public static function uses(Collection $ids): array
    {
        if ($ids->isEmpty()) {
            return [];
        }

        return DB::table('clicks')->where('object_type', 1)->whereIn('object_id', $ids)
            ->selectRaw('object_id, SUM(count) c')->groupBy('object_id')->pluck('c', 'object_id')->map(fn ($c) => (int) $c)->all();
    }

    /** Voturile (da/nu) și folosirile, pentru o listă de cupoane. @return array<int, array{yes:int,no:int,total:int,rate:?int,uses?:int}> */
    public static function votes(Collection $ids): array
    {
        if ($ids->isEmpty()) {
            return [];
        }
        $min = max(1, (int) Settings::get('vote_min'));
        $out = [];
        foreach (DB::table('votes')->whereIn('coupon_id', $ids)->selectRaw('coupon_id, SUM(yes) y, SUM(no) n')->groupBy('coupon_id')->get() as $r) {
            $t = (int) $r->y + (int) $r->n;
            $out[$r->coupon_id] = ['yes' => (int) $r->y, 'no' => (int) $r->n, 'total' => $t, 'rate' => $t >= $min ? (int) round($r->y / $t * 100) : null];
        }
        // „Folosit de X ori” vine împreună cu voturile (aceleași pagini, o singură trecere prin date).
        foreach (self::uses($ids) as $id => $n) {
            $out[$id] = ($out[$id] ?? ['yes' => 0, 'no' => 0, 'total' => 0, 'rate' => null]) + ['uses' => $n];
        }

        return $out;
    }
}
