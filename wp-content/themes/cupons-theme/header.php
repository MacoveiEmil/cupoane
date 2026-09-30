<!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo('charset'); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="theme-color" content="#14112e">
<?php wp_head(); ?>
</head>
<body <?php body_class(); ?> data-api="<?php echo esc_url(rest_url('cupons/v1/vote')); ?>">
<?php wp_body_open(); ?>
<a class="skip" href="#main">Sari la conținut</a>
<?php
$ct_menu = static function (string $label): void {
    echo '<nav aria-label="' . esc_attr($label) . '"><ul class="nav__list">';
    foreach (ct_menu_items() as $item) {
        printf(
            '<li><a href="%s"%s>%s%s</a></li>',
            esc_url($item['url']),
            ct_is_current($item['url']) ? ' aria-current="page"' : '',
            ct_icon($item['icon'], 18),
            esc_html($item['label'])
        );
    }
    echo '</ul></nav>';
};
?>
<header class="hdr">
    <div class="wrap hdr__in">
        <a class="brand" href="<?php echo esc_url(home_url('/')); ?>" <?php echo is_front_page() ? 'aria-current="page"' : ''; ?>>
            <?php if (has_custom_logo()) :
                echo wp_get_attachment_image((int) get_theme_mod('custom_logo'), 'full', false, ['class' => 'brand__img', 'alt' => get_bloginfo('name'), 'loading' => 'eager', 'decoding' => 'async']);
            else : ?>
                <span class="brand__mark" aria-hidden="true"><?php echo ct_icon('tag', 20); ?></span>
                <span class="brand__name"><?php bloginfo('name'); ?></span>
            <?php endif; ?>
        </a>

        <div class="nav nav--d"><?php $ct_menu('Principal'); ?></div>
        <?php if (!is_front_page()) : ?>
            <div class="hdr__search"><?php ct_search_form(); ?></div>
        <?php endif; ?>

        <details class="nav nav--m" data-menu>
            <summary class="nav__toggle" aria-label="Meniu"><?php echo ct_icon('menu', 22) . ct_icon('x', 22); ?><span>Meniu</span></summary>
            <div class="nav__panel">
                <?php $ct_menu('Principal (mobil)'); ?>
                <?php if (!is_front_page()) : ?>
                    <div class="nav__search"><?php ct_search_form(); ?></div>
                <?php endif; ?>
            </div>
        </details>
    </div>
</header>
<main id="main" class="main">
