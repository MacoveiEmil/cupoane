</main>
<?php
use Cupons\Core\Content\Pages;
use Cupons\Core\Front\View;

$ct_links = static function (array $keys): void {
    foreach ($keys as $key) {
        $url = ct_page_url($key);
        if ($url !== '') {
            printf('<li><a href="%s">%s</a></li>', esc_url($url), esc_html(get_the_title(Pages::id($key))));
        }
    }
};
?>
<footer class="ftr">
    <div class="wrap">
        <div class="ftr__grid">
            <div class="ftr__about">
                <a class="brand" href="<?php echo esc_url(home_url('/')); ?>">
                    <span class="brand__mark" aria-hidden="true"><?php echo ct_icon('tag', 20); ?></span>
                    <span class="brand__name"><?php bloginfo('name'); ?></span>
                </a>
                <p>Vouchere, reduceri și cupoane pentru magazinele online din România. Verificăm ofertele zilnic și le scoatem automat pe cele expirate.</p>
                <?php ct_affiliate_note(); ?>
            </div>

            <?php if (ct_ready()) : ?>
                <nav aria-labelledby="f-disc">
                    <h2 id="f-disc">Descoperă</h2>
                    <ul>
                        <?php foreach (ct_menu_items() as $item) : ?>
                            <li><a href="<?php echo esc_url($item['url']); ?>"><?php echo esc_html($item['label']); ?></a></li>
                        <?php endforeach; ?>
                        <?php $ct_links(['cum', 'metodologie', 'calendar']); ?>
                    </ul>
                </nav>

                <?php $ftr_stores = View::popular_stores(7); ?>
                <?php if ($ftr_stores) : ?>
                    <nav aria-labelledby="f-stores">
                        <h2 id="f-stores">Magazine populare</h2>
                        <ul>
                            <?php foreach ($ftr_stores as $t) : ?>
                                <li><a href="<?php echo esc_url((string) get_term_link($t)); ?>">Cod reducere <?php echo esc_html($t->name); ?></a></li>
                            <?php endforeach; ?>
                        </ul>
                    </nav>
                <?php endif; ?>

                <nav aria-labelledby="f-info">
                    <h2 id="f-info">Informații</h2>
                    <ul><?php $ct_links(['despre', 'transparenta', 'contact', 'termeni', 'confidentialitate', 'cookies']); ?></ul>
                </nav>
            <?php endif; ?>
        </div>
        <div class="ftr__bottom">© <?php echo esc_html(wp_date('Y')); ?> <?php bloginfo('name'); ?> · Vouchere, reduceri și cupoane verificate</div>
    </div>
</footer>
<?php wp_footer(); ?>
</body>
</html>
