<?php

namespace App\Import;

use App\Models\Category;
use App\Models\Coupon;
use App\Models\Program;
use App\Models\Store;
use App\Models\Tag;
use App\Support\Day;
use App\Support\PageCache;
use App\Support\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Importul pe pași scurți (compatibil cu shared hosting): programe → oferte → logo-uri.
 * Ofertele noi intră ca CIORNĂ; cele existente (după ID extern) își actualizează doar datele;
 * cele șterse (în coș) nu sunt readuse; cele expirate nu se importă.
 */
final class Importer
{
    public const BUDGET = 15;

    public static function connectors(): array
    {
        return ['2performant' => new TwoPerformant()];
    }

    public static function enabled(string $id): bool
    {
        return match ($id) {
            '2performant' => (bool) Settings::get('tp_enabled', true),
            default => false,
        };
    }

    public static function state(string $network): array
    {
        return Cache::get('import:'.$network, self::fresh('idle'));
    }

    private static function fresh(string $phase): array
    {
        return [
            'phase' => $phase, 'cursor' => null, 'error' => '', 'started' => $phase === 'idle' ? null : now()->toIso8601String(), 'finished' => null,
            'stats' => ['programs' => 0, 'stores_created' => 0, 'created' => 0, 'updated' => 0, 'unchanged' => 0, 'unmapped' => 0, 'expired' => 0, 'skipped' => 0],
        ];
    }

    public static function running(array $s): bool
    {
        return in_array($s['phase'], ['programs', 'offers', 'logos'], true);
    }

    public static function start(string $network): array
    {
        Cache::forever('import:'.$network, $s = self::fresh('programs'));

        return $s;
    }

    /** Un pas de maxim BUDGET secunde. Reluabil după eroare. */
    public static function step(string $network, int $budget = self::BUDGET): array
    {
        $connector = self::connectors()[$network] ?? null;
        $state = self::state($network);
        if (! $connector || ! self::enabled($network) || ! self::running($state)) {
            return $state;
        }
        $lock = Cache::lock('import-lock:'.$network, 120);
        if (! $lock->get()) {
            return $state + ['busy' => true];
        }

        $t0 = microtime(true);
        @set_time_limit($budget + 30);
        try {
            $state['error'] = '';
            PageCache::batch(function () use (&$state, $connector, $network, $t0, $budget) {
                while (self::running($state) && microtime(true) - $t0 < $budget) {
                    if ($state['phase'] === 'programs') {
                        [$items, $next] = $connector->programs($state['cursor']);
                        foreach ($items as $p) {
                            if ($p->accepted !== false) {
                                $state['stats']['programs']++;
                                if (self::upsertProgram($p)) {
                                    $state['stats']['stores_created']++;
                                }
                            }
                        }
                        $state['cursor'] = $next;
                        if ($next === null) {
                            self::syncAffiliateLinks(); // magazinele acceptate primesc linkul de afiliere
                        }
                        $state['phase'] = $next === null ? 'offers' : 'programs';
                    } elseif ($state['phase'] === 'offers') {
                        [$items, $next] = $connector->offers($state['cursor']);
                        foreach ($items as $o) {
                            $state['stats'][self::saveOffer($o, $network)]++;
                        }
                        $state['cursor'] = $next;
                        $state['phase'] = $next === null ? 'logos' : 'offers';
                    } else {
                        if (self::fetchLogos($t0 + $budget) === 0) {
                            $state['phase'] = 'done';
                            $state['finished'] = now()->toIso8601String();
                        }
                    }
                }
            });
        } catch (ImportException $e) {
            $state['error'] = $e->getMessage();
        } finally {
            Cache::forever('import:'.$network, $state);
            $lock->release();
        }

        return $state;
    }

    public static function run(string $network, ?callable $tick = null): array
    {
        $s = self::start($network);
        while (self::running($s) && $s['error'] === '' && empty($s['busy'])) {
            $s = self::step($network, 25);
            $tick && $tick($s);
        }

        return $s;
    }

    /** Salvează programul; creează magazinul dacă e acceptat și nu există. @return bool magazin nou creat */
    private static function upsertProgram(ProgramData $p): bool
    {
        $program = Program::updateOrCreate(
            ['network' => $p->network, 'program_id' => $p->id],
            ['name' => mb_substr($p->name, 0, 190), 'site_url' => mb_substr($p->url, 0, 255), 'accepted' => $p->accepted, 'last_seen' => now()]
                + ($p->uniqueCode !== '' ? ['unique_code' => $p->uniqueCode] : [])
        );
        if ($program->store_id) {
            return false;
        }

        // Asociere după domeniu, apoi creare automată.
        $store = self::matchByDomain($p->url) ?? Store::where('network', $p->network)->where('program_id', $p->id)->first();
        $created = false;
        if (! $store && $p->accepted === true && Settings::get('auto_stores')) {
            $name = trim(preg_replace('/\s+/u', ' ', strip_tags($p->name)) ?? '');
            $name = $name === mb_strtolower($name) ? Str::ucfirst($name) : $name; // „answear.ro” → „Answear.ro”; „eMAG.ro” rămâne
            $store = Store::create([
                'name' => $name ?: (string) parse_url($p->url, PHP_URL_HOST),
                'url' => $p->url,
                'logo_src' => $p->logo ?: null,
            ]);
            if ($p->category !== '') {
                $cat = Category::firstOrCreate(['slug' => Str::slug($p->category)], ['name' => $p->category]);
                $store->categories()->syncWithoutDetaching([$cat->id]);
            }
            $created = true;
        }
        if ($store) {
            $store->update(['network' => $p->network, 'program_id' => $p->id]);
            $program->update(['store_id' => $store->id]);
        }

        return $created;
    }

    /**
     * Magazinele cu program ACCEPTAT primesc linkul de afiliere (quicklink spre site-ul magazinului), folosit de
     * „Mergi la magazin”, /go/{magazin} și de ofertele fără link propriu. Un link pus manual în admin nu e suprascris.
     * @return int câte magazine au primit link
     */
    public static function syncAffiliateLinks(): int
    {
        $done = 0;
        $programs = Program::where('accepted', true)->whereNotNull('store_id')->whereNotNull('unique_code')->where('unique_code', '!=', '')->get();
        foreach ($programs as $program) {
            $store = Store::find($program->store_id);
            $connector = self::connectors()[$program->network] ?? null;
            if (! $store || ! $connector || trim((string) $store->affiliate_url) !== '' || trim((string) $store->url) === '') {
                continue;
            }
            $store->update(['affiliate_url' => $connector->deeplink($store->url, (string) $program->program_id)]);
            $done++;
        }

        return $done;
    }

    private static function matchByDomain(string $url): ?Store
    {
        $host = self::host($url);
        if ($host === '') {
            return null;
        }
        $found = Store::whereNotNull('url')->whereNull('program_id')->get()->filter(fn ($s) => self::host((string) $s->url) === $host);

        return $found->count() === 1 ? $found->first() : null;
    }

    private static function host(string $url): string
    {
        $url = $url !== '' && ! str_contains($url, '://') ? 'https://'.$url : $url;

        return (string) preg_replace('/^www\./', '', strtolower((string) parse_url($url, PHP_URL_HOST)));
    }

    /** @return string cheia statisticii */
    private static function saveOffer(OfferData $o, string $network): string
    {
        $store = Store::where('network', $network)->where('program_id', $o->programId)->first();
        if (! $store) {
            return 'unmapped';
        }
        if ($o->end !== '' && $o->end < Day::today()->toDateString()) {
            return 'expired';
        }
        if ($o->title === '') {
            return 'skipped';
        }

        $fields = [
            'code' => $o->code ?: null,
            'value' => $o->value,
            'value_type' => $o->valueType,
            'upto' => $o->upto,
            'terms' => $o->terms ?: null,
            'affiliate_url' => $o->link ?: null,
            'starts_on' => $o->start ?: null,
            'expires_on' => $o->end ?: null,
        ];

        $existing = Coupon::withTrashed()->where('ext_id', $o->extId)->first();
        if ($existing) {
            if ($existing->trashed()) {
                return 'skipped'; // ștearsă sau expirată intenționat: nu o readucem
            }
            $existing->fill($fields);
            if (! $existing->isDirty()) {
                return 'unchanged';
            }
            $existing->save();

            return 'updated';
        }

        $coupon = Coupon::create($fields + [
            'store_id' => $store->id,
            'title' => mb_substr($o->title, 0, 250),
            'status' => 'draft',
            'ext_id' => $o->extId,
        ]);
        if ($o->valueType === 'transport' && ($tag = Tag::where('slug', 'transport-gratuit')->first())) {
            $coupon->tags()->syncWithoutDetaching([$tag->id]);
        }

        return 'created';
    }

    /** Descarcă logo-urile magazinelor create automat și le salvează WebP. @return int câte au rămas */
    public static function fetchLogos(float $deadline): int
    {
        $stores = Store::whereNotNull('logo_src')->limit(50)->get();
        foreach ($stores as $i => $store) {
            if (microtime(true) > $deadline) {
                return $stores->count() - $i;
            }
            $src = $store->logo_src;
            $store->logo_src = null; // o singură încercare: un eșec nu blochează importul
            try {
                $res = Http::timeout(15)->get($src);
                if ($res->successful() && ($img = @imagecreatefromstring($res->body()))) {
                    $store->logo_path = self::saveWebp($img, 'logos/'.$store->slug);
                }
            } catch (\Throwable) {
                // ignorat: magazinul rămâne cu inițiala ca logo
            }
            $store->saveQuietly();
        }

        return Store::whereNotNull('logo_src')->count();
    }

    /** Redimensionează (implicit max 320×160) și salvează WebP (fundal transparent păstrat). */
    public static function saveWebp(\GdImage $img, string $base, int $maxW = 320, int $maxH = 160): string
    {
        $w = imagesx($img);
        $h = imagesy($img);
        $scale = min(1, $maxW / $w, $maxH / $h);
        $nw = max(1, (int) round($w * $scale));
        $nh = max(1, (int) round($h * $scale));
        $out = imagecreatetruecolor($nw, $nh);
        imagealphablending($out, false);
        imagesavealpha($out, true);
        imagefill($out, 0, 0, imagecolorallocatealpha($out, 0, 0, 0, 127));
        imagecopyresampled($out, $img, 0, 0, 0, 0, $nw, $nh, $w, $h);

        ob_start();
        imagewebp($out, null, 85);
        $path = $base.'.webp';
        Storage::disk('public')->put($path, (string) ob_get_clean());

        return $path;
    }
}
