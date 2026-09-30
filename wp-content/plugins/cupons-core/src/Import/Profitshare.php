<?php

declare(strict_types=1);

namespace Cupons\Core\Import;

use Cupons\Core\Support\Settings;

/**
 * Profitshare — conform https://doc.profitshare.com/affiliates/docs/ (API https://api.profitshare.ro):
 *  - Autentificare: antetele Date, X-PS-Client, X-PS-Accept: json, X-PS-Auth = HMAC-SHA1(API KEY) al șirului
 *    METODĂ + rută + „?” + query + „/” + API USER + Date   (ex.: „GETaffiliate-vouchers/?page=1/user…”)
 *  - GET  affiliate-advertisers/       → result[] (id, name, url)
 *  - GET  affiliate-vouchers/          advertiser_id, valid=1, page → result.results[] (name, type, code, discount,
 *                                      valability „Y-m-d - Y-m-d”, exclusivity, tracking_link), result.total_pages
 *  - POST affiliate-links/             [{name, url}] → result[].ps_url
 * Voucherele nu au ID în API: cheia de deduplicare e un hash din advertiser + tip + cod + nume.
 */
final class Profitshare implements NetworkConnectorInterface
{
    private const API = 'https://api.profitshare.ro/';

    public function id(): string
    {
        return 'profitshare';
    }

    public function label(): string
    {
        return 'Profitshare';
    }

    public function settings_fields(): array
    {
        return [
            'ps_api_user' => ['label' => 'API user Profitshare', 'secret' => false, 'help' => 'Din contul de afiliat Profitshare → API.'],
            'ps_api_key'  => ['label' => 'API key Profitshare', 'secret' => true, 'help' => ''],
        ];
    }

    public function is_configured(): bool
    {
        return (string) Settings::get('ps_api_user') !== '' && (string) Settings::get('ps_api_key') !== '';
    }

    public function programs(?string $cursor): Page
    {
        $data  = $this->request('GET', 'affiliate-advertisers/', []);
        $items = [];
        foreach ((array) ($data['result'] ?? []) as $a) {
            if (is_array($a) && !empty($a['id'])) {
                $items[] = new Program($this->id(), (string) $a['id'], (string) ($a['name'] ?? ''), (string) ($a['url'] ?? ''));
            }
        }
        return new Page($items, null); // lista nu e paginată
    }

    /** Cursor „i:pagina” peste lista programelor asociate (API-ul nu întoarce ID-ul magazinului în voucher). */
    public function offers(?string $cursor, array $mapped): Page
    {
        $ids = array_map('strval', array_keys($mapped));
        sort($ids);
        if (!$ids) {
            return new Page([], null);
        }

        [$i, $page] = array_map('intval', explode(':', $cursor ?? '0:1') + [0, 1]);
        if (!isset($ids[$i])) {
            return new Page([], null);
        }
        $adv  = $ids[$i];
        $data = $this->request('GET', 'affiliate-vouchers/', ['advertiser_id' => $adv, 'valid' => 1, 'page' => max(1, $page)]);
        $res  = (array) ($data['result'] ?? []);

        $items = [];
        foreach ((array) ($res['results'] ?? []) as $v) {
            if (!is_array($v)) {
                continue;
            }
            $type = (string) ($v['type'] ?? '');
            $code = $type === 'code' ? trim((string) ($v['code'] ?? '')) : '';
            $name = trim((string) ($v['name'] ?? ''));
            [$start, $end] = $this->valability((string) ($v['valability'] ?? ''));
            [$value, $vtype] = Offer::parse_value((string) ($v['discount'] ?? '') . ' ' . $name);

            $items[] = new Offer(
                ext_id: $this->id() . ':' . substr(md5($adv . '|' . $type . '|' . $code . '|' . $name), 0, 20),
                program_id: $adv,
                title: $name,
                code: $code,
                value: $value,
                value_type: $vtype,
                terms: '',
                link: (string) ($v['tracking_link'] ?? ''),
                start: $start,
                end: $end,
                exclusive: (int) ($v['exclusivity'] ?? 0) === 1,
            );
        }

        $total = (int) ($res['total_pages'] ?? 1);
        $next  = $page < $total ? $i . ':' . ($page + 1) : (isset($ids[$i + 1]) ? ($i + 1) . ':1' : null);
        return new Page($items, $next);
    }

    public function deeplink(string $url, string $program_id): string
    {
        $data = $this->request('POST', 'affiliate-links/', [], [[
            'name' => 'deal-' . substr(md5($url), 0, 8),
            'url'  => $url,
        ]]);
        $ps = (string) ($data['result'][0]['ps_url'] ?? '');
        if ($ps === '') {
            throw new ImportException('Profitshare: generatorul de linkuri nu a returnat ps_url.');
        }
        return $ps;
    }

    /** @return array{0:string,1:string} */
    private function valability(string $text): array
    {
        preg_match_all('/\d{4}-\d{2}-\d{2}/', $text, $m);
        $d = $m[0] ?? [];
        return [$d[0] ?? '', $d[1] ?? ''];
    }

    /** @return array<mixed> */
    private function request(string $method, string $route, array $query, ?array $body = null): array
    {
        if (!$this->is_configured()) {
            throw new ImportException('Profitshare: completează API user și API key în Cupoane → Setări.');
        }
        $user = (string) Settings::get('ps_api_user');
        $key  = (string) Settings::get('ps_api_key');
        $qs   = http_build_query($query);
        $date = gmdate('D, d M Y H:i:s T');
        $auth = hash_hmac('sha1', $method . $route . '?' . $qs . '/' . $user . $date, $key);

        $args = [
            'method'  => $method,
            'timeout' => Http::TIMEOUT,
            'headers' => [
                'Date'        => $date,
                'X-PS-Client' => $user,
                'X-PS-Accept' => 'json',
                'X-PS-Auth'   => $auth,
            ],
        ];
        if ($body !== null) {
            $args['headers']['Content-Type'] = 'application/x-www-form-urlencoded';
            $args['body'] = http_build_query($body);
        }

        return Http::json(wp_remote_request(self::API . $route . '?' . $qs, $args), 'Profitshare');
    }
}
