<?php
/**
 * Cardul de deal (produs anume).
 *
 * @var array $args ['d' => View::deal()]
 */

declare(strict_types=1);

use Cupons\Core\Front\Images;

$d     = $args['d'];
$store = $d['store'];
?>
<article class="deal" id="<?php echo esc_attr($d['anchor']); ?>">
    <a class="deal__img" href="<?php echo esc_url($d['go']); ?>" <?php echo ct_out_attrs(); ?> tabindex="-1" aria-hidden="true">
        <?php if ($d['image_id']) :
            echo wp_get_attachment_image($d['image_id'], Images::DEAL, false, ['loading' => 'lazy', 'decoding' => 'async', 'alt' => '', 'sizes' => '(max-width: 600px) 45vw, 220px']);
        else : ?>
            <span class="deal__ph"><?php echo ct_icon('tag', 32); ?></span>
        <?php endif; ?>
        <?php if ($d['percent'] > 0) : ?><span class="deal__pct">−<?php echo (int) $d['percent']; ?>%</span><?php endif; ?>
    </a>
    <div class="deal__body">
        <?php if ($store) : ?><a class="card__store" href="<?php echo esc_url((string) get_term_link($store)); ?>"><?php echo esc_html($store->name); ?></a><?php endif; ?>
        <h3 class="deal__title"><?php echo esc_html($d['title']); ?></h3>
        <p class="deal__price">
            <?php if ($d['price_new'] !== '') : ?><strong><?php echo esc_html($d['price_new']); ?></strong><?php endif; ?>
            <?php if ($d['price_old'] !== '') : ?><s><?php echo esc_html($d['price_old']); ?></s><?php endif; ?>
        </p>
        <?php if ($d['expiry']['label']) : ?><p class="deal__exp<?php echo $d['expiry']['soon'] ? ' is-soon' : ''; ?>"><?php echo esc_html($d['expiry']['label']); ?></p><?php endif; ?>
        <a class="btn btn--deal btn--sm" href="<?php echo esc_url($d['go']); ?>" <?php echo ct_out_attrs(); ?>><span class="btn__label">Vezi produsul<?php echo ct_icon('external', 14); ?></span></a>
    </div>
</article>
