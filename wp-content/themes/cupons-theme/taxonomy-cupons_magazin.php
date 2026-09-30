<?php
/**
 * Pagina de magazin (cea mai importantă).
 * Header → oferte (exclusive → verificate recent → restul) → deal-uri → informații utile → descriere → FAQ → similare.
 * Fără secțiune de oferte expirate.
 */

declare(strict_types=1);

use Cupons\Core\Front\Images;
use Cupons\Core\Front\View;

get_header();

$term    = get_queried_object();
$s       = View::store($term);
$posts   = $GLOBALS['wp_query']->posts;
$deals   = View::store_deals($term);
$similar = View::similar_stores($term, 6);
?>
<div class="wrap">
    <?php ct_breadcrumbs(); ?>

    <header class="store">
        <span class="logo logo--lg"><?php echo View::logo($term, ['size' => Images::LOGO, 'px' => 96, 'lazy' => false, 'priority' => true]); // phpcs:ignore ?></span>
        <div class="store__main">
            <h1 class="store__title">Cod reducere <?php echo esc_html($s['name']); ?></h1>
            <p class="store__meta">
                <strong><?php echo esc_html(ct_offers_label($s['count'])); ?></strong>
                <?php if ($s['updated']) : ?>
                    <span><?php echo ct_icon('clock', 14); ?> Actualizat la <time datetime="<?php echo esc_attr($s['updated_ymd']); ?>"><?php echo esc_html($s['updated']); ?></time></span>
                <?php endif; ?>
            </p>
        </div>
        <a class="btn btn--primary store__go" href="<?php echo esc_url($s['go']); ?>" <?php echo ct_out_attrs(); ?>><span class="btn__label">Mergi la magazin<?php echo ct_icon('external', 16); ?></span></a>
    </header>

    <section class="sec" aria-labelledby="s-offers">
        <h2 id="s-offers" class="sr">Oferte <?php echo esc_html($s['name']); ?></h2>
        <?php if ($posts) : ?>
            <?php ct_offer_list($posts, false, 'h3'); ?>
        <?php else : ?>
            <div class="empty">
                <p><strong>Momentan nu avem coduri active pentru <?php echo esc_html($s['name']); ?>.</strong></p>
                <p>Ofertele expirate sunt scoase automat, așa că aici apar doar coduri valabile. Revino în curând sau încearcă un magazin similar.</p>
            </div>
            <?php if ($similar) : ?>
                <div class="tiles"><?php foreach ($similar as $t) {
                    ct_store_tile($t, true, true);
                } ?></div>
            <?php endif; ?>
        <?php endif; ?>
    </section>

    <?php if ($deals) : ?>
        <section class="sec" aria-labelledby="s-deals">
            <?php ct_section_head('Deal-uri ' . $s['name'], '', 's-deals'); ?>
            <div class="deals"><?php foreach ($deals as $p) {
                ct_deal_card($p);
            } ?></div>
        </section>
    <?php endif; ?>

    <?php if ($s['info']) : ?>
        <section class="sec" aria-labelledby="s-info">
            <h2 id="s-info">Informații utile</h2>
            <dl class="info">
                <?php foreach ($s['info'] as $row) : ?>
                    <div><dt><?php echo esc_html($row['label']); ?></dt><dd><?php echo esc_html($row['value']); ?></dd></div>
                <?php endforeach; ?>
            </dl>
        </section>
    <?php endif; ?>

    <?php if (trim(wp_strip_all_tags($s['description'])) !== '') : ?>
        <section class="sec prose box" aria-labelledby="s-about">
            <h2 id="s-about">Despre <?php echo esc_html($s['name']); ?></h2>
            <?php echo wp_kses_post($s['description']); ?>
        </section>
    <?php endif; ?>

    <?php if ($s['faq']) : ?>
        <section class="sec" aria-labelledby="s-faq">
            <h2 id="s-faq">Întrebări frecvente</h2>
            <div class="faq box">
                <?php foreach ($s['faq'] as $row) : ?>
                    <details>
                        <summary><?php echo esc_html((string) $row['q']); ?></summary>
                        <p><?php echo wp_kses((string) $row['a'], ['a' => ['href' => true], 'strong' => [], 'em' => [], 'br' => []]); ?></p>
                    </details>
                <?php endforeach; ?>
            </div>
        </section>
    <?php endif; ?>

    <?php if ($posts && $similar) : ?>
        <section class="sec" aria-labelledby="s-sim">
            <h2 id="s-sim">Magazine similare</h2>
            <div class="tiles"><?php foreach ($similar as $t) {
                ct_store_tile($t, true, true);
            } ?></div>
        </section>
    <?php endif; ?>
</div>
<?php
get_footer();
