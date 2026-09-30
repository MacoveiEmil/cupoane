<?php

declare(strict_types=1);

namespace Cupons\Core\Import;

use Cupons\Core\Support\Settings;

/**
 * 2Performant — conform https://doc.2performant.com/ (API https://api.2performant.com):
 *  - POST /users/sign_in              antet „user-key” + {user:{email,password}}; răspunsul conține user.unique_code
 *                                     (= aff_code); cererile următoare trimit antetele access-token, client, uid, token-type
 *  - GET  /affiliate/programs          filter[relation]=accepted, page, perpage → programs[] (id, name, main_url, unique_code)
 *  - GET  /affiliate/advertiser_promotions  filter[affrequest_status]=accepted, page, perpage → advertiser_promotions[]
 *  - Quicklink (deeplink): https://event.2performant.com/events/click?ad_type=quicklink&aff_code=…&unique=…&redirect_to=…
 */
final class TwoPerformant implements NetworkConnectorInterface
{
    private const API       = 'https://api.2performant.com';
    private const CLICK     = 'https://event.2performant.com/events/click';
    private const PER_PAGE  = 50;
    private const TRANSIENT = 'cupons_2p_auth';

    /** @var array<string,string>|null */
    private ?array $auth = null;

    public function id(): string
    {
        return '2performant';
    }

    public function label(): string
    {
        return '2Performant';
    }

    public function settings_fields(): array
    {
        return [
            'tp_email'    => ['label' => 'Email cont 2Performant', 'secret' => false, 'help' => ''],
            'tp_password' => ['label' => 'Parolă cont 2Performant', 'secret' => true, 'help' => ''],
            'tp_user_key' => ['label' => 'User key', 'secret' => true, 'help' => 'În contul de afiliat: Settings (network.2performant.com/affiliate/settings).'],
        ];
    }

    public function is_configured(): bool
    {
        foreach (array_keys($this->settings_fields()) as $key) {
            if ((string) Settings::get($key) === '') {
                return false;
            }
        }
        return true;
    }

    public function programs(?string $cursor): Page
    {
        $page = max(1, (int) ($cursor ?? 1));
        $data = $this->get('/affiliate/programs', [
            'page'    => $page,
            'perpage' => self::PER_PAGE,
            'filter'  => ['relation' => 'accepted'],
        ]);

        $items = [];
        foreach ((array) ($data['programs'] ?? []) as $p) {
            if (!is_array($p) || empty($p['id'])) {
                continue;
            }
            $url = (string) ($p['main_url'] ?? '');
            if ($url === '' && !empty($p['base_url'])) {
                $url = 'https://' . ltrim((string) $p['base_url'], '/');
            }
            $items[] = new Program($this->id(), (string) $p['id'], (string) ($p['name'] ?? ''), $url, (string) ($p['unique_code'] ?? ''));
        }

        return new Page($items, $this->next_page($data, $page, count($items)));
    }

    public function offers(?string $cursor, array $mapped): Page
    {
        $page = max(1, (int) ($cursor ?? 1));
        $data = $this->get('/affiliate/advertiser_promotions', [
            'page'    => $page,
            'perpage' => self::PER_PAGE,
            'filter'  => ['affrequest_status' => 'accepted'],
        ]);
        $aff = $this->login()['aff_code'];

        $items = [];
        foreach ((array) ($data['advertiser_promotions'] ?? []) as $p) {
            if (!is_array($p) || empty($p['id'])) {
                continue;
            }
            $program_id = (string) ($p['program']['id'] ?? '');
            $unique     = Programs::unique_code($this->id(), $program_id);
            $landing    = (string) ($p['landing_page_link'] ?? '');
            $link       = ($landing !== '' && $unique !== '' && $aff !== '') ? $this->quicklink($landing, $unique, $aff) : '';
            $terms      = trim((string) ($p['customer_description'] ?? ''));
            [$value, $type] = Offer::parse_value(($p['name'] ?? '') . ' ' . $terms);

            $items[] = new Offer(
                ext_id: $this->id() . ':' . $p['id'],
                program_id: $program_id,
                title: trim((string) ($p['name'] ?? '')),
                code: trim((string) ($p['coupon'] ?? '')),
                value: $value,
                value_type: $type,
                terms: $terms,
                link: $link,
                start: Offer::local_date((string) ($p['promotion_start'] ?? '')),
                end: Offer::local_date((string) ($p['promotion_end'] ?? '')),
            );
        }

        return new Page($items, $this->next_page($data, $page, count($items)));
    }

    public function deeplink(string $url, string $program_id): string
    {
        $unique = Programs::unique_code($this->id(), $program_id);
        if ($unique === '') {
            throw new ImportException('2Performant: nu cunosc codul unic al programului. Rulează întâi „Importă acum” pentru 2Performant.');
        }
        $aff = $this->login()['aff_code'];
        if ($aff === '') {
            throw new ImportException('2Performant: contul nu a returnat codul de afiliat (unique_code).');
        }
        return $this->quicklink($url, $unique, $aff);
    }

    private function quicklink(string $url, string $unique, string $aff): string
    {
        return self::CLICK . '?' . http_build_query([
            'ad_type'     => 'quicklink',
            'aff_code'    => $aff,
            'unique'      => $unique,
            'redirect_to' => $url,
        ], '', '&', PHP_QUERY_RFC3986);
    }

    /** @return array<string,string> */
    private function login(bool $force = false): array
    {
        if (!$force && $this->auth) {
            return $this->auth;
        }
        $cached = $force ? false : get_transient(self::TRANSIENT);
        if (is_array($cached) && !empty($cached['access-token'])) {
            return $this->auth = $cached;
        }
        if (!$this->is_configured()) {
            throw new ImportException('2Performant: completează email, parolă și user key în Cupoane → Setări.');
        }

        $response = wp_remote_post(self::API . '/users/sign_in', [
            'timeout' => Http::TIMEOUT,
            'headers' => [
                'Content-Type' => 'application/json',
                'Accept'       => 'application/json',
                'user-key'     => (string) Settings::get('tp_user_key'),
            ],
            'body' => wp_json_encode(['user' => [
                'email'    => (string) Settings::get('tp_email'),
                'password' => (string) Settings::get('tp_password'),
            ]]),
        ]);
        $data = Http::json($response, '2Performant');

        $auth = [
            'access-token' => (string) wp_remote_retrieve_header($response, 'access-token'),
            'client'       => (string) wp_remote_retrieve_header($response, 'client'),
            'uid'          => (string) wp_remote_retrieve_header($response, 'uid'),
            'token-type'   => (string) (wp_remote_retrieve_header($response, 'token-type') ?: 'Bearer'),
            'aff_code'     => (string) ($data['user']['unique_code'] ?? ''),
        ];
        if ($auth['access-token'] === '' || $auth['client'] === '' || $auth['uid'] === '') {
            throw new ImportException('2Performant: autentificarea nu a returnat antetele access-token / client / uid.');
        }

        set_transient(self::TRANSIENT, $auth, 20 * MINUTE_IN_SECONDS);
        return $this->auth = $auth;
    }

    /** @return array<mixed> */
    private function get(string $path, array $query, bool $retried = false): array
    {
        $auth     = $this->login();
        $response = wp_remote_get(self::API . $path . '?' . http_build_query($query), [
            'timeout' => Http::TIMEOUT,
            'headers' => [
                'Accept'       => 'application/json',
                'access-token' => $auth['access-token'],
                'client'       => $auth['client'],
                'uid'          => $auth['uid'],
                'token-type'   => $auth['token-type'],
            ],
        ]);

        if (!$retried && (int) wp_remote_retrieve_response_code($response) === 401) {
            delete_transient(self::TRANSIENT);
            $this->auth = null;
            $this->login(true);
            return $this->get($path, $query, true);
        }

        $data = Http::json($response, '2Performant');

        // Dacă serverul rotește token-ul, păstrăm varianta nouă.
        $new = (string) wp_remote_retrieve_header($response, 'access-token');
        if ($new !== '' && $new !== $auth['access-token']) {
            $auth['access-token'] = $new;
            set_transient(self::TRANSIENT, $this->auth = $auth, 20 * MINUTE_IN_SECONDS);
        }
        return $data;
    }

    private function next_page(array $data, int $page, int $count): ?string
    {
        $pages = $data['metadata']['pagination']['pages'] ?? $data['pagination']['pages'] ?? null;
        if ($pages !== null) {
            return $page < (int) $pages ? (string) ($page + 1) : null;
        }
        return $count >= self::PER_PAGE ? (string) ($page + 1) : null;
    }
}
