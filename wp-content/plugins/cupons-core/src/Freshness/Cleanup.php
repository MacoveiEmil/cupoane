<?php

declare(strict_types=1);

namespace Cupons\Core\Freshness;

use Cupons\Core\Content\Links;
use Cupons\Core\Content\Sync;
use Cupons\Core\Keys;
use Cupons\Core\Query\Offers;
use Cupons\Core\Stats\Votes;
use Cupons\Core\Support\Dates;
use Cupons\Core\Support\Settings;

/**
 * Jobul zilnic de prospețime:
 * 1. ofertele expirate (orice status) → Trash (golit automat după EMPTY_TRASH_DAYS = 30);
 * 2. ofertele fără expirare, neverificate de N zile → ciornă + „De reverificat”;
 * 3. purge de cache pe paginile afectate, inclusiv unde se schimbă eticheta „Expiră mâine/azi”;
 * 4. curățarea rate-limit-ului de voturi.
 */
final class Cleanup
{
    public const LOCK_TTL  = 1800;
    public const BATCH     = 200;
    public const LOG_OPTION = 'cupons_last_cleanup';

    /**
     * @return array{locked?:bool,expired:int,stale:int,labels:int,guard:int,dry:bool,date:string}
     */
    public static function run(bool $dry = false): array
    {
        if (!Lock::acquire('cleanup', self::LOCK_TTL)) {
            return ['locked' => true, 'expired' => 0, 'stale' => 0, 'labels' => 0, 'guard' => 0, 'dry' => $dry, 'date' => Dates::today()];
        }

        try {
            $report = [
                'expired' => self::trash_expired($dry),
                'stale'   => self::draft_stale($dry),
                'labels'  => self::refresh_labels($dry),
                'guard'   => $dry ? 0 : Votes::prune_guard(),
                'dry'     => $dry,
                'date'    => Dates::today(),
            ];

            if (!$dry) {
                // Homepage-ul afișează data zilei: se regenerează zilnic, chiar dacă nu s-a schimbat nicio ofertă.
                CachePurger::instance()->add_url(home_url('/'));
                CachePurger::instance()->flush();
                update_option(self::LOG_OPTION, ['time' => time()] + $report, false);
            }

            return $report;
        } finally {
            Lock::release('cleanup');
        }
    }

    private static function trash_expired(bool $dry): int
    {
        $count = 0;
        $args  = [
            'post_type'        => Keys::offer_types(),
            'post_status'      => ['publish', 'draft', 'pending', 'future', 'private'],
            'fields'           => 'ids',
            'posts_per_page'   => self::BATCH,
            'no_found_rows'    => true,
            'orderby'          => 'ID',
            'order'            => 'ASC',
            Offers::BYPASS     => true,
            'meta_query'       => [[
                'key' => Keys::M_UNTIL, 'value' => Dates::today(), 'compare' => '<', 'type' => 'DATE',
            ]],
        ];

        if ($dry) {
            $args['posts_per_page'] = -1;
            return count(get_posts($args));
        }

        // Fiecare lot mută posturile în Trash, deci următorul query ia lotul următor.
        do {
            $ids  = get_posts($args);
            $done = 0;
            foreach ($ids as $id) {
                $store = Links::store_of((int) $id);
                if (wp_trash_post((int) $id)) {
                    $done++;
                    if ($store) {
                        Sync::touch_store($store->term_id);
                    }
                }
            }
            $count += $done;
        } while (count($ids) === self::BATCH && $done > 0);

        return $count;
    }

    private static function draft_stale(bool $dry): int
    {
        $days      = max(1, Settings::int('recheck_days'));
        $threshold = Dates::offset(-$days);
        $count     = 0;
        $args      = [
            'post_type'      => Keys::offer_types(),
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => self::BATCH,
            'no_found_rows'  => true,
            Offers::BYPASS   => true,
            'meta_query'     => [
                'relation' => 'AND',
                ['key' => Keys::M_UNTIL, 'value' => Keys::DATE_MAX],
                ['key' => Keys::M_VERIFIED, 'value' => $threshold, 'compare' => '<', 'type' => 'DATE'],
            ],
        ];

        if ($dry) {
            $args['posts_per_page'] = -1;
            return count(get_posts($args));
        }

        do {
            $ids  = get_posts($args);
            $done = 0;
            foreach ($ids as $id) {
                update_post_meta((int) $id, Keys::M_RECHECK, '1');
                $res = wp_update_post(['ID' => (int) $id, 'post_status' => 'draft'], true);
                if (!is_wp_error($res) && $res) {
                    $done++;
                }
            }
            $count += $done;
        } while (count($ids) === self::BATCH && $done > 0);

        return $count;
    }

    /** Ofertele care expiră azi sau mâine își schimbă eticheta: paginile lor trebuie regenerate. */
    private static function refresh_labels(bool $dry): int
    {
        $ids = get_posts([
            'post_type'      => Keys::offer_types(),
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'posts_per_page' => -1,
            'no_found_rows'  => true,
            Offers::BYPASS   => true,
            'meta_query'     => [[
                'key' => Keys::M_UNTIL, 'value' => [Dates::today(), Dates::offset(1)], 'compare' => 'IN',
            ]],
        ]);
        if (!$dry) {
            foreach ($ids as $id) {
                CachePurger::instance()->add_post((int) $id);
            }
        }
        return count($ids);
    }

    /** „Verificat azi” din admin: republică și actualizează data verificării. */
    public static function mark_verified(int $post_id): bool
    {
        update_post_meta($post_id, Keys::M_VERIFIED, Dates::today());
        delete_post_meta($post_id, Keys::M_RECHECK);
        $res = wp_update_post(['ID' => $post_id, 'post_status' => 'publish'], true);
        return !is_wp_error($res);
    }
}
