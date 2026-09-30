<?php

declare(strict_types=1);

get_header();
?>
<div class="wrap">
    <header class="page-head">
        <h1>Pagina nu există</h1>
        <p class="page-head__sub">Poate oferta a expirat sau adresa s-a schimbat. Caută magazinul:</p>
    </header>
    <div class="search-page"><?php ct_search_form(); ?></div>
    <p><a href="<?php echo esc_url(home_url('/')); ?>">← Înapoi la prima pagină</a></p>
</div>
<?php
get_footer();
