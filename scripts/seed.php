<?php
/**
 * Date demo: 20 magazine, 60 cupoane, 10 deal-uri, inclusiv expirate și neverificate
 * (pentru testarea curățării). Rulează prin WP-CLI: wp eval-file scripts/seed.php [reset]
 *
 * Magazinele sunt FICTIVE (domenii example.com): nu copiem conținutul altor site-uri.
 * Toate obiectele demo au meta _cupons_seed=1 și pot fi șterse cu argumentul „reset”.
 */

// Fără declare(strict_types): wp eval-file rulează fișierul prin eval().

use Cupons\Core\Keys;
use Cupons\Core\Content\Sync;
use Cupons\Core\Support\Dates;

if (!class_exists(Keys::class)) {
    WP_CLI::error('Activează pluginul cupons-core înainte de seed.');
}

$reset = in_array('reset', $args ?? [], true);

// Șterge datele demo existente (idempotent).
$old = get_posts(['post_type' => Keys::offer_types(), 'post_status' => 'any', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => Keys::M_SEED, 'cupons_all' => true]);
$trash = get_posts(['post_type' => Keys::offer_types(), 'post_status' => 'trash', 'posts_per_page' => -1, 'fields' => 'ids', 'meta_key' => Keys::M_SEED, 'cupons_all' => true]);
foreach (array_merge($old, $trash) as $id) {
    wp_delete_post((int) $id, true);
}
foreach (get_terms(['taxonomy' => [Keys::STORE, Keys::CATEGORY], 'hide_empty' => false, 'meta_key' => Keys::M_SEED]) as $t) {
    wp_delete_term($t->term_id, $t->taxonomy);
}
if ($reset) {
    WP_CLI::success('Datele demo au fost șterse.');
    return;
}

update_option('timezone_string', 'Europe/Bucharest');
update_option('date_format', 'j F Y');
update_option('permalink_structure', '/%postname%/');
\Cupons\Core\Freshness\Scheduler::reschedule(); // la 00:05 în noul fus orar

// Categorii (2 niveluri)
$tree = [
    'Fashion'             => ['Îmbrăcăminte', 'Încălțăminte'],
    'Electronice & IT'    => ['Telefoane', 'Laptopuri'],
    'Casă & Grădină'      => ['Mobilă', 'Bricolaj'],
    'Sănătate & Frumusețe'=> ['Cosmetice', 'Farmacie'],
    'Sport & Outdoor'     => [],
    'Copii & Jucării'     => [],
    'Cărți & Papetărie'   => [],
    'Călătorii'           => [],
];
$cat_ids = [];
foreach ($tree as $parent => $children) {
    $p = wp_insert_term($parent, Keys::CATEGORY);
    $pid = is_wp_error($p) ? (int) $p->get_error_data() : (int) $p['term_id'];
    add_term_meta($pid, Keys::M_SEED, '1');
    $cat_ids[$parent] = $pid;
    foreach ($children as $child) {
        $c = wp_insert_term($child, Keys::CATEGORY, ['parent' => $pid]);
        $cid = is_wp_error($c) ? (int) $c->get_error_data() : (int) $c['term_id'];
        add_term_meta($cid, Keys::M_SEED, '1');
        $cat_ids[$child] = $cid;
    }
}

// 20 magazine fictive
$stores = [
    ['Moda Urbană', 'Îmbrăcăminte', '2performant'],
    ['PasUșor', 'Încălțăminte', 'profitshare'],
    ['TechNova', 'Laptopuri', '2performant'],
    ['FonMax', 'Telefoane', 'profitshare'],
    ['CasaVerde', 'Bricolaj', '2performant'],
    ['Mobila Bună', 'Mobilă', 'direct'],
    ['Frumos Natural', 'Cosmetice', 'profitshare'],
    ['FarmaPlus', 'Farmacie', '2performant'],
    ['SportLand', 'Sport & Outdoor', 'profitshare'],
    ['Jucăria Veselă', 'Copii & Jucării', '2performant'],
    ['Librăria Pagina', 'Cărți & Papetărie', 'profitshare'],
    ['Vacanța Mea', 'Călătorii', '2performant'],
    ['Stil Clasic', 'Îmbrăcăminte', '2performant'],
    ['Gadget Hub', 'Electronice & IT', 'profitshare'],
    ['Grădina Mea', 'Casă & Grădină', 'direct'],
    ['Beauty Box', 'Cosmetice', '2performant'],
    ['Alergare Pro', 'Sport & Outdoor', 'profitshare'],
    ['Bebe Confort', 'Copii & Jucării', '2performant'],
    ['Office Store', 'Cărți & Papetărie', 'direct'],
    ['Fashion Outlet', 'Fashion', 'profitshare'],
];

$store_ids = [];
foreach ($stores as $i => [$name, $cat, $network]) {
    $slug = sanitize_title($name);
    $res  = wp_insert_term($name, Keys::STORE, [
        'slug'        => $slug,
        'description' => "{$name} este un magazin online demo, folosit pentru testarea site-ului. Textul descriptiv real îl adaugi din admin.",
    ]);
    if (is_wp_error($res)) {
        WP_CLI::warning($res->get_error_message());
        continue;
    }
    $tid = (int) $res['term_id'];
    $store_ids[] = $tid;
    add_term_meta($tid, Keys::M_SEED, '1');
    update_term_meta($tid, Keys::T_URL, "https://{$slug}.example.com/");
    update_term_meta($tid, Keys::T_AFF, "https://{$slug}.example.com/?utm_source=demo");
    update_term_meta($tid, Keys::T_NETWORK, $network);
    update_term_meta($tid, Keys::T_PROGRAM, (string) (1000 + $i));
    update_term_meta($tid, Keys::T_HOME_ORDER, (string) ($i < 12 ? $i + 1 : 0));
    add_term_meta($tid, Keys::T_CATS, $cat_ids[$cat]);
    update_term_meta($tid, Keys::T_INFO, array_filter([
        'transport'      => $i % 2 ? 'Curier rapid, 1–3 zile lucrătoare' : 'Curier, 2–4 zile lucrătoare',
        'prag_transport' => $i % 3 ? '199 lei' : '',
        'retur'          => '30 de zile',
        'plata'          => 'Card, ramburs',
        'prima_comanda'  => $i % 4 === 0 ? '10% cu abonare la newsletterul magazinului' : '',
    ]));
    update_term_meta($tid, Keys::T_FAQ, [
        ['q' => "Cum folosesc un cod de reducere {$name}?", 'a' => 'Copiază codul, adaugă produsele în coș și introdu codul în câmpul dedicat la finalizarea comenzii.'],
        ['q' => "Cât costă transportul la {$name}?", 'a' => 'Vezi secțiunea „Informații utile” de mai sus; condițiile se pot schimba, verifică pe site-ul magazinului.'],
    ]);
    update_term_meta($tid, Keys::T_UPDATED, Dates::today());
}

$tags = [];
foreach (Keys::DEFAULT_TAGS as $slug => $label) {
    $t = get_term_by('slug', $slug, Keys::TAG);
    if ($t) {
        $tags[] = $t->term_id;
    }
}

$today = Dates::today();
$create = static function (string $type, string $title, int $store, array $meta, array $tag_ids = []) {
    $id = wp_insert_post([
        'post_type'   => $type,
        'post_title'  => $title,
        'post_status' => 'publish',
        'meta_input'  => $meta + [Keys::M_SEED => '1'],
    ], true);
    if (is_wp_error($id)) {
        WP_CLI::warning($id->get_error_message());
        return 0;
    }
    wp_set_object_terms($id, [$store], Keys::STORE);
    if ($tag_ids) {
        wp_set_object_terms($id, $tag_ids, Keys::TAG);
    }
    // wp_insert_post a rulat înainte de setarea magazinului: resincronizăm categoriile.
    Sync::sync_categories($id);
    \Cupons\Core\Content\Normalizer::normalize($id);
    return $id;
};

// 60 cupoane: 3 per magazin
$stats = ['active' => 0, 'expired' => 0, 'stale' => 0];
$n = 0;
foreach ($store_ids as $si => $store) {
    for ($k = 0; $k < 3; $k++, $n++) {
        $pct   = [10, 15, 20, 25, 30, 40][$n % 6];
        $code  = ($n % 4 === 3) ? '' : strtoupper(substr(sanitize_title(get_term($store)->name), 0, 4)) . $pct . chr(65 + $k);
        $kind  = $n % 7 === 0 ? 'transport' : ($n % 5 === 0 ? 'lei' : 'procent');
        $value = $kind === 'lei' ? (string) (25 + $n % 4 * 25) : ($kind === 'transport' ? '' : (string) $pct);
        $title = match ($kind) {
            'transport' => 'Transport gratuit la orice comandă',
            'lei'       => "{$value} lei reducere la comenzi de peste 200 lei",
            default     => "{$pct}% reducere " . ($k === 0 ? 'la toate produsele' : ($k === 1 ? 'la colecția nouă' : 'la produsele selectate')),
        };

        $meta = [
            Keys::M_CODE       => $code,
            Keys::M_VALUE      => $value,
            Keys::M_VALUE_TYPE => $kind,
            Keys::M_TERMS      => 'Nu se cumulează cu alte promoții. Valabil o singură dată per client.',
            Keys::M_EXCLUSIVE  => $n % 9 === 0 ? '1' : '0',
            Keys::M_TOP        => $n % 8 === 0 ? '1' : '0',
            Keys::M_VERIFIED   => Dates::offset(-($n % 10)),
        ];

        // Scenarii de test pentru curățare:
        if ($n % 8 === 5) {                 // 7-8 expirate (ieri sau mai demult)
            $meta[Keys::M_EXPIRES] = Dates::offset(-1 - ($n % 3) * 5);
            $stats['expired']++;
        } elseif ($n % 11 === 4) {          // fără expirare și neverificate de 45 de zile
            $meta[Keys::M_VERIFIED] = Dates::offset(-45);
            $stats['stale']++;
        } elseif ($n % 3 === 0) {           // fără expirare, verificate recent
            $stats['active']++;
        } else {                            // expiră în viitor (unele mâine)
            $meta[Keys::M_EXPIRES] = Dates::offset($n % 6 === 1 ? 1 : 3 + $n % 25);
            $stats['active']++;
        }

        $tag_ids = [];
        if ($kind === 'transport') {
            $tag_ids[] = get_term_by('slug', 'transport-gratuit', Keys::TAG)->term_id;
        }
        if ($n % 6 === 2) {
            $tag_ids[] = get_term_by('slug', 'prima-comanda', Keys::TAG)->term_id;
        }
        $create(Keys::COUPON, $title, $store, $meta, $tag_ids);
    }
}

// 10 deal-uri (2 expirate)
$products = ['Căști wireless', 'Pantofi sport', 'Rucsac urban', 'Cremă hidratantă', 'Mouse ergonomic', 'Set pahare', 'Minge fotbal', 'Carte de bucate', 'Lampă de birou', 'Geacă de iarnă'];
foreach ($products as $i => $name) {
    $old = 100 + $i * 45;
    $new = (int) round($old * (0.6 + ($i % 4) * 0.08));
    $meta = [
        Keys::M_PRODUCT_URL => 'https://' . get_term($store_ids[$i])->slug . '.example.com/produs-' . ($i + 1),
        Keys::M_PRICE_OLD   => (string) $old,
        Keys::M_PRICE_NEW   => (string) $new,
        Keys::M_VERIFIED    => $today,
        Keys::M_EXPIRES     => $i >= 8 ? Dates::offset(-2) : Dates::offset(5 + $i),
    ];
    $create(Keys::DEAL, $name, $store_ids[$i], $meta);
}

flush_rewrite_rules(false);

WP_CLI::success(sprintf(
    'Seed complet: %d magazine, %d cupoane (%d active, %d expirate, %d neverificate de 45 zile), 10 deal-uri (2 expirate).',
    count($store_ids), $n, $stats['active'], $stats['expired'], $stats['stale']
));
WP_CLI::log('Testează curățarea: wp cupons cleanup --dry-run, apoi wp cupons cleanup');

// Local: Rank Math scrie meta abia după wizard sau „skip registration”.
update_option('rank_math_registration_skip', 1);
