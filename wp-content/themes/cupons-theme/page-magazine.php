<?php
/**
 * /magazine/ — index A–Z.
 */

declare(strict_types=1);

use Cupons\Core\Front\View;

get_header();

$groups = ct_ready() ? View::stores_az() : [];
?>
<div class="wrap">
    <?php ct_breadcrumbs(); ?>
    <header class="page-head">
        <h1>Magazine A–Z</h1>
        <p class="page-head__sub">Toate magazinele cu vouchere, reduceri și cupoane. Numărul arată ofertele active azi.</p>
    </header>

    <?php if ($groups) : ?>
        <nav class="az" aria-label="Litere">
            <?php foreach (array_keys($groups) as $letter) : ?>
                <a href="#l-<?php echo esc_attr(sanitize_title($letter)); ?>"><?php echo esc_html($letter); ?></a>
            <?php endforeach; ?>
        </nav>
        <?php foreach ($groups as $letter => $stores) : ?>
            <section class="az__group" id="l-<?php echo esc_attr(sanitize_title($letter)); ?>">
                <h2><?php echo esc_html($letter); ?></h2>
                <ul class="az__list">
                    <?php foreach ($stores as $row) : ?>
                        <li>
                            <a href="<?php echo esc_url((string) get_term_link($row['term'])); ?>"><?php echo esc_html($row['term']->name); ?></a>
                            <span class="az__n"><?php echo (int) $row['count']; ?></span>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </section>
        <?php endforeach; ?>
    <?php else : ?>
        <p class="empty">Nu există magazine încă.</p>
    <?php endif; ?>
</div>
<?php
get_footer();
