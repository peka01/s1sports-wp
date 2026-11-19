<?php
/**
 * "Toppbild" on the pages built from snapshots (Start, Juniorutveckling, Om oss …):
 * editors pick the big image at the top of the page in wp-admin. When the field is
 * empty, the image from the snapshot's CSS is shown as before.
 */
defined('ABSPATH') || exit;

/** Slugs of the pages rendered from snapshots, both languages (the Swedish front page is "start"). */
function s1_snapshot_slugs(): array {
    static $slugs = null;
    if ($slugs !== null) return $slugs;
    $manifest = json_decode((string) file_get_contents(get_template_directory() . '/snapshots/manifest.json'), true);
    return $slugs = array_values(array_unique(array_merge(['start'], array_column((array) $manifest, 'slug'))));
}

function s1_snapshot_page_ids(): array {
    static $ids = null;
    if ($ids !== null) return $ids;
    $ids = get_posts([
        'post_type' => 'page',
        'post_status' => 'any',
        'post_name__in' => s1_snapshot_slugs(),
        'numberposts' => -1,
        'fields' => 'ids',
        'suppress_filters' => true,
    ]);
    return $ids = array_map('intval', $ids);
}

function s1_is_snapshot_page(int $post_id): bool {
    return in_array($post_id, s1_snapshot_page_ids(), true);
}

/**
 * The hero section of a snapshot page: its CSS class (the first section after the header)
 * and the background image the snapshot's CSS gives it today.
 */
function s1_snapshot_hero(array $entry): array {
    $html = (string) file_get_contents($entry['file']);
    if (!preg_match('~</header>\s*<section class="(bde-section-\d+-100)~', $html, $m)) return ['class' => '', 'url' => ''];
    $class = $m[1];
    $url = '';
    foreach ($entry['css'] as $css) {
        $file = get_template_directory() . '/' . $css;
        if (!is_readable($file)) continue;
        if (preg_match('~\.' . preg_quote($class, '~') . '\{[^}]*background-image:url\(([^)]+)\)~', (string) file_get_contents($file), $found)) {
            $url = trim($found[1], '\'"');
            break;
        }
    }
    return ['class' => $class, 'url' => $url];
}

/** A style block that swaps the hero background for the image chosen in wp-admin, or '' when none is chosen. */
function s1_page_hero_style(int $post_id, array $entry): string {
    $image_id = (int) get_post_meta($post_id, 's1_hero_image', true);
    if ($image_id <= 0) return '';
    $url = wp_get_attachment_image_url($image_id, 'full');
    $hero = s1_snapshot_hero($entry);
    if (!$url || $hero['class'] === '') return '';
    return '<style id="s1-page-hero">.breakdance .' . $hero['class'] . '.bde-section{background-image:url("' . esc_url($url) . '")!important}</style>';
}

/* The pages' texts live in the theme, so the block editor has nothing to edit there: use the classic screen with just the title and the fields. */
add_filter('use_block_editor_for_post', function (bool $use, WP_Post $post): bool {
    return $post->post_type === 'page' && s1_is_snapshot_page((int) $post->ID) ? false : $use;
}, 10, 2);

add_action('acf/init', function (): void {
    if (!function_exists('acf_add_local_field_group')) return;
    $locations = array_map(static fn(int $id): array => [['param' => 'page', 'operator' => '==', 'value' => (string) $id]], s1_snapshot_page_ids());
    if (!$locations) return;
    acf_add_local_field_group([
        'key' => 'group_s1_page_hero',
        'title' => 'Toppbild',
        'acfml_field_group_mode' => 'advanced',
        'fields' => [
            ['key' => 'field_s1_page_hero_help', 'label' => '', 'type' => 'message',
                'message' => 'Texterna på den här sidan ligger i temat och ändras inte här. Här byter du den stora bilden överst på sidan.'],
            ['key' => 'field_s1_hero_image', 'label' => 'Toppbild', 'name' => 's1_hero_image', 'type' => 'image',
                'instructions' => 'Lämna tomt för att behålla bilden som syns i dag.',
                'return_format' => 'id', 'preview_size' => 'medium', 'library' => 'all', 'wpml_cf_preferences' => 1],
        ],
        'location' => $locations,
        'menu_order' => 0,
        'position' => 'acf_after_title',
        'style' => 'default',
        'label_placement' => 'top',
        'hide_on_screen' => ['the_content', 'featured_image', 'excerpt', 'discussion', 'comments', 'author', 'format', 'categories', 'tags', 'send-trackbacks'],
        'active' => true,
    ]);
});

/** Name today's image in the field's hint, so it is clear what an empty field keeps. */
add_filter('acf/prepare_field/key=field_s1_hero_image', function ($field) {
    $post_id = isset($_GET['post']) ? absint($_GET['post']) : 0;
    if (!$post_id || !is_array($field)) return $field;
    $slug = (string) get_post_field('post_name', $post_id);
    $en = ['home', 'junior-development', 'about-us', 'pro-consulting', 'our-services', 'our-athletes', 'contact', 'college-recruiting', 'book-a-meeting'];
    $entry = s1_snapshot_entry(in_array($slug, $en, true) ? 'en' : 'sv', $slug === 'start' ? 'home' : $slug);
    $url = $entry ? s1_snapshot_hero($entry)['url'] : '';
    if ($url !== '') {
        $field['instructions'] = 'Lämna tomt för att behålla bilden som syns i dag: <a href="' . esc_url($url) . '" target="_blank" rel="noopener">' . esc_html(basename((string) parse_url($url, PHP_URL_PATH))) . '</a>.';
    }
    return $field;
});
