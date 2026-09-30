<?php

declare(strict_types=1);

namespace Cupons\Core\Admin;

/** Mici helperi de randare pentru formularele din admin (totul escapat). */
final class Fields
{
    public static function media(string $name, int $attachment_id, string $button = 'Alege imaginea'): void
    {
        $src = $attachment_id ? wp_get_attachment_image_url($attachment_id, 'thumbnail') : '';
        ?>
        <div class="cupons-media" data-cupons-media>
            <img src="<?php echo esc_url((string) $src); ?>" alt="" class="cupons-media__preview" <?php echo $src ? '' : 'hidden'; ?>>
            <input type="hidden" name="<?php echo esc_attr($name); ?>" value="<?php echo esc_attr((string) ($attachment_id ?: '')); ?>">
            <button type="button" class="button" data-cupons-media-pick><?php echo esc_html($button); ?></button>
            <button type="button" class="button-link" data-cupons-media-clear <?php echo $attachment_id ? '' : 'hidden'; ?>>Elimină</button>
        </div>
        <?php
    }

    /** @param array<string,string> $options */
    public static function select(string $name, array $options, string $current, string $id = ''): void
    {
        printf('<select name="%s" id="%s">', esc_attr($name), esc_attr($id ?: $name));
        foreach ($options as $value => $label) {
            printf('<option value="%s"%s>%s</option>', esc_attr((string) $value), selected($current, (string) $value, false), esc_html($label));
        }
        echo '</select>';
    }

    /** Lista de magazine pentru select (ID => nume). */
    public static function store_options(): array
    {
        $terms = get_terms(['taxonomy' => \Cupons\Core\Keys::STORE, 'hide_empty' => false, 'orderby' => 'name']);
        $out   = ['' => '— Alege magazinul —'];
        if (is_array($terms)) {
            foreach ($terms as $t) {
                $out[(string) $t->term_id] = $t->name;
            }
        }
        return $out;
    }

    /** Checkbox-uri pentru etichete. @param int[] $checked */
    public static function tag_checkboxes(string $name, array $checked): void
    {
        $terms = get_terms(['taxonomy' => \Cupons\Core\Keys::TAG, 'hide_empty' => false]);
        if (!is_array($terms)) {
            return;
        }
        foreach ($terms as $t) {
            printf(
                '<label class="cupons-inline"><input type="checkbox" name="%s[]" value="%d"%s> %s</label>',
                esc_attr($name), (int) $t->term_id, checked(in_array($t->term_id, $checked, true), true, false), esc_html($t->name)
            );
        }
    }
}
