<?php

declare(strict_types=1);

namespace Cupons\Core\Rest;

use Cupons\Core\Keys;
use Cupons\Core\Query\Offers;
use Cupons\Core\Stats\Votes;
use Cupons\Core\Support\Request;

/**
 * POST /wp-json/cupons/v1/vote  {id: int, v: 1|0}
 *
 * Paginile sunt complet cacheabile, deci un nonce WP ar fi expirat în HTML-ul din cache
 * (și pentru vizitatori anonimi nu protejează nimic). Protecția folosită:
 * same-origin (Origin/Referer), cupon activ, cookie funcțional „cupons_v”,
 * plus un vot per cupon per IP per zi (HMAC de IP cu salt, fără IP stocat).
 */
final class VoteController
{
    public const NS     = 'cupons/v1';
    public const COOKIE = 'cupons_v';
    private const MAX_COOKIE_IDS = 200;

    public static function register_hooks(): void
    {
        add_action('rest_api_init', [self::class, 'routes']);
    }

    public static function routes(): void
    {
        register_rest_route(self::NS, '/vote', [
            'methods'             => \WP_REST_Server::CREATABLE,
            'callback'            => [self::class, 'vote'],
            'permission_callback' => [self::class, 'permission'],
            'args'                => [
                'id' => ['type' => 'integer', 'required' => true, 'minimum' => 1],
                'v'  => ['type' => 'integer', 'required' => true, 'enum' => [0, 1]],
            ],
        ]);
    }

    public static function permission(): bool|\WP_Error
    {
        if (!Request::is_same_origin()) {
            return new \WP_Error('cupons_origin', 'Cerere respinsă.', ['status' => 403]);
        }
        return true;
    }

    public static function vote(\WP_REST_Request $req): \WP_REST_Response|\WP_Error
    {
        Request::no_cache();
        $id = (int) $req['id'];

        $post = get_post($id);
        if (!$post || $post->post_type !== Keys::COUPON || $post->post_status !== 'publish' || !Offers::is_active($id)) {
            return new \WP_Error('cupons_not_found', 'Oferta nu mai este activă.', ['status' => 404]);
        }

        $voted = self::voted_ids();
        if (in_array($id, $voted, true)) {
            return self::respond(['ok' => false, 'reason' => 'voted'] + Votes::for_coupon($id), 409);
        }

        if (!Votes::claim_ip_slot($id, Request::client_ip())) {
            return self::respond(['ok' => false, 'reason' => 'voted'] + Votes::for_coupon($id), 429);
        }

        Votes::add($id, (int) $req['v'] === 1);

        $voted[] = $id;
        self::set_cookie(array_slice($voted, -self::MAX_COOKIE_IDS));

        return self::respond(['ok' => true] + Votes::for_coupon($id), 200);
    }

    /** @return int[] */
    private static function voted_ids(): array
    {
        $raw = isset($_COOKIE[self::COOKIE]) ? (string) wp_unslash($_COOKIE[self::COOKIE]) : '';
        return array_values(array_filter(array_map('intval', explode('.', $raw))));
    }

    /** Cookie funcțional (nu necesită consimțământ): doar ID-urile ofertelor votate. */
    private static function set_cookie(array $ids): void
    {
        if (headers_sent()) {
            return;
        }
        setcookie(self::COOKIE, implode('.', $ids), [
            'expires'  => time() + YEAR_IN_SECONDS,
            'path'     => COOKIEPATH ?: '/',
            'domain'   => COOKIE_DOMAIN ?: '',
            'secure'   => is_ssl(),
            'httponly' => false, // JS îl citește ca să marcheze „ai votat” pe paginile din cache
            'samesite' => 'Lax',
        ]);
    }

    private static function respond(array $data, int $status): \WP_REST_Response
    {
        $res = new \WP_REST_Response($data, $status);
        $res->header('Cache-Control', 'no-store');
        $res->header('X-Robots-Tag', 'noindex');
        return $res;
    }
}
