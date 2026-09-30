<?php
/**
 * /categorii/ — categoriile cu numărul de magazine și de oferte active.
 */

declare(strict_types=1);

use Cupons\Core\Front\View;

get_header();

$tree = ct_ready() ? View::categories_tree() : [];
?>
<div class="wrap">
    <?php ct_breadcrumbs(); ?>
    <header class="page-head">
        <h1>Categorii</h1>
        <p class="page-head__sub">Vouchere, reduceri și cupoane grupate pe tipuri de magazine.</p>
    </header>

    <div class="cat-grid">
        <?php foreach ($tree as $c) : ?>
            <section class="cat-box">
                <h2><a href="<?php echo esc_url($c['url']); ?>"><?php echo esc_html($c['term']->name); ?></a></h2>
                <p class="cats__n"><?php echo esc_html(ct_plural((int) $c['stores'], 'magazin', 'magazine')); ?> · <?php echo esc_html(ct_offers_label($c['offers'])); ?></p>
                <?php if ($c['children']) : ?>
                    <ul>
                        <?php foreach ($c['children'] as $ch) : ?>
                            <li><a href="<?php echo esc_url($ch['url']); ?>"><?php echo esc_html($ch['term']->name); ?></a> <span class="cats__n"><?php echo (int) $ch['offers']; ?></span></li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </section>
        <?php endforeach; ?>
    </div>
</div>
<?php
get_footer();
