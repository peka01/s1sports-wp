<?php
/**
 * A small image-gallery field for ACF (the built-in gallery field needs ACF PRO).
 * Stores the chosen media library images as comma-separated attachment IDs.
 * Loaded from the acf/include_field_types hook in events.php.
 */
defined('ABSPATH') || exit;

class S1_ACF_Field_Gallery extends acf_field {
    public function initialize(): void {
        $this->name = 's1_gallery';
        $this->label = 'Bildspel (S1)';
        $this->category = 'content';
        $this->defaults = ['button_label' => 'Lägg till bilder'];
    }

    public static function ids($value): array {
        $raw = is_array($value) ? $value : explode(',', (string) $value);
        $ids = array_values(array_unique(array_filter(array_map('absint', $raw))));
        return array_values(array_filter($ids, static fn(int $id): bool => wp_attachment_is_image($id)));
    }

    public function render_field($field): void {
        $ids = self::ids($field['value'] ?? '');
        echo '<div class="s1-gallery-box' . ($ids ? ' has-images' : '') . '">';
        echo '<ul class="s1-gallery-list">';
        foreach ($ids as $id) {
            echo '<li data-id="' . (int) $id . '">' . wp_get_attachment_image($id, 'thumbnail', false, ['alt' => '', 'loading' => false])
                . '<button type="button" class="s1-gallery-remove" aria-label="Ta bort bild">&times;</button></li>';
        }
        echo '</ul>';
        echo '<p class="s1-gallery-empty">Inga bilder ännu.</p>';
        echo '<p><button type="button" class="button s1-gallery-add">' . esc_html($field['button_label'] ?: 'Lägg till bilder') . '</button></p>';
        echo '<input type="hidden" class="s1-gallery-input" name="' . esc_attr($field['name']) . '" value="' . esc_attr(implode(',', $ids)) . '">';
        echo '</div>';
    }

    public function input_admin_enqueue_scripts(): void {
        $base = plugin_dir_url(dirname(__DIR__) . '/s1sports-content.php');
        wp_enqueue_media();
        wp_enqueue_style('s1-event-gallery', $base . 'assets/admin-event-gallery.css', [], S1_CONTENT_VERSION);
        wp_enqueue_script('s1-event-gallery', $base . 'assets/admin-event-gallery.js', ['jquery', 'jquery-ui-sortable'], S1_CONTENT_VERSION, true);
    }

    public function update_value($value, $post_id, $field) {
        return implode(',', self::ids($value));
    }

    public function format_value($value, $post_id, $field) {
        return self::ids($value);
    }
}
