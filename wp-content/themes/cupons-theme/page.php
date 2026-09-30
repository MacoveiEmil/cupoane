<?php
/**
 * Pagini statice: articol lizibil (~70 caractere pe rând), cuprins din H2 (de la 3 secțiuni),
 * „Ultima actualizare” și autor.
 */

declare(strict_types=1);

get_header();

while (have_posts()) :
    the_post();
    $content = (string) apply_filters('the_content', get_the_content());
    ?>
    <div class="wrap">
        <?php ct_breadcrumbs(); ?>
        <article class="article">
            <header class="article__head">
                <h1><?php the_title(); ?></h1>
                <p class="article__meta">
                    Ultima actualizare: <time datetime="<?php echo esc_attr(get_the_modified_date('Y-m-d')); ?>"><?php echo esc_html(get_the_modified_date('j F Y')); ?></time>
                    · <?php echo esc_html(get_the_author()); ?>
                </p>
            </header>
            <?php echo ct_toc($content); // phpcs:ignore -- escapat în ct_toc ?>
            <div class="prose">
                <?php if (trim(wp_strip_all_tags($content)) === '') : ?>
                    <p><em>Conținut în curs de redactare.</em></p>
                <?php else :
                    echo $content; // phpcs:ignore -- the_content
                endif; ?>
            </div>
        </article>
    </div>
    <?php
endwhile;

get_footer();
