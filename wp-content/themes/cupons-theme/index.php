<?php
/**
 * Fallback. Site-ul nu folosește articole de blog; afișăm o listă simplă dacă există.
 */

declare(strict_types=1);

get_header();
?>
<div class="wrap">
    <?php ct_breadcrumbs(); ?>
    <header class="page-head"><h1><?php echo esc_html(wp_strip_all_tags(get_the_archive_title() ?: get_bloginfo('name'))); ?></h1></header>
    <?php if (have_posts()) : ?>
        <ul class="plain-list">
            <?php while (have_posts()) : the_post(); ?>
                <li><a href="<?php the_permalink(); ?>"><?php the_title(); ?></a></li>
            <?php endwhile; ?>
        </ul>
        <?php the_posts_pagination(); ?>
    <?php else : ?>
        <p class="empty">Nu am găsit nimic aici.</p>
    <?php endif; ?>
</div>
<?php
get_footer();
