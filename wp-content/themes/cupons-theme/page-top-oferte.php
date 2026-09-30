<?php
/**
 * /top-oferte/ — top oferte, coduri exclusive, adăugate recent.
 */

declare(strict_types=1);

use Cupons\Core\Front\View;

get_header();

$top       = ct_ready() ? View::top_offers(24) : [];
$exclusive = ct_ready() ? View::exclusive_offers(24) : [];
$recent    = ct_ready() ? View::recent_offers(24) : [];
?>
<div class="wrap">
    <?php ct_breadcrumbs(); ?>
    <header class="page-head">
        <h1>Top oferte</h1>
        <p class="page-head__sub">Cele mai bune vouchere, reduceri și cupoane active acum.</p>
    </header>

    <?php if ($top) : ?>
        <section class="sec" aria-labelledby="top"><?php ct_section_head('Cele mai accesate și recomandate', '', 'top'); ?><?php ct_offer_list($top); ?></section>
    <?php endif; ?>
    <?php if ($exclusive) : ?>
        <section class="sec" aria-labelledby="exclusive"><?php ct_section_head('Coduri exclusive', '', 'exclusive'); ?><?php ct_offer_list($exclusive); ?></section>
    <?php endif; ?>
    <?php if ($recent) : ?>
        <section class="sec" aria-labelledby="recente"><?php ct_section_head('Adăugate recent', '', 'recente'); ?><?php ct_offer_list($recent); ?></section>
    <?php endif; ?>
    <?php if (!$top && !$exclusive && !$recent) : ?>
        <p class="empty">Momentan nu sunt oferte active.</p>
    <?php endif; ?>
</div>
<?php
get_footer();
