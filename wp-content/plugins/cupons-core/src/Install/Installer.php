<?php

declare(strict_types=1);

namespace Cupons\Core\Install;

use Cupons\Core\Content\PostTypes;
use Cupons\Core\Content\Taxonomies;
use Cupons\Core\Freshness\Scheduler;
use Cupons\Core\Routing\Redirect;

final class Installer
{
    public const SALT_OPTION  = 'cupons_ip_salt';
    public const FLUSH_OPTION = 'cupons_flush_rewrites';

    public static function activate(): void
    {
        Schema::install();

        Taxonomies::register();
        PostTypes::register();
        Redirect::add_rules();
        Taxonomies::ensure_default_tags();

        if (!get_option(self::SALT_OPTION)) {
            add_option(self::SALT_OPTION, wp_generate_password(64, true, true), '', false);
        }

        Scheduler::schedule();
        flush_rewrite_rules(false);
    }

    public static function deactivate(): void
    {
        Scheduler::unschedule();
        flush_rewrite_rules(false);
    }

    /** Rulează la fiecare încărcare; costă o singură citire de opțiune. */
    public static function maybe_upgrade(): void
    {
        if (get_option(Schema::OPTION) !== Schema::VERSION) {
            Schema::install();
            update_option(self::FLUSH_OPTION, 1, false);
        }

        if (get_option(self::FLUSH_OPTION)) {
            add_action('init', static function (): void {
                delete_option(self::FLUSH_OPTION);
                flush_rewrite_rules(false);
            }, 99);
        }
    }

    /** Marchează rescrierea regulilor la următoarea încărcare (ex.: după schimbări de rute). */
    public static function request_flush(): void
    {
        update_option(self::FLUSH_OPTION, 1, false);
    }
}
