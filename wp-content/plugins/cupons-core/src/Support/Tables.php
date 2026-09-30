<?php

declare(strict_types=1);

namespace Cupons\Core\Support;

final class Tables
{
    public static function clicks(): string
    {
        return $GLOBALS['wpdb']->prefix . 'cupons_clicks';
    }

    public static function votes(): string
    {
        return $GLOBALS['wpdb']->prefix . 'cupons_votes';
    }

    public static function vote_guard(): string
    {
        return $GLOBALS['wpdb']->prefix . 'cupons_vote_guard';
    }

    public static function programs(): string
    {
        return $GLOBALS['wpdb']->prefix . 'cupons_programs';
    }
}
