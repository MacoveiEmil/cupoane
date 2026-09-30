<?php

declare(strict_types=1);

namespace Cupons\Core\Stats;

use Cupons\Core\Support\Dates;
use Cupons\Core\Support\Tables;

/** Click-uri agregate pe zi: un rând per (tip, obiect, zi), incrementat prin UPSERT. */
final class Clicks
{
    public static function increment(int $type, int $object_id): void
    {
        global $wpdb;
        $table = Tables::clicks();
        $wpdb->query($wpdb->prepare(
            "INSERT INTO {$table} (object_type, object_id, date, count) VALUES (%d, %d, %s, 1)
             ON DUPLICATE KEY UPDATE count = count + 1",
            $type, $object_id, Dates::today()
        ));
    }

    /**
     * @param int[] $ids
     * @return array<int,int> object_id => click-uri în ultimele $days zile
     */
    public static function totals(int $type, array $ids, int $days = 30): array
    {
        $ids = array_values(array_filter(array_map('intval', $ids)));
        if (!$ids) {
            return [];
        }
        global $wpdb;
        $table = Tables::clicks();
        $in    = implode(',', array_fill(0, count($ids), '%d'));
        $rows  = $wpdb->get_results($wpdb->prepare(
            "SELECT object_id, SUM(count) AS c FROM {$table}
             WHERE object_type = %d AND date >= %s AND object_id IN ({$in})
             GROUP BY object_id",
            $type, Dates::offset(-$days), ...$ids
        ));
        $out = [];
        foreach ($rows as $row) {
            $out[(int) $row->object_id] = (int) $row->c;
        }
        return $out;
    }

    /**
     * Cele mai accesate obiecte în ultimele $days zile.
     * @return int[]
     */
    public static function top(int $type, int $days = 30, int $limit = 50): array
    {
        global $wpdb;
        $table = Tables::clicks();
        return array_map('intval', $wpdb->get_col($wpdb->prepare(
            "SELECT object_id FROM {$table} WHERE object_type = %d AND date >= %s
             GROUP BY object_id ORDER BY SUM(count) DESC LIMIT %d",
            $type, Dates::offset(-$days), $limit
        )));
    }
}
