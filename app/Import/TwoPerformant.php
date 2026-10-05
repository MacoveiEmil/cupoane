<?php

namespace App\Import;

use App\Models\Program;
use App\Support\Settings;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

/**
 * 2Performant — conform https://doc.2performant.com/ (API https://api.2performant.com), verificat live:
 *  - POST /users/sign_in  antet „user-key” + {user:{email,password}} → antetele access-token/client/uid/token-type
 *                         + user.unique_code (= aff_code pentru quicklink)
 *  - GET  /affiliate/programs?filter[relation]=accepted          → programs[], metadata.pagination
 *  - GET  /affiliate/advertiser_promotions?filter[affrequest_status]=accepted → advertiser_promotions[], pagination
 *  - Quicklink: https://event.2performant.com/events/click?ad_type=quicklink&aff_code=…&unique=…&redirect_to=…
 */
final class TwoPerformant implements NetworkConnector
{
    private const API = 'https://api.2performant.com';
    private const CLICK = 'https://event.2performant.com/events/click';
    private const PER_PAGE = 50;
    private const AUTH_CACHE = 'tp_auth';

    private ?array $auth = null;

    public function id(): string
    {
        return '2performant';
    }

    public function label(): string
    {
        return '2Performant';
    }

    public function isConfigured(): bool
    {
        return Settings::get('tp_email') !== '' && Settings::get('tp_password') !== '' && Settings::get('tp_user_key') !== '';
    }

    public function programs(?string $cursor): array
    {
        $page = max(1, (int) ($cursor ?? 1));
        $data = $this->get('/affiliate/programs', ['page' => $page, 'perpage' => self::PER_PAGE, 'filter' => ['relation' => 'accepted']]);
        $items = array_map(fn ($p) => $this->program($p, 'accepted'), array_filter((array) ($data['programs'] ?? []), fn ($p) => ! empty($p['id'])));

        return [array_values($items), $this->nextPage($data, $page, count($items))];
    }

    /** Cursor „relatie:pagina”: întâi cererile în așteptare, apoi programele neaplicate. Doar România, fără categoria Adult. */
    public function directory(?string $cursor): array
    {
        [$relation, $page] = explode(':', $cursor ?? 'pending:1') + [1 => 1];
        $page = max(1, (int) $page);
        $data = $this->get('/affiliate/programs', ['page' => $page, 'perpage' => self::PER_PAGE, 'filter' => ['relation' => $relation, 'country' => 'Romania']]);
        $raw = array_filter((array) ($data['programs'] ?? []), fn ($p) => ! empty($p['id']));
        $items = [];
        foreach ($raw as $p) {
            if (! in_array(trim((string) ($p['category']['name'] ?? '')), Categories::SKIP, true)) {
                $items[] = $this->program($p, $relation);
            }
        }
        $next = $this->nextPage($data, $page, count($raw));
        if ($next === null && $relation === 'pending') {
            return [$items, 'not_applied:1'];
        }

        return [$items, $next === null ? null : $relation.':'.$next];
    }

    private function program(array $p, string $relation): ProgramData
    {
        $url = (string) ($p['main_url'] ?? '') ?: (! empty($p['base_url']) ? 'https://'.ltrim($p['base_url'], '/') : '');
        // Sursa de trafic „Coupons/Vouchers” (id 17): usage = yes și activă → magazinul acceptă site-uri ca al nostru.
        $source = collect((array) ($p['advertiser_traffic_sources'] ?? []))
            ->first(fn ($s) => is_array($s) && ((int) ($s['traffic_source_id'] ?? 0) === 17 || stripos((string) ($s['name'] ?? ''), 'coupon') !== false));
        $allows = match (true) {
            is_array($source) => ($source['usage'] ?? '') === 'yes' && ($source['status'] ?? 'active') !== 'deleted',
            isset($p['most_used_traffic_sources']['Coupons/Vouchers']) => $p['most_used_traffic_sources']['Coupons/Vouchers'] === 'yes',
            default => null,
        };

        return new ProgramData(
            network: $this->id(),
            id: (string) $p['id'],
            name: trim((string) ($p['name'] ?? '')),
            url: $url,
            uniqueCode: (string) ($p['unique_code'] ?? ''),
            category: trim((string) ($p['category']['name'] ?? '')),
            logo: (string) ($p['logo_path'] ?? ''),
            accepted: $relation === 'accepted' ? ($p['status'] ?? 'active') === 'active' : false,
            relation: $relation,
            allowsCoupons: $allows,
        );
    }

    public function offers(?string $cursor): array
    {
        $page = max(1, (int) ($cursor ?? 1));
        // Toate promoțiile din rețea, inclusiv de la magazinele la care nu suntem (încă) acceptați:
        // pentru acestea linkul duce direct la magazin, iar la acceptare trece automat pe afiliere.
        $data = $this->get('/affiliate/advertiser_promotions', ['page' => $page, 'perpage' => self::PER_PAGE]);
        $aff = $this->login()['aff_code'];
        $items = [];
        foreach ((array) ($data['advertiser_promotions'] ?? []) as $p) {
            if (empty($p['id'])) {
                continue;
            }
            $programId = (string) ($p['program']['id'] ?? '');
            $program = Program::where('network', $this->id())->where('program_id', $programId)->first(['unique_code', 'accepted']);
            $unique = $program && $program->accepted ? (string) $program->unique_code : '';
            $landing = (string) ($p['landing_page_link'] ?? '');
            $terms = trim((string) ($p['customer_description'] ?? ''));
            [$value, $type, $upto] = OfferData::parseValue(($p['name'] ?? '').' '.$terms);

            $items[] = new OfferData(
                extId: $this->id().':'.$p['id'],
                programId: $programId,
                title: trim((string) ($p['name'] ?? '')),
                code: trim((string) ($p['coupon'] ?? '')),
                value: $value,
                valueType: $type,
                upto: $upto,
                terms: $terms,
                link: ($landing !== '' && $unique !== '' && $aff !== '') ? $this->quicklink($landing, $unique, $aff) : $landing,
                start: OfferData::localDate($p['promotion_start'] ?? ''),
                end: OfferData::localDate($p['promotion_end'] ?? ''),
            );
        }

        return [$items, $this->nextPage($data, $page, count($items))];
    }

    public function deeplink(string $url, string $programId): string
    {
        $unique = (string) Program::where('network', $this->id())->where('program_id', $programId)->value('unique_code');
        if ($unique === '') {
            throw new ImportException('2Performant: nu cunosc codul unic al programului. Rulează întâi importul.');
        }

        return $this->quicklink($url, $unique, $this->login()['aff_code']);
    }

    private function quicklink(string $url, string $unique, string $aff): string
    {
        return self::CLICK.'?'.http_build_query(['ad_type' => 'quicklink', 'aff_code' => $aff, 'unique' => $unique, 'redirect_to' => $url], '', '&', PHP_QUERY_RFC3986);
    }

    private function login(bool $force = false): array
    {
        if (! $force && $this->auth) {
            return $this->auth;
        }
        if (! $force && ($cached = Cache::get(self::AUTH_CACHE))) {
            return $this->auth = $cached;
        }
        if (! $this->isConfigured()) {
            throw new ImportException('2Performant: completează email, parolă și user key în Admin → Setări.');
        }

        $res = Http::timeout(20)->acceptJson()
            ->withHeaders(['user-key' => Settings::get('tp_user_key')])
            ->post(self::API.'/users/sign_in', ['user' => ['email' => Settings::get('tp_email'), 'password' => Settings::get('tp_password')]]);
        $data = $this->json($res);

        $auth = [
            'access-token' => (string) $res->header('access-token'),
            'client' => (string) $res->header('client'),
            'uid' => (string) $res->header('uid'),
            'token-type' => (string) ($res->header('token-type') ?: 'Bearer'),
            'aff_code' => (string) ($data['user']['unique_code'] ?? ''),
        ];
        if ($auth['access-token'] === '' || $auth['client'] === '' || $auth['uid'] === '') {
            throw new ImportException('2Performant: autentificarea nu a returnat antetele access-token / client / uid.');
        }
        Cache::put(self::AUTH_CACHE, $auth, now()->addMinutes(20));

        return $this->auth = $auth;
    }

    private function get(string $path, array $query, bool $retried = false): array
    {
        $auth = $this->login();
        $res = Http::timeout(20)->acceptJson()
            ->withHeaders(array_intersect_key($auth, array_flip(['access-token', 'client', 'uid', 'token-type'])))
            ->get(self::API.$path, $query);

        if ($res->status() === 401 && ! $retried) {
            Cache::forget(self::AUTH_CACHE);
            $this->auth = null;
            $this->login(true);

            return $this->get($path, $query, true);
        }
        $data = $this->json($res);

        if (($new = (string) $res->header('access-token')) !== '' && $new !== $auth['access-token']) {
            $auth['access-token'] = $new;
            Cache::put(self::AUTH_CACHE, $this->auth = $auth, now()->addMinutes(20));
        }

        return $data;
    }

    private function json(Response $res): array
    {
        if ($res->status() === 429) {
            throw new ImportException('2Performant: limita de requesturi atinsă (100/minut). Reîncearcă peste câteva minute.');
        }
        if (in_array($res->status(), [401, 403], true)) {
            throw new ImportException('2Performant: acces refuzat. Verifică datele de acces din Setări.');
        }
        if (! $res->successful() || ! is_array($res->json())) {
            throw new ImportException('2Performant: răspuns neașteptat (HTTP '.$res->status().'): '.mb_substr(strip_tags($res->body()), 0, 200));
        }

        return $res->json();
    }

    private function nextPage(array $data, int $page, int $count): ?string
    {
        $pages = $data['metadata']['pagination']['pages'] ?? $data['pagination']['pages'] ?? null;
        if ($pages !== null) {
            return $page < (int) $pages ? (string) ($page + 1) : null;
        }

        return $count >= self::PER_PAGE ? (string) ($page + 1) : null;
    }
}
