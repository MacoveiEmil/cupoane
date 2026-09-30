<?php

declare(strict_types=1);

namespace Cupons\Core\Front;

use Cupons\Core\Content\Pages;
use Cupons\Core\Keys;
use Cupons\Core\Query\Offers;
use Cupons\Core\Support\Dates;

/**
 * Title, description, robots și canonical.
 *
 * Cu Rank Math activ: valorile noastre sunt template-urile implicite, aplicate prin filtrele
 * Rank Math; un titlu/descriere setat manual în Rank Math pe pagină are prioritate.
 * Fără Rank Math: pluginul le scrie singur în <head>.
 */
final class Seo
{
    /** Sub acest număr de oferte active, pagina de magazin/categorie/etichetă e noindex. */
    public const MIN_OFFERS_INDEX = 3;

    private const MONTHS = ['', 'ianuarie', 'februarie', 'martie', 'aprilie', 'mai', 'iunie', 'iulie', 'august', 'septembrie', 'octombrie', 'noiembrie', 'decembrie'];

    public static function register_hooks(): void
    {
        add_action('wp', [self::class, 'wire']);
    }

    /**
     * Rank Math e instalat ȘI își scrie efectiv meta-urile pe frontend
     * (până la parcurgerea wizard-ului / „skip registration” nu scrie nimic).
     */
    public static function rank_math(): bool
    {
        static $on = null;
        return $on ??= defined('RANK_MATH_VERSION') && function_exists('rank_math') && isset(rank_math()->frontend);
    }

    public static function wire(): void
    {
        if (is_admin()) {
            return;
        }
        if (self::rank_math()) {
            add_filter('rank_math/frontend/title', [self::class, 'rm_title']);
            add_filter('rank_math/frontend/description', [self::class, 'rm_description']);
            add_filter('rank_math/frontend/robots', [self::class, 'rm_robots']);
            // Termenii cu < 3 oferte sunt noindex: nu au ce căuta în sitemap.
            add_filter('rank_math/sitemap/entry', [self::class, 'rm_sitemap_entry'], 10, 3);
            return;
        }

        add_filter('pre_get_document_title', [self::class, 'fallback_title'], 20);
        add_filter('wp_robots', [self::class, 'fallback_robots']);
        add_action('wp_head', [self::class, 'fallback_head'], 1);
    }

    /* ---------- Valorile calculate ---------- */

    /** @return array{title:string,description:string} Șiruri goale = lăsăm valoarea implicită. */
    public static function meta(): array
    {
        static $meta = null;
        if ($meta !== null) {
            return $meta;
        }

        $site  = get_bloginfo('name');
        $month = self::MONTHS[(int) current_datetime()->format('n')];
        $year  = current_datetime()->format('Y');
        $ll    = "{$month} {$year}";
        $meta  = ['title' => '', 'description' => ''];

        if (is_front_page()) {
            $meta = [
                'title'       => "{$site} — Vouchere, reduceri și cupoane verificate zilnic",
                'description' => 'Vouchere, reduceri și cupoane verificate pentru magazinele online din România. Coduri actualizate zilnic, fără oferte expirate.',
            ];
        } elseif (is_tax(Keys::STORE)) {
            $t       = get_queried_object();
            $n       = Offers::count_for_term($t->term_id, Keys::STORE);
            $updated = Dates::human((string) get_term_meta($t->term_id, Keys::T_UPDATED, true)) ?: Dates::human(Dates::today());
            $meta    = [
                'title'       => "Cod reducere {$t->name} {$ll} — vouchere, reduceri și cupoane",
                'description' => "Vouchere, reduceri și cupoane {$t->name} verificate: {$n} " . self::offers_word($n) . " active în {$ll}. Actualizat la {$updated}.",
            ];
        } elseif (is_tax(Keys::CATEGORY)) {
            $t      = get_queried_object();
            $n      = Offers::count_for_term($t->term_id, Keys::CATEGORY);
            $stores = View::stores_per_category()[$t->term_id] ?? 0;
            $meta   = [
                'title'       => "Vouchere, reduceri și cupoane {$t->name} — {$ll}",
                'description' => "Vouchere, reduceri și cupoane {$t->name}: {$n} " . self::offers_word($n) . " active de la {$stores} " . ($stores === 1 ? 'magazin' : 'magazine') . ", verificate în {$ll}.",
            ];
        } elseif (is_tax(Keys::TAG)) {
            $t    = get_queried_object();
            $n    = Offers::count_for_term($t->term_id, Keys::TAG);
            $meta = [
                'title'       => "{$t->name}: vouchere, reduceri și cupoane — {$ll}",
                'description' => "Vouchere, reduceri și cupoane cu {$t->name}: {$n} " . self::offers_word($n) . " active, verificate în {$ll}.",
            ];
        } elseif (Pages::is('magazine')) {
            $meta = [
                'title'       => "Magazine A–Z — vouchere, reduceri și cupoane | {$site}",
                'description' => 'Toate magazinele online cu vouchere, reduceri și cupoane active, în ordine alfabetică.',
            ];
        } elseif (Pages::is('categorii')) {
            $meta = [
                'title'       => "Categorii — vouchere, reduceri și cupoane | {$site}",
                'description' => 'Vouchere, reduceri și cupoane pe categorii de magazine: fashion, electronice, casă, sănătate și altele.',
            ];
        } elseif (Pages::is('deal-uri')) {
            $meta = [
                'title'       => "Deal-uri: produse la preț redus — {$ll} | {$site}",
                'description' => 'Produse cu preț redus în magazinele online din România, verificate. Vouchere, reduceri și cupoane actualizate zilnic.',
            ];
        } elseif (Pages::is('top-oferte')) {
            $meta = [
                'title'       => "Top oferte {$ll} — vouchere, reduceri și cupoane | {$site}",
                'description' => 'Cele mai bune vouchere, reduceri și cupoane active acum: coduri exclusive și oferte adăugate recent.',
            ];
        }

        return $meta;
    }

    public static function noindex(): bool
    {
        if (is_paged() || is_search() || is_404() || Pages::is('cauta')) {
            return true;
        }
        if (is_tax([Keys::STORE, Keys::CATEGORY, Keys::TAG])) {
            $t = get_queried_object();
            return Offers::count_for_term($t->term_id, $t->taxonomy) < self::MIN_OFFERS_INDEX;
        }
        return false;
    }

    private static function offers_word(int $n): string
    {
        // 1 ofertă, 2–19 oferte, 20+ „de oferte” (regula românească).
        if ($n === 1) {
            return 'ofertă';
        }
        return ($n === 0 || $n % 100 < 20) ? 'oferte' : 'de oferte';
    }

    /* ---------- Rank Math ---------- */

    private static function has_custom(string $key): bool
    {
        if (is_tax() || is_category() || is_tag()) {
            return (string) get_term_meta(get_queried_object_id(), $key, true) !== '';
        }
        if (is_singular() || is_front_page()) {
            return (string) get_post_meta(get_queried_object_id(), $key, true) !== '';
        }
        return false;
    }

    public static function rm_title(string $title): string
    {
        $mine = self::meta()['title'];
        if ($mine === '' || self::has_custom('rank_math_title')) {
            return $title;
        }
        $page = (int) get_query_var('paged');
        return $page > 1 ? "{$mine} — pagina {$page}" : $mine;
    }

    public static function rm_description(string $desc): string
    {
        $mine = self::meta()['description'];
        return ($mine === '' || self::has_custom('rank_math_description')) ? $desc : $mine;
    }

    public static function rm_robots(array $robots): array
    {
        if (self::noindex()) {
            $robots['index']  = 'noindex';
            $robots['follow'] = 'follow';
        }
        return $robots;
    }

    public static function rm_sitemap_entry(mixed $url, string $type, mixed $object): mixed
    {
        if ($type === 'term' && $object instanceof \WP_Term && in_array($object->taxonomy, [Keys::STORE, Keys::CATEGORY, Keys::TAG], true)) {
            if (Offers::count_for_term($object->term_id, $object->taxonomy) < self::MIN_OFFERS_INDEX) {
                return false;
            }
        }
        if ($type === 'post' && $object instanceof \WP_Post && $object->ID === Pages::id('cauta')) {
            return false;
        }
        return $url;
    }

    /* ---------- Fără Rank Math ---------- */

    public static function fallback_title(string $title): string
    {
        $mine = self::meta()['title'];
        if ($mine === '') {
            return $title;
        }
        $page = (int) get_query_var('paged');
        return $page > 1 ? "{$mine} — pagina {$page}" : $mine;
    }

    public static function fallback_robots(array $robots): array
    {
        if (self::noindex()) {
            unset($robots['index']);
            $robots['noindex'] = true;
            $robots['follow']  = true;
        }
        return $robots;
    }

    public static function fallback_head(): void
    {
        $desc = self::meta()['description'];
        if ($desc !== '') {
            printf("<meta name=\"description\" content=\"%s\">\n", esc_attr($desc));
        }
        // WordPress scrie canonical doar pe paginile singulare; pentru termeni îl scriem noi.
        if (is_tax([Keys::STORE, Keys::CATEGORY, Keys::TAG])) {
            $link = get_term_link(get_queried_object());
            if (is_string($link)) {
                $page = (int) get_query_var('paged');
                $url  = $page > 1 ? trailingslashit($link) . 'page/' . $page . '/' : $link;
                printf("<link rel=\"canonical\" href=\"%s\">\n", esc_url($url));
            }
        }
    }
}
