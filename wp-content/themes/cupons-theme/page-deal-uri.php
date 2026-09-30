<?php
/**
 * /deal-uri/ — produse la preț redus, paginat.
 */

declare(strict_types=1);

use Cupons\Core\Front\View;

get_header();

$paged = max(1, (int) get_query_var('paged'));
$q     = ct_ready() ? View::deals_query($paged) : null;
?>
<div class="wrap">
    <?php ct_breadcrumbs(); ?>
    <header class="page-head">
        <h1>Deal-uri</h1>
        <p class="page-head__sub">Produse anume la preț redus, verificate<?php echo $paged > 1 ? ' · pagina ' . (int) $paged : ''; ?>.</p>
    </header>

    <?php if ($q && $q->have_posts()) : ?>
        <div class="deals deals--page">
            <?php foreach ($q->posts as $p) {
                ct_deal_card($p);
            } ?>
        </div>
        <?php ct_pagination((int) $q->max_num_pages, $paged); ?>
    <?php else : ?>
        <p class="empty">Momentan nu avem deal-uri active.</p>
    <?php endif; ?>
</div>
<?php
get_footer();
