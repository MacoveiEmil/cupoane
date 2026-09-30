<?php
/**
 * Homepage: titlu + căutare, magazine populare, top oferte, exclusive, recente, deal-uri, categorii, text SEO.
 * Fără carusele. Fiecare secțiune are „Vezi toate”.
 */

declare(strict_types=1);

use Cupons\Core\Front\View;

get_header();

if (!ct_ready()) {
    echo '<div class="wrap"><p>Site în configurare.</p></div>';
    get_footer();
    return;
}

$popular   = View::popular_stores(12);
$top       = View::top_offers(8);
$exclusive = View::exclusive_offers(6, wp_list_pluck($top, 'ID'));
$recent    = View::recent_offers(8, array_merge(wp_list_pluck($top, 'ID'), wp_list_pluck($exclusive, 'ID')));
$deals     = View::deals_query(1, 6);
$cats      = View::categories_tree();
$top_url   = ct_page_url('top-oferte');
$stats     = View::stats();
$podium    = array_slice($popular, 0, 3); // primele 3 din „Ordine pe homepage”
?>
<section class="hero">
    <div class="wrap hero__grid">
        <div class="hero__main">
            <h1 class="hero__title">Vouchere, reduceri și <span class="hero__hl">cupoane verificate</span></h1>
            <p class="hero__sub">Coduri de reducere pentru magazinele online din România, într-un singur loc. Copiezi codul, mergi în magazin, plătești mai puțin.</p>
            <?php ct_search_form(true); ?>
            <p class="hero__stats">
                <span><strong><?php echo (int) $stats['offers']; ?></strong> oferte active</span>
                <span><strong><?php echo (int) $stats['stores']; ?></strong> magazine</span>
                <span>actualizat azi, <?php echo esc_html(wp_date('j F')); ?></span>
            </p>
            <?php if ($cats) : ?>
                <ul class="hero__chips" aria-label="Categorii">
                    <?php foreach (array_slice($cats, 0, 6) as $c) : ?>
                        <li><a href="<?php echo esc_url($c['url']); ?>"><?php echo esc_html($c['term']->name); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            <?php endif; ?>
        </div>

        <?php if ($podium) : ?>
            <aside class="podium" aria-labelledby="h-podium">
                <p class="podium__head" id="h-podium"><?php echo ct_icon('fire', 16); ?>Top magazine</p>
                <ol class="podium__list">
                    <?php foreach ($podium as $i => $store) :
                        $sd   = View::store($store);
                        $best = View::best_offer_label($store); ?>
                        <li>
                            <a class="podium__item" href="<?php echo esc_url($sd['url']); ?>">
                                <span class="podium__rank" aria-hidden="true"><?php echo (int) $i + 1; ?></span>
                                <span class="logo logo--sm"><?php echo View::logo($store, ['size' => \Cupons\Core\Front\Images::LOGO_SM, 'px' => 44, 'lazy' => false]); // phpcs:ignore ?></span>
                                <span class="podium__txt">
                                    <strong><?php echo esc_html($store->name); ?></strong>
                                    <small><?php echo esc_html(ct_offers_label($sd['count'])); ?></small>
                                </span>
                                <?php if ($best !== '') : ?><span class="podium__deal"><?php echo esc_html($best); ?></span><?php endif; ?>
                            </a>
                        </li>
                    <?php endforeach; ?>
                </ol>
                <a class="podium__all" href="<?php echo esc_url(ct_page_url('magazine')); ?>">Toate magazinele <?php echo ct_icon('chevron', 14); ?></a>
            </aside>
        <?php endif; ?>
    </div>
</section>

<div class="wrap">
    <?php if ($popular) : ?>
        <section class="sec" aria-labelledby="s-pop">
            <?php ct_section_head('Magazine populare', ct_page_url('magazine'), 's-pop'); ?>
            <div class="tiles tiles--home">
                <?php foreach ($popular as $i => $store) {
                    ct_store_tile($store, $i >= 8);
                } ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($top) : ?>
        <section class="sec" aria-labelledby="s-top">
            <?php ct_section_head('Top oferte', $top_url, 's-top'); ?>
            <?php ct_offer_list($top); ?>
        </section>
    <?php endif; ?>

    <?php if ($exclusive) : ?>
        <section class="sec" aria-labelledby="s-excl">
            <?php ct_section_head('Coduri exclusive', $top_url ? $top_url . '#exclusive' : '', 's-excl'); ?>
            <?php ct_offer_list($exclusive); ?>
        </section>
    <?php endif; ?>

    <?php if ($recent) : ?>
        <section class="sec" aria-labelledby="s-new">
            <?php ct_section_head('Adăugate recent', $top_url ? $top_url . '#recente' : '', 's-new'); ?>
            <?php ct_offer_list($recent); ?>
        </section>
    <?php endif; ?>

    <?php if ($deals->have_posts()) : ?>
        <section class="sec" aria-labelledby="s-deals">
            <?php ct_section_head('Deal-uri', ct_page_url('deal-uri'), 's-deals'); ?>
            <div class="deals">
                <?php foreach ($deals->posts as $p) {
                    ct_deal_card($p);
                } ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($cats) : ?>
        <section class="sec" aria-labelledby="s-cats">
            <?php ct_section_head('Categorii', ct_page_url('categorii'), 's-cats'); ?>
            <ul class="cats">
                <?php foreach ($cats as $c) : ?>
                    <li>
                        <a href="<?php echo esc_url($c['url']); ?>"><?php echo esc_html($c['term']->name); ?></a>
                        <span class="cats__n"><?php echo (int) $c['stores']; ?> magazine · <?php echo esc_html(ct_offers_label($c['offers'])); ?></span>
                    </li>
                <?php endforeach; ?>
            </ul>
        </section>
    <?php endif; ?>

    <section class="sec seo">
        <?php
        while (have_posts()) {
            the_post();
            if (trim(get_the_content()) !== '') {
                echo '<div class="prose">';
                the_content();
                echo '</div>';
            }
        }
        ct_affiliate_note();
        ?>
    </section>
</div>
<?php
get_footer();
