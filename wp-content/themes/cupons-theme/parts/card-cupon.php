<?php
/**
 * Cardul de ofertă. Înălțime stabilă; codul se dezvăluie inline, fără layout shift.
 *
 * @var array $args ['o' => View::offer(), 'show_logo' => bool, 'h' => 'h2'|'h3']
 */

declare(strict_types=1);

use Cupons\Core\Front\Images;
use Cupons\Core\Front\View;

$o     = $args['o'];
$h     = in_array($args['h'] ?? 'h3', ['h2', 'h3'], true) ? $args['h'] : 'h3';
$store = $o['store'];
$votes = $o['votes'];
?>
<article class="card" id="<?php echo esc_attr($o['anchor']); ?>">
    <div class="card__badge card__badge--<?php echo esc_attr($o['badge']['kind']); ?>">
        <span class="card__value"><?php if (!empty($o['badge']['upto'])) : ?><small class="card__pre">până la</small><?php endif; ?><?php echo esc_html($o['badge']['text']); ?></span>
    </div>

    <div class="card__body">
        <?php if (!empty($args['show_logo']) && $store) : ?>
            <a class="card__store" href="<?php echo esc_url((string) get_term_link($store)); ?>">
                <span class="logo logo--sm"><?php echo View::logo($store, ['size' => Images::LOGO, 'px' => 72, 'alt' => '']); // phpcs:ignore ?></span>
                <?php echo esc_html($store->name); ?>
            </a>
        <?php endif; ?>
        <<?php echo $h; ?> class="card__title"><?php echo esc_html($o['title']); ?></<?php echo $h; ?>>
        <p class="card__meta">
            <?php if ($o['exclusive']) : ?><span class="pill pill--excl"><?php echo ct_icon('star', 12); ?>Exclusiv</span><?php endif; ?>
            <?php if ($o['expiry']['soon']) : ?><span class="pill pill--soon"><?php echo ct_icon('clock', 12); ?><?php echo esc_html($o['expiry']['label']); ?></span><?php endif; ?>
            <?php if ($o['verified']) : ?><span class="card__ver"><?php echo ct_icon('shield', 13); ?>Verificat pe <time datetime="<?php echo esc_attr($o['verified_ymd']); ?>"><?php echo esc_html($o['verified']); ?></time></span><?php endif; ?>
            <?php if (!$o['expiry']['soon'] && $o['expiry']['label']) : ?><span class="card__exp"><?php echo esc_html($o['expiry']['label']); ?></span><?php endif; ?>
        </p>
        <?php if ($o['terms'] !== '') : ?>
            <details class="card__terms">
                <summary>Condiții</summary>
                <p><?php echo wp_kses($o['terms'], ['a' => ['href' => true], 'strong' => [], 'em' => [], 'br' => []]); ?></p>
            </details>
        <?php endif; ?>
    </div>

    <div class="card__act">
        <?php if ($o['has_code']) : ?>
            <a class="btn btn--code" href="<?php echo esc_url($o['go']); ?>" <?php echo ct_out_attrs(); ?> data-code="<?php echo esc_attr($o['code']); ?>">
                <span class="btn__code" aria-hidden="true"><?php echo esc_html($o['masked']); ?></span>
                <span class="btn__label"><?php echo ct_icon('copy', 16); ?>Copiază codul</span>
            </a>
            <noscript><p class="card__nojs">Cod: <code><?php echo esc_html($o['code']); ?></code></p></noscript>
        <?php else : ?>
            <a class="btn btn--deal" href="<?php echo esc_url($o['go']); ?>" <?php echo ct_out_attrs(); ?>>
                <span class="btn__label">Vezi oferta<?php echo ct_icon('external', 16); ?></span>
            </a>
        <?php endif; ?>
        <p class="card__status" aria-live="polite"></p>

        <div class="vote" data-vote="<?php echo (int) $o['id']; ?>">
            <span class="vote__q">A funcționat?</span>
            <button type="button" class="vote__btn" data-v="1" aria-label="Da, a funcționat"><?php echo ct_icon('up', 15); ?>Da</button>
            <button type="button" class="vote__btn" data-v="0" aria-label="Nu a funcționat"><?php echo ct_icon('down', 15); ?>Nu</button>
            <span class="vote__rate"><?php
                if ($votes['rate'] !== null) {
                    printf('%d%% succes · %d voturi', (int) $votes['rate'], (int) $votes['total']);
                }
            ?></span>
        </div>
    </div>
</article>
