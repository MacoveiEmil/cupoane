<?php

declare(strict_types=1);

namespace Cupons\Core\Admin;

use Cupons\Core\Content\Sync;
use Cupons\Core\Freshness\CachePurger;
use Cupons\Core\Keys;
use Cupons\Core\Support\Sanitize;

/**
 * Câmpurile magazinului (term meta). Textul descriptiv = descrierea nativă a termenului.
 */
final class StoreFields
{
    private const NONCE = 'cupons_store_nonce';

    public static function register_hooks(): void
    {
        add_action(Keys::STORE . '_add_form_fields', [self::class, 'render_add']);
        add_action(Keys::STORE . '_edit_form_fields', [self::class, 'render_edit']);
        add_action('created_' . Keys::STORE, [self::class, 'save']);
        add_action('edited_' . Keys::STORE, [self::class, 'save']);
        add_action('pre_delete_term', [self::class, 'before_delete'], 10, 2);
    }

    public static function render_add(): void
    {
        self::render(null, 'div');
    }

    public static function render_edit(\WP_Term $term): void
    {
        self::render($term, 'tr');
    }

    private static function render(?\WP_Term $term, string $layout): void
    {
        $id   = $term?->term_id ?? 0;
        $get  = static fn(string $k) => $id ? (string) get_term_meta($id, $k, true) : '';
        $info = $id ? (array) get_term_meta($id, Keys::T_INFO, true) : [];
        $faq  = $id ? (array) get_term_meta($id, Keys::T_FAQ, true) : [];
        $cats = $id ? Sync::store_categories($id) : [];

        wp_nonce_field('cupons_store_save', self::NONCE);

        self::row($layout, 'Logo', static function () use ($get) {
            Fields::media('cupons[logo]', (int) $get(Keys::T_LOGO), 'Alege logo');
            echo '<p class="description">Ideal WebP pătrat, minim 160×160 px.</p>';
        });

        self::row($layout, 'URL site', static fn() => printf(
            '<input type="url" name="cupons[url]" value="%s" class="regular-text" placeholder="https://">', esc_attr($get(Keys::T_URL))
        ));

        self::row($layout, 'Link de afiliere implicit', static fn() => printf(
            '<input type="url" name="cupons[aff]" value="%s" class="large-text" placeholder="https://"><p class="description">Folosit de „Mergi la magazin” și de ofertele fără link propriu. Dacă lipsește, se folosește URL-ul site-ului.</p>',
            esc_attr($get(Keys::T_AFF))
        ));

        self::row($layout, 'Rețea', static function () use ($get) {
            Fields::select('cupons[network]', ['' => '—'] + Keys::NETWORKS, $get(Keys::T_NETWORK), 'cupons-network');
        });

        self::row($layout, 'ID program în rețea', static fn() => printf(
            '<input type="text" name="cupons[program]" value="%s" class="regular-text">', esc_attr($get(Keys::T_PROGRAM))
        ));

        self::row($layout, 'Categorii', static function () use ($cats) {
            self::category_checklist($cats);
        });

        self::row($layout, 'Ordine pe homepage', static fn() => printf(
            '<input type="number" min="0" max="999" name="cupons[home_order]" value="%s" class="small-text"><p class="description">0 = nu apare la „Magazine populare”. 1 = primul.</p>',
            esc_attr($get(Keys::T_HOME_ORDER) ?: '0')
        ));

        self::row($layout, 'Informații utile', static function () use ($info) {
            echo '<p class="description">Se afișează doar câmpurile completate.</p><div class="cupons-grid">';
            foreach (Keys::INFO_FIELDS as $key => $label) {
                printf(
                    '<label>%s<input type="text" name="cupons[info][%s]" value="%s" class="widefat"></label>',
                    esc_html($label), esc_attr($key), esc_attr((string) ($info[$key] ?? ''))
                );
            }
            echo '</div>';
        });

        self::row($layout, 'Întrebări frecvente', static function () use ($faq) {
            $rows = $faq ?: [['q' => '', 'a' => '']];
            echo '<div class="cupons-faq" data-cupons-repeater>';
            foreach ($rows as $row) {
                self::faq_row((string) ($row['q'] ?? ''), (string) ($row['a'] ?? ''));
            }
            echo '<template>';
            self::faq_row('', '');
            echo '</template>';
            echo '<button type="button" class="button" data-cupons-add>+ Adaugă întrebare</button></div>';
        });
    }

    private static function faq_row(string $q, string $a): void
    {
        printf(
            '<div class="cupons-faq__row"><input type="text" name="cupons[faq_q][]" value="%s" class="widefat" placeholder="Întrebare"><textarea name="cupons[faq_a][]" rows="2" class="widefat" placeholder="Răspuns">%s</textarea><button type="button" class="button-link-delete" data-cupons-remove>Șterge</button></div>',
            esc_attr($q), esc_textarea($a)
        );
    }

    /** @param int[] $checked */
    private static function category_checklist(array $checked): void
    {
        $parents = get_terms(['taxonomy' => Keys::CATEGORY, 'hide_empty' => false, 'parent' => 0]);
        if (!is_array($parents) || !$parents) {
            echo '<p class="description">Nu există categorii încă.</p>';
            return;
        }
        echo '<ul class="cupons-checklist">';
        foreach ($parents as $p) {
            printf('<li><label><input type="checkbox" name="cupons[cats][]" value="%d"%s> <strong>%s</strong></label>', $p->term_id, checked(in_array($p->term_id, $checked, true), true, false), esc_html($p->name));
            $children = get_terms(['taxonomy' => Keys::CATEGORY, 'hide_empty' => false, 'parent' => $p->term_id]);
            if (is_array($children) && $children) {
                echo '<ul>';
                foreach ($children as $c) {
                    printf('<li><label><input type="checkbox" name="cupons[cats][]" value="%d"%s> %s</label></li>', $c->term_id, checked(in_array($c->term_id, $checked, true), true, false), esc_html($c->name));
                }
                echo '</ul>';
            }
            echo '</li>';
        }
        echo '</ul>';
    }

    private static function row(string $layout, string $label, callable $field): void
    {
        if ($layout === 'tr') {
            echo '<tr class="form-field"><th scope="row">' . esc_html($label) . '</th><td>';
            $field();
            echo '</td></tr>';
            return;
        }
        echo '<div class="form-field"><label>' . esc_html($label) . '</label>';
        $field();
        echo '</div>';
    }

    public static function save(int $term_id): void
    {
        if (!isset($_POST[self::NONCE]) || !wp_verify_nonce(sanitize_key(wp_unslash($_POST[self::NONCE])), 'cupons_store_save')) {
            return;
        }
        if (!current_user_can('edit_term', $term_id)) {
            return;
        }

        // phpcs:ignore WordPress.Security.ValidatedSanitizedInput -- fiecare câmp e sanitizat mai jos.
        $in = isset($_POST['cupons']) && is_array($_POST['cupons']) ? wp_unslash($_POST['cupons']) : [];

        $logo = absint($in['logo'] ?? 0);
        update_term_meta($term_id, Keys::T_LOGO, ($logo && wp_attachment_is_image($logo)) ? $logo : '');
        update_term_meta($term_id, Keys::T_URL, Sanitize::url($in['url'] ?? ''));
        update_term_meta($term_id, Keys::T_AFF, Sanitize::url($in['aff'] ?? ''));
        update_term_meta($term_id, Keys::T_NETWORK, Sanitize::enum($in['network'] ?? '', array_keys(Keys::NETWORKS), ''));
        update_term_meta($term_id, Keys::T_PROGRAM, sanitize_text_field((string) ($in['program'] ?? '')));
        update_term_meta($term_id, Keys::T_HOME_ORDER, (string) min(999, absint($in['home_order'] ?? 0)));

        $info = [];
        foreach (array_keys(Keys::INFO_FIELDS) as $key) {
            $val = sanitize_text_field((string) ($in['info'][$key] ?? ''));
            if ($val !== '') {
                $info[$key] = $val;
            }
        }
        update_term_meta($term_id, Keys::T_INFO, $info);

        $faq = [];
        $qs  = (array) ($in['faq_q'] ?? []);
        $as  = (array) ($in['faq_a'] ?? []);
        foreach ($qs as $i => $q) {
            $q = sanitize_text_field((string) $q);
            $a = Sanitize::rich((string) ($as[$i] ?? ''));
            if ($q !== '' && $a !== '') {
                $faq[] = ['q' => $q, 'a' => $a];
            }
        }
        update_term_meta($term_id, Keys::T_FAQ, $faq);

        $old  = Sync::store_categories($term_id);
        $cats = array_values(array_unique(array_filter(array_map('absint', (array) ($in['cats'] ?? [])),
            static fn(int $id) => $id > 0 && term_exists($id, Keys::CATEGORY))));
        delete_term_meta($term_id, Keys::T_CATS);
        foreach ($cats as $cat) {
            add_term_meta($term_id, Keys::T_CATS, $cat);
        }
        sort($old);
        sort($cats);
        if ($old !== $cats) {
            Sync::resync_store($term_id);
            foreach (array_merge($old, $cats) as $cat_id) {
                $cat = get_term($cat_id, Keys::CATEGORY);
                if ($cat instanceof \WP_Term) {
                    CachePurger::instance()->add_term($cat);
                }
            }
        }

        Sync::touch_store($term_id);
        do_action('cupons_store_saved', $term_id);

        $term = get_term($term_id, Keys::STORE);
        if ($term instanceof \WP_Term) {
            CachePurger::instance()->add_term($term);
        }
    }

    public static function before_delete(int $term_id, string $taxonomy): void
    {
        if ($taxonomy === Keys::STORE) {
            CachePurger::instance()->add_all();
        }
    }
}
