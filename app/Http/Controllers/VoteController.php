<?php

namespace App\Http\Controllers;

use App\Models\Coupon;
use App\Support\Catalog;
use App\Support\Day;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * POST /api/vote {id, v: 1|0} — „A funcționat?”.
 * Paginile sunt complet cacheabile, deci fără token CSRF (ar expira în HTML-ul din cache). Protecție:
 * same-origin (Origin/Referer), cupon activ, cookie funcțional „cv”, un vot per cupon per IP per zi (HMAC, IP-ul nu se stochează).
 */
class VoteController extends Controller
{
    public function __invoke(Request $request)
    {
        $host = parse_url(url('/'), PHP_URL_HOST);
        $source = (string) ($request->header('Origin') ?: $request->header('Referer'));
        if ($source === '' || strcasecmp((string) parse_url($source, PHP_URL_HOST), (string) $host) !== 0) {
            return response()->json(['ok' => false, 'reason' => 'origin'], 403);
        }

        $data = $request->validate(['id' => 'required|integer|min:1', 'v' => 'required|in:0,1']);
        $coupon = Coupon::find($data['id']);
        if (! $coupon || ! $coupon->isActive()) {
            return response()->json(['ok' => false, 'reason' => 'inactive'], 404);
        }

        $voted = array_filter(array_map('intval', explode('.', (string) $request->cookie('cv', ''))));
        $summary = fn () => Catalog::votes(collect([$coupon->id]))[$coupon->id] ?? ['yes' => 0, 'no' => 0, 'total' => 0, 'rate' => null];
        if (in_array($coupon->id, $voted, true)) {
            return response()->json(['ok' => false, 'reason' => 'voted'] + $summary(), 409);
        }

        // IP-ul real: în producție proxy-urile de încredere sunt doar Cloudflare (App\Http\Middleware\TrustProxies).
        $ip = (string) $request->ip();
        $hash = hash_hmac('sha256', $ip.'|'.$coupon->id.'|'.Day::today()->toDateString(), (string) config('app.key'));
        if (! DB::table('vote_guards')->insertOrIgnore(['hash' => $hash, 'created_at' => now()])) {
            return response()->json(['ok' => false, 'reason' => 'voted'] + $summary(), 429);
        }

        $col = $data['v'] == 1 ? 'yes' : 'no';
        DB::table('votes')->upsert(
            [['coupon_id' => $coupon->id, 'date' => Day::today()->toDateString(), 'yes' => $col === 'yes' ? 1 : 0, 'no' => $col === 'no' ? 1 : 0]],
            ['coupon_id', 'date'],
            [$col => DB::raw($col.' + 1')]
        );

        $voted[] = $coupon->id;

        return response()->json(['ok' => true] + $summary())
            ->header('Cache-Control', 'no-store')
            ->withCookie(cookie('cv', implode('.', array_slice($voted, -200)), 525600, '/', null, $request->secure(), false, false, 'Lax'));
    }
}
