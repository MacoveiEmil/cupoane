<?php
/**
 * Pagina de categorie: subcategorii, magazinele din categorie, ofertele active (paginat).
 */

declare(strict_types=1);

use Cupons\Core\Front\View;
use Cupons\Core\Keys;

get_header();

$term     = get_queried_object();
$paged    = max(1, (int) get_query_var('paged'));
$children = get_terms(['taxonomy' => Keys::CATEGORY, 'parent' => $term->term_id, 'hide_empty' => false]);
$stores   = $paged === 1 ? View::stores_in_category($term, 12) : [];
$posts    = $GLOBALS['wp_query']->posts;
?>
<div class="wrap">
    <?php ct_breadcrumbs(); ?>
    <header class="page-head">
        <h1>Vouchere, reduceri și cupoane <?php echo esc_html($term->name); ?></h1>
        <?php if ($paged > 1) : ?><p class="page-head__sub">Pagina <?php echo (int) $paged; ?></p><?php endif; ?>
    </header>

    <?php if (is_array($children) && $children) : ?>
        <ul class="chips">
            <?php foreach ($children as $c) : ?>
                <li><a href="<?php echo esc_url((string) get_term_link($c)); ?>"><?php echo esc_html($c->name); ?></a></li>
            <?php endforeach; ?>
        </ul>
    <?php endif; ?>

    <?php if ($stores) : ?>
        <section class="sec" aria-labelledby="s-stores">
            <?php ct_section_head('Magazine ' . $term->name, '', 's-stores'); ?>
            <div class="tiles"><?php foreach ($stores as $i => $t) {
                ct_store_tile($t, $i >= 6, true);
            } ?></div>
        </section>
    <?php endif; ?>

    <section class="sec" aria-labelledby="s-offers">
        <h2 id="s-offers">Oferte active</h2>
        <?php if ($posts) : ?>
            <?php ct_offer_list($posts); ?>
            <?php ct_pagination((int) $GLOBALS['wp_query']->max_num_pages, $paged); ?>
        <?php else : ?>
            <p class="empty">Momentan nu sunt oferte active în această categorie.</p>
        <?php endif; ?>
    </section>

    <?php if ($paged === 1 && trim(wp_strip_all_tags(term_description($term))) !== '') : ?>
        <section class="sec prose"><?php echo wp_kses_post(term_description($term)); ?></section>
    <?php endif; ?>
</div>
<?php
get_footer();
