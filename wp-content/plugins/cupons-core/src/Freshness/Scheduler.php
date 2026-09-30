<?php

declare(strict_types=1);

namespace Cupons\Core\Freshness;

use Cupons\Core\Support\Dates;

/**
 * Programare zilnică la 00:05 ora României, imediat după ce ofertele de ieri au expirat.
 * Pe producție, WP-Cron e declanșat de cron-ul de sistem (vezi README).
 */
final class Scheduler
{
    public const HOOK = 'cupons_daily';
    public const TIME = '00:05';

    public static function register_hooks(): void
    {
        add_action(self::HOOK, [self::class, 'run']);
        add_action('admin_init', [self::class, 'schedule']);
        // Ora 00:05 e locală: dacă se schimbă fusul orar, reprogramăm.
        add_action('update_option_timezone_string', [self::class, 'reschedule']);
        add_action('update_option_gmt_offset', [self::class, 'reschedule']);
    }

    public static function reschedule(): void
    {
        self::unschedule();
        self::schedule();
    }

    public static function run(): void
    {
        Cleanup::run();
    }

    public static function schedule(): void
    {
        if (!wp_next_scheduled(self::HOOK)) {
            wp_schedule_event(Dates::next_local_time(self::TIME), 'daily', self::HOOK);
        }
    }

    public static function unschedule(): void
    {
        wp_clear_scheduled_hook(self::HOOK);
    }
}
