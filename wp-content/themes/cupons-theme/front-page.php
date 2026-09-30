<?php
/**
 * Homepage: titlu + căutare, magazine populare (3 carduri mari), top oferte, exclusive, recente,
 * deal-uri, categorii, text SEO. Fără carusele. Fiecare secțiune are „Vezi toate”.
 */

declare(strict_types=1);

use Cupons\Core\Front\Images;
use Cupons\Core\Front\View;

get_header();

if (!ct_ready()) {
    echo '<div class="wrap"><p>Site în configurare.</p></div>';
    get_footer();
    return;
}

$popular   = View::popular_stores(3); // primele 3 din „Ordine pe homepage”
$top       = View::top_offers(8);
$exclusive = View::exclusive_offers(6, wp_list_pluck($top, 'ID'));
$recent    = View::recent_offers(8, array_merge(wp_list_pluck($top, 'ID'), wp_list_pluck($exclusive, 'ID')));
$deals     = View::deals_query(1, 6);
$cats      = View::categories_tree();
$top_url   = ct_page_url('top-oferte');
$stats     = View::stats();
?>
<section class="intro">
    <div class="wrap">
        <h1 class="intro__title">Vouchere, reduceri și <span>cupoane verificate</span></h1>
        <p class="intro__sub"><strong><?php echo (int) $stats['offers']; ?></strong> oferte active la <strong><?php echo (int) $stats['stores']; ?></strong> magazine · actualizat azi, <?php echo esc_html(wp_date('j F')); ?></p>
        <?php ct_search_form(true); ?>
    </div>
</section>

<div class="wrap">
    <?php if ($popular) : ?>
        <section class="sec sec--first" aria-labelledby="s-pop">
            <?php ct_section_head('Magazine populare', ct_page_url('magazine'), 's-pop'); ?>
            <div class="feat">
                <?php foreach ($popular as $store) :
                    $sd   = View::store($store);
                    $best = View::best_offer_label($store); ?>
                    <a class="feat__card" href="<?php echo esc_url($sd['url']); ?>">
                        <span class="logo logo--xl"><?php echo View::logo($store, ['size' => Images::LOGO, 'px' => 88, 'lazy' => false]); // phpcs:ignore ?></span>
                        <span class="feat__body">
                            <span class="feat__name"><?php echo esc_html($store->name); ?></span>
                            <span class="feat__count"><?php echo esc_html(ct_offers_label($sd['count'])); ?></span>
                            <?php if ($best !== '') : ?><span class="feat__deal"><?php echo esc_html($best); ?></span><?php endif; ?>
                        </span>
                        <span class="feat__cta">Vezi codurile <?php echo ct_icon('chevron', 16); ?></span>
                    </a>
                <?php endforeach; ?>
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
