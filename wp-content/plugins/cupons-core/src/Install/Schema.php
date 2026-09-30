<?php

declare(strict_types=1);

namespace Cupons\Core\Install;

use Cupons\Core\Support\Tables;

final class Schema
{
    public const VERSION = '1';
    public const OPTION  = 'cupons_db_version';

    public static function install(): void
    {
        global $wpdb;
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';

        $charset = $wpdb->get_charset_collate();
        $clicks  = Tables::clicks();
        $votes   = Tables::votes();
        $guard   = Tables::vote_guard();
        $prog    = Tables::programs();

        // Atenție: dbDelta cere două spații după PRIMARY KEY.
        dbDelta("CREATE TABLE {$clicks} (
  object_type tinyint(3) unsigned NOT NULL,
  object_id bigint(20) unsigned NOT NULL,
  date date NOT NULL,
  count int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (object_type,object_id,date),
  KEY date (date)
) {$charset};");

        dbDelta("CREATE TABLE {$votes} (
  coupon_id bigint(20) unsigned NOT NULL,
  date date NOT NULL,
  yes int(10) unsigned NOT NULL DEFAULT 0,
  no int(10) unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY  (coupon_id,date)
) {$charset};");

        dbDelta("CREATE TABLE {$guard} (
  hash char(64) NOT NULL,
  created datetime NOT NULL,
  PRIMARY KEY  (hash),
  KEY created (created)
) {$charset};");

        dbDelta("CREATE TABLE {$prog} (
  network varchar(20) NOT NULL,
  program_id varchar(64) NOT NULL,
  name varchar(190) NOT NULL DEFAULT '',
  site_url varchar(255) NOT NULL DEFAULT '',
  term_id bigint(20) unsigned NOT NULL DEFAULT 0,
  last_seen datetime NULL,
  PRIMARY KEY  (network,program_id),
  KEY term_id (term_id)
) {$charset};");

        update_option(self::OPTION, self::VERSION, false);
    }
}
