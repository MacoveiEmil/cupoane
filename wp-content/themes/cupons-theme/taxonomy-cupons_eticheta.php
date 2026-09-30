<?php
/**
 * Pagini tematice /oferte/{eticheta}/ (transport gratuit, prima comandă, studenți, cumulabil).
 */

declare(strict_types=1);

get_header();

$term  = get_queried_object();
$paged = max(1, (int) get_query_var('paged'));
$posts = $GLOBALS['wp_query']->posts;
?>
<div class="wrap">
    <?php ct_breadcrumbs(); ?>
    <header class="page-head">
        <h1><?php echo esc_html($term->name); ?>: vouchere, reduceri și cupoane</h1>
        <?php if ($paged === 1 && trim(wp_strip_all_tags(term_description($term))) !== '') : ?>
            <div class="page-head__sub"><?php echo wp_kses_post(term_description($term)); ?></div>
        <?php elseif ($paged > 1) : ?>
            <p class="page-head__sub">Pagina <?php echo (int) $paged; ?></p>
        <?php endif; ?>
    </header>

    <?php if ($posts) : ?>
        <?php ct_offer_list($posts, true, 'h2'); ?>
        <?php ct_pagination((int) $GLOBALS['wp_query']->max_num_pages, $paged); ?>
    <?php else : ?>
        <p class="empty">Momentan nu sunt oferte active cu această etichetă.</p>
    <?php endif; ?>
</div>
<?php
get_footer();
