<?php

declare(strict_types=1);

namespace Cupons\Core\Stats;

use Cupons\Core\Install\Installer;
use Cupons\Core\Support\Dates;
use Cupons\Core\Support\Settings;
use Cupons\Core\Support\Tables;

/**
 * Voturi reale „A funcționat? Da / Nu”, agregate pe zi.
 * Rata de succes se afișează doar de la pragul minim de voturi (setare, implicit 5).
 */
final class Votes
{
    public static function add(int $coupon_id, bool $yes): void
    {
        global $wpdb;
        $table = Tables::votes();
        $col   = $yes ? 'yes' : 'no';
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (coupon_id, date, yes, no) VALUES (%d, %s, %d, %d)
             ON DUPLICATE KEY UPDATE {$col} = {$col} + 1",
            $coupon_id, Dates::today(), $yes ? 1 : 0, $yes ? 0 : 1
        ));
    }

    /**
     * @param int[] $ids
     * @return array<int,array{yes:int,no:int,total:int,rate:?int}>
     */
    public static function totals(array $ids): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        global $wpdb;
        $table = Tables::votes();
        $in    = implode(',', array_fill(0, count($ids), '%d'));
        $rows  = $wpdb->get_results($wpdb->prepare(
            "SELECT coupon_id, SUM(yes) AS y, SUM(no) AS n FROM {$table} WHERE coupon_id IN ({$in}) GROUP BY coupon_id",
            ...$ids
        ));
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->coupon_id] = self::summary((int) $row->y, (int) $row->n);
        }
        return $out;
    }

    public static function for_coupon(int $id): array
    {
        return self::totals([$id])[$id] ?? self::summary(0, 0);
    }

    /** rate = null sub prag: nu se afișează nicio cifră. */
    public static function summary(int $yes, int $no): array
    {
        $total = $yes + $no;
        $min   = max(1, Settings::int('vote_min'));
        return [
            'yes'   => $yes,
            'no'    => $no,
            'total' => $total,
            'rate'  => $total >= $min ? (int) round($yes / $total * 100) : null,
        ];
    }

    /**
     * Rate-limit pe IP: un vot per cupon per IP per zi. IP-ul nu se stochează,
     * doar un HMAC cu salt secret (nu poate fi inversat). Returnează false dacă a mai votat.
     */
    public static function claim_ip_slot(int $coupon_id, string $ip): bool
    {
        global $wpdb;
        $salt  = (string) get_option(Installer::SALT_OPTION, wp_salt('nonce'));
        $hash  = hash_hmac('sha256', $ip . '|' . $coupon_id . '|' . Dates::today(), $salt);
        $table = Tables::vote_guard();
        $done  = $wpdb->query($wpdb->prepare(
            "INSERT IGNORE INTO {$table} (hash, created) VALUES (%s, %s)",
            $hash, current_time('mysql', true)
        ));
        return $done === 1;
    }

    public static function prune_guard(int $days = 2): int
    {
        global $wpdb;
        $table = Tables::vote_guard();
        return (int) $wpdb->query($wpdb->prepare(
            "DELETE FROM {$table} WHERE created < %s",
            gmdate('Y-m-d H:i:s', time() - $days * DAY_IN_SECONDS)
        ));
    }
}
