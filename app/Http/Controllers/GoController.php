<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Models\Deal;
use App\Models\Store;
use App\Support\Day;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * /go/{id}     → cupon       /go/d{id} → deal       /go/{slug} → magazin
 * 302 la linkul de afiliere, click numărat (fără roboți), noindex, fără cache.
 * Oferta expirată / nepublicată → pagina magazinului (vizitatorul nu ajunge la o ofertă moartă).
 */
class GoController extends Controller
{
    public function __invoke(Request $request, string $key)
    {
        [$target, $type, $id, $fallback] = $this->resolve($key);

        if ($target !== '' && ! $this->isBot($request)) {
            DB::table('clicks')->upsert(
                [['object_type' => $type, 'object_id' => $id, 'date' => Day::today()->toDateString(), 'count' => 1]],
                ['object_type', 'object_id', 'date'],
                ['count' => DB::raw('count + 1')]
            );
        }

        return redirect()->away($target !== '' ? $target : $fallback, 302)->withHeaders([
            'X-Robots-Tag' => 'noindex, nofollow',
            'Cache-Control' => 'no-store, private',
            'X-LiteSpeed-Cache-Control' => 'no-cache',
        ]);
    }

    /** @return array{0:string,1:int,2:int,3:string} */
    private function resolve(string $key): array
    {
        $home = url('/');
        if (ctype_digit($key) && ($c = Coupon::with('store')->find((int) $key))) {
            $fallback = $c->store ? $c->store->url() : $home;

            return $c->isActive() ? [$this->safe($c->targetUrl()), 1, $c->id, $fallback] : ['', 0, 0, $fallback];
        }
        if (preg_match('/^d(\d+)$/', $key, $m) && ($d = Deal::with('store')->find((int) $m[1]))) {
            $fallback = $d->store ? $d->store->url() : $home;

            return $d->isActive() ? [$this->safe($d->targetUrl()), 2, $d->id, $fallback] : ['', 0, 0, $fallback];
        }
        if ($s = Store::where('slug', $key)->first()) {
            return [$this->safe($s->targetUrl()), 3, $s->id, $s->url()];
        }

        return ['', 0, 0, $home];
    }

    /** Validare structurală (fără DNS): http/https + host. */
    private function safe(string $url): string
    {
        $p = parse_url($url);

        return is_array($p) && in_array(strtolower($p['scheme'] ?? ''), ['http', 'https'], true) && ! empty($p['host']) ? $url : '';
    }

    private function isBot(Request $request): bool
    {
        $ua = (string) $request->userAgent();

        // Preîncărcările browserului (prefetch / prerender) nu sunt click-uri reale.
        $prefetch = (bool) preg_match('/prefetch|prerender/i', $request->header('Sec-Purpose', '').$request->header('Purpose', '').$request->header('X-Moz', ''));

        return $ua === '' || $request->isMethod('HEAD') || $prefetch
            || (bool) preg_match('/bot|crawl|spider|slurp|preview|facebookexternalhit|headless|lighthouse|curl|wget|python|httpclient|monitor/i', $ua);
    }
}
