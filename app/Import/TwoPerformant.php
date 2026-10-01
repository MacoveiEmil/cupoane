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
        $items = [];
        foreach ((array) ($data['programs'] ?? []) as $p) {
            if (empty($p['id'])) {
                continue;
            }
            $url = (string) ($p['main_url'] ?? '') ?: (! empty($p['base_url']) ? 'https://'.ltrim($p['base_url'], '/') : '');
            $items[] = new ProgramData(
                network: $this->id(),
                id: (string) $p['id'],
                name: trim((string) ($p['name'] ?? '')),
                url: $url,
                uniqueCode: (string) ($p['unique_code'] ?? ''),
                category: trim((string) ($p['category']['name'] ?? '')),
                logo: (string) ($p['logo_path'] ?? ''),
                accepted: ($p['status'] ?? 'active') === 'active',
            );
        }

        return [$items, $this->nextPage($data, $page, count($items))];
    }

    public function offers(?string $cursor): array
    {
        $page = max(1, (int) ($cursor ?? 1));
        $data = $this->get('/affiliate/advertiser_promotions', ['page' => $page, 'perpage' => self::PER_PAGE, 'filter' => ['affrequest_status' => 'accepted']]);
        $aff = $this->login()['aff_code'];
        $items = [];
        foreach ((array) ($data['advertiser_promotions'] ?? []) as $p) {
            if (empty($p['id'])) {
                continue;
            }
            $programId = (string) ($p['program']['id'] ?? '');
            $unique = (string) Program::where('network', $this->id())->where('program_id', $programId)->value('unique_code');
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
                link: ($landing !== '' && $unique !== '' && $aff !== '') ? $this->quicklink($landing, $unique, $aff) : '',
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
