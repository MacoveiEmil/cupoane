<?php

declare(strict_types=1);

namespace Cupons\Core;

final class Plugin
{
    public static function boot(): void
    {
        Install\Installer::maybe_upgrade();

        Content\Taxonomies::register_hooks();
        Content\PostTypes::register_hooks();
        Content\Meta::register_hooks();
        Content\Sync::register_hooks();
        Query\Offers::register_hooks();
        Routing\Redirect::register_hooks();
        Rest\VoteController::register_hooks();
        Freshness\Scheduler::register_hooks();
        Freshness\CachePurger::register_hooks();
        Admin\CategoryRules::register_hooks(); // se aplică și la WP-CLI/import

        if (is_admin()) {
            Admin\Assets::register_hooks();
            Admin\StoreFields::register_hooks();
            Admin\CouponBox::register_hooks();
            Admin\DealBox::register_hooks();
            Admin\Columns::register_hooks();
            Admin\Reverify::register_hooks();
            Admin\Settings::register_hooks();
        }

        if (defined('WP_CLI') && WP_CLI) {
            Cli\Command::register();
        }
    }
}
