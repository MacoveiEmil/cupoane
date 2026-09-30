<?php
/**
 * /cauta/?q= — rezultate de căutare (funcționează fără JS). noindex.
 */

declare(strict_types=1);

use Cupons\Core\Front\View;

get_header();

$q   = ct_ready() ? View::search_query() : '';
$res = ct_ready() ? View::search($q) : ['stores' => [], 'categories' => []];
?>
<div class="wrap">
    <?php ct_breadcrumbs(); ?>
    <header class="page-head">
        <h1><?php echo $q !== '' ? 'Rezultate pentru „' . esc_html($q) . '”' : 'Caută un magazin'; ?></h1>
    </header>

    <div class="search-page"><?php ct_search_form(); ?></div>

    <?php if ($q !== '') : ?>
        <?php if ($res['stores']) : ?>
            <section class="sec" aria-labelledby="r-stores">
                <h2 id="r-stores">Magazine</h2>
                <div class="tiles"><?php foreach ($res['stores'] as $t) {
                    ct_store_tile($t, true, true);
                } ?></div>
            </section>
        <?php endif; ?>
        <?php if ($res['categories']) : ?>
            <section class="sec" aria-labelledby="r-cats">
                <h2 id="r-cats">Categorii</h2>
                <ul class="chips"><?php foreach ($res['categories'] as $c) : ?>
                    <li><a href="<?php echo esc_url((string) get_term_link($c)); ?>"><?php echo esc_html($c->name); ?></a></li>
                <?php endforeach; ?></ul>
            </section>
        <?php endif; ?>
        <?php if (!$res['stores'] && !$res['categories']) : ?>
            <p class="empty">N-am găsit niciun magazin. Verifică scrierea sau caută în <a href="<?php echo esc_url(ct_page_url('magazine')); ?>">lista A–Z</a>.</p>
        <?php endif; ?>
    <?php endif; ?>
</div>
<?php
get_footer();
