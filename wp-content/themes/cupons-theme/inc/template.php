<?php
/**
 * Helperi de afișare. Datele vin din pluginul cupons-core (Cupons\Core\Front\View).
 */

declare(strict_types=1);

use Cupons\Core\Content\Pages;
use Cupons\Core\Front\Breadcrumbs;
use Cupons\Core\Front\Images;
use Cupons\Core\Front\SearchIndex;
use Cupons\Core\Front\View;

function ct_ready(): bool
{
    return class_exists(View::class);
}

function ct_page_url(string $key): string
{
    return ct_ready() ? Pages::url($key) : '';
}

/** Atributele comune ale linkurilor de ieșire. */
function ct_out_attrs(): string
{
    return 'target="_blank" rel="sponsored nofollow noopener"';
}

/** Meniul principal: meniul din admin (dacă e setat) sau cele 4 elemente implicite. */
function ct_menu_items(): array
{
    $locations = get_nav_menu_locations();
    if (!empty($locations['primary'])) {
        $items = wp_get_nav_menu_items($locations['primary']);
        if (is_array($items) && $items) {
            return array_slice(array_map(static fn($i) => ['label' => $i->title, 'url' => $i->url, 'icon' => 'chevron'], $items), 0, 5);
        }
    }
    $out  = [];
    $defs = [
        'magazine'   => ['Magazine', 'store'],
        'categorii'  => ['Categorii', 'grid'],
        'deal-uri'   => ['Deal-uri', 'percent'],
        'top-oferte' => ['Top oferte', 'fire'],
    ];
    foreach ($defs as $key => [$label, $icon]) {
        $url = ct_page_url($key);
        if ($url !== '') {
            $out[] = ['label' => $label, 'url' => $url, 'icon' => $icon];
        }
    }
    return $out;
}

function ct_is_current(string $url): bool
{
    $req = home_url(add_query_arg([]));
    return untrailingslashit(strtok($req, '?')) === untrailingslashit($url);
}

/* ---------- Componente ---------- */

function ct_search_form(bool $big = false): void
{
    $action = ct_page_url('cauta') ?: home_url('/');
    $index  = ct_ready() ? SearchIndex::url() : '';
    $value  = ct_ready() ? View::search_query() : '';
    $id     = $big ? 'q-hero' : 'q-' . wp_unique_id();
    ?>
    <form class="search<?php echo $big ? ' search--big' : ''; ?>" role="search" action="<?php echo esc_url($action); ?>" method="get" data-search data-index="<?php echo esc_url($index); ?>">
        <label class="sr" for="<?php echo esc_attr($id); ?>">Caută un magazin</label>
        <div class="search__row">
            <input class="search__input" id="<?php echo esc_attr($id); ?>" type="search" name="q" value="<?php echo esc_attr($value); ?>"
                   placeholder="Caută un magazin, ex.: <?php echo esc_attr(ct_example_store()); ?>" autocomplete="off" spellcheck="false"
                   role="combobox" aria-autocomplete="list" aria-expanded="false" aria-controls="<?php echo esc_attr($id); ?>-list">
            <button class="search__btn" type="submit"><?php echo ct_icon('search', 20); ?><span class="sr">Caută</span></button>
        </div>
        <ul class="search__list" id="<?php echo esc_attr($id); ?>-list" role="listbox" hidden></ul>
    </form>
    <?php
}

function ct_example_store(): string
{
    if (!ct_ready()) {
        return '';
    }
    $s = View::popular_stores(1);
    return $s ? $s[0]->name : 'numele magazinului';
}

function ct_breadcrumbs(): void
{
    if (!ct_ready()) {
        return;
    }
    $items = Breadcrumbs::items();
    if (count($items) < 2) {
        return;
    }
    echo '<nav class="crumbs" aria-label="Breadcrumb"><ol>';
    $last = count($items) - 1;
    foreach ($items as $i => $item) {
        if ($i === $last || $item['url'] === '') {
            printf('<li><span aria-current="page">%s</span></li>', esc_html($item['name']));
        } else {
            printf('<li><a href="%s">%s</a></li>', esc_url($item['url']), esc_html($item['name']));
        }
    }
    echo '</ol></nav>';
}

function ct_section_head(string $title, string $more_url = '', string $id = '', string $more = 'Vezi toate'): void
{
    printf('<div class="sec__head"><h2%s>%s</h2>', $id ? ' id="' . esc_attr($id) . '"' : '', esc_html($title));
    if ($more_url !== '') {
        printf('<a class="sec__more" href="%s">%s %s</a>', esc_url($more_url), esc_html($more), ct_icon('chevron', 16));
    }
    echo '</div>';
}

/** Preîncarcă voturile pentru o listă de postări (un singur query). @param WP_Post[] $posts */
function ct_prime_votes(array $posts): void
{
    if (ct_ready() && $posts) {
        View::prime_votes(wp_list_pluck($posts, 'ID'));
    }
}

/** Cardul de ofertă (cupon). */
function ct_offer_card(WP_Post $post, bool $show_logo = true, string $heading = 'h3'): void
{
    $o = View::offer($post);
    get_template_part('parts/card', 'cupon', ['o' => $o, 'show_logo' => $show_logo, 'h' => $heading]);
}

function ct_deal_card(WP_Post $post): void
{
    get_template_part('parts/card', 'deal', ['d' => View::deal($post)]);
}

/** @param WP_Post[] $posts */
function ct_offer_list(array $posts, bool $show_logo = true, string $heading = 'h3'): void
{
    if (!$posts) {
        return;
    }
    ct_prime_votes($posts);
    echo '<div class="offers">';
    foreach ($posts as $p) {
        ct_offer_card($p, $show_logo, $heading);
    }
    echo '</div>';
}

/** Tile de magazin (logo + nume). */
function ct_store_tile(WP_Term $t, bool $lazy = true, bool $count = false): void
{
    $counts = $count ? View::store($t)['count'] : null;
    ?>
    <a class="tile" href="<?php echo esc_url((string) get_term_link($t)); ?>">
        <span class="logo logo--md"><?php echo View::logo($t, ['size' => Images::LOGO, 'px' => 64, 'lazy' => $lazy]); // phpcs:ignore -- HTML generat și escapat în plugin ?></span>
        <span class="tile__name"><?php echo esc_html($t->name); ?></span>
        <?php if ($counts !== null) : ?>
            <span class="tile__count"><?php echo esc_html(ct_offers_label($counts)); ?></span>
        <?php endif; ?>
    </a>
    <?php
}

function ct_offers_label(int $n): string
{
    if ($n === 0) {
        return 'nicio ofertă activă';
    }
    if ($n === 1) {
        return '1 ofertă activă';
    }
    return $n . ($n % 100 < 20 ? ' oferte active' : ' de oferte active');
}

function ct_pagination(int $total, int $current): void
{
    if ($total < 2) {
        return;
    }
    $links = paginate_links([
        'total'     => $total,
        'current'   => max(1, $current),
        'type'      => 'array',
        'prev_text' => '← Înapoi',
        'next_text' => 'Înainte →',
        'mid_size'  => 1,
    ]);
    if ($links) {
        echo '<nav class="pager" aria-label="Paginare"><ul><li>' . implode('</li><li>', $links) . '</li></ul></nav>'; // phpcs:ignore -- generat de WP
    }
}

/** Mențiunea de afiliere (footer, homepage). */
function ct_affiliate_note(): void
{
    $url = ct_page_url('transparenta');
    echo '<p class="note">Unele linkuri de pe site sunt linkuri de afiliere: dacă faci o comandă prin ele, putem primi un comision, fără niciun cost în plus pentru tine.';
    if ($url !== '') {
        printf(' <a href="%s">Cum funcționează</a>.', esc_url($url));
    }
    echo '</p>';
}

/* ---------- Pagini statice: cuprins din H2 ---------- */

function ct_heading_ids(string $content): string
{
    if (!is_page() || !in_the_loop() || !is_main_query()) {
        return $content;
    }
    $used = [];
    return (string) preg_replace_callback('/<h2([^>]*)>(.*?)<\/h2>/is', static function ($m) use (&$used) {
        if (str_contains($m[1], ' id=')) {
            return $m[0];
        }
        $base = sanitize_title(wp_strip_all_tags($m[2])) ?: 'sectiune';
        $id   = $base;
        $n    = 2;
        while (isset($used[$id])) {
            $id = $base . '-' . $n++;
        }
        $used[$id] = true;
        return '<h2' . $m[1] . ' id="' . esc_attr($id) . '">' . $m[2] . '</h2>';
    }, $content);
}

/** Cuprins din H2-urile cu id (afișat doar de la 3 secțiuni). */
function ct_toc(string $html): string
{
    if (!preg_match_all('/<h2[^>]*id="([^"]+)"[^>]*>(.*?)<\/h2>/is', $html, $m, PREG_SET_ORDER) || count($m) < 3) {
        return '';
    }
    $out = '<nav class="toc" aria-label="Cuprins"><details open><summary>Cuprins</summary><ol>';
    foreach ($m as $h) {
        $out .= sprintf('<li><a href="#%s">%s</a></li>', esc_attr($h[1]), esc_html(wp_strip_all_tags($h[2])));
    }
    return $out . '</ol></details></nav>';
}
