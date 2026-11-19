<?php
/**
 * "Bilder på sidan" on the pages built from snapshots (Start, Juniorutveckling, Om oss …):
 * every photo and background image in the page body can be swapped in wp-admin. The fields
 * are found in the snapshot itself, so a page gets one field per image, in page order.
 * An empty field keeps the image from the snapshot.
 *
 * Meta keys: the big image at the top keeps its old key s1_hero_image; the others are
 * s1_img_<element number>. Breakdance gives the same element the same number on the
 * Swedish and the English page, so WPML can copy the choice between the languages.
 */
defined('ABSPATH') || exit;

/** Slugs of the pages rendered from snapshots, both languages (the Swedish front page is "start"). */
function s1_snapshot_slugs(): array {
    static $slugs = null;
    if ($slugs !== null) return $slugs;
    return $slugs = array_values(array_unique(array_merge(['start'], array_column(s1_snapshot_manifest(), 'slug'))));
}

function s1_snapshot_manifest(): array {
    static $manifest = null;
    if ($manifest !== null) return $manifest;
    $manifest = json_decode((string) file_get_contents(get_template_directory() . '/snapshots/manifest.json'), true);
    return $manifest = is_array($manifest) ? $manifest : [];
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

/** The snapshot entry for a page in wp-admin, found from its slug. */
function s1_snapshot_entry_for_post(int $post_id): ?array {
    if (!$post_id || !s1_is_snapshot_page($post_id)) return null;
    $slug = (string) get_post_field('post_name', $post_id);
    $en = ['home', 'junior-development', 'about-us', 'pro-consulting', 'our-services', 'our-athletes', 'contact', 'college-recruiting', 'book-a-meeting'];
    return s1_snapshot_entry(in_array($slug, $en, true) ? 'en' : 'sv', $slug === 'start' ? 'home' : $slug);
}

function s1_page_image_key(string $class): string {
    $number = (int) substr($class, strrpos($class, '-') + 1);
    return ($number === 100 && strpos($class, 'bde-section-') === 0) ? 's1_hero_image' : 's1_img_' . $number;
}

function s1_page_image_text(string $html): string {
    return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8')));
}

/**
 * The images in a snapshot's page body (between the header and the shared footer), in page order:
 * <img> elements and elements that get a background image from the page's CSS.
 * Each: key (meta key), type (img|bg), class, url (today's image), label (for wp-admin).
 */
function s1_snapshot_images(array $entry): array {
    static $cache = [];
    $file = (string) $entry['file'];
    if (isset($cache[$file])) return $cache[$file];
    $html = (string) file_get_contents($file);
    // On Juniorutveckling this section is replaced by the Golf/Tennis cards, which have their own image fields.
    if (in_array($entry['slug'] ?? '', ['junior-utveckling', 'junior-development'], true)) {
        $html = (string) preg_replace('/<section id="las-mer"[^>]*>[\s\S]*?<\/section>/', '', $html, 1);
    }
    $start = strpos($html, '</header>');
    if ($start === false) return $cache[$file] = [];
    $end = preg_match('/<section class="bde-section-108-/', $html, $footer, PREG_OFFSET_CAPTURE, $start) ? (int) $footer[0][1] : strlen($html);
    $body = substr($html, $start, $end - $start);

    $css = '';
    foreach ((array) $entry['css'] as $path) {
        $css_file = get_template_directory() . '/' . $path;
        if (is_readable($css_file)) $css .= (string) file_get_contents($css_file) . "\n";
    }

    $spots = [];
    preg_match_all('/<img class="(bde-image2-\d+-\d+)[ "][^>]*?\ssrc="([^"]+)"/', $body, $images, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    foreach ($images as $m) {
        $spots[$m[1][0]] = ['pos' => $m[0][1], 'type' => 'img', 'class' => $m[1][0], 'url' => html_entity_decode($m[2][0])];
    }
    preg_match_all('/class="(bde-[a-z0-9]+-\d+-\d+)[ "]/', $body, $elements, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    foreach ($elements as $m) {
        $class = $m[1][0];
        if (isset($spots[$class]) || strpos($class, 'bde-image2-') === 0) continue;
        if (preg_match('~\.' . preg_quote($class, '~') . '\{[^}]*background-image:url\(([^)]+)\)~', $css, $bg)) {
            $spots[$class] = ['pos' => $m[0][1], 'type' => 'bg', 'class' => $class, 'url' => trim($bg[1], '\'"')];
        }
    }
    $spots = array_values($spots);
    usort($spots, static fn(array $a, array $b): int => $a['pos'] <=> $b['pos']);

    // Name each image after the heading next to it, so the field says which image it is.
    preg_match_all('~<h([1-4])\b[^>]*>([\s\S]*?)</h\1>~', $body, $heads, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    preg_match_all('~<section\b~', $body, $sections, PREG_OFFSET_CAPTURE);
    $section_starts = array_column($sections[0], 1);
    foreach ($spots as $i => &$spot) {
        $spot['key'] = s1_page_image_key($spot['class']);
        if ($spot['key'] === 's1_hero_image') {
            $spot['label'] = 'Toppbild';
            continue;
        }
        $section_start = 0;
        $section_end = strlen($body);
        foreach ($section_starts as $s) {
            if ($s <= $spot['pos']) $section_start = $s;
            elseif ($s < $section_end) { $section_end = $s; break; }
        }
        $next_spot = isset($spots[$i + 1]) ? $spots[$i + 1]['pos'] : $section_end;
        $heading = '';
        if ($spot['type'] === 'bg') {
            // A background sits behind its own heading.
            foreach ($heads as $h) {
                if ($h[0][1] > $spot['pos'] && $h[0][1] < min($next_spot, $section_end)) { $heading = s1_page_image_text($h[2][0]); break; }
            }
            // A card without a heading: its first piece of text, e.g. "Fotboll".
            if ($heading === '' && preg_match_all('/>([^<]+)</', substr($body, $spot['pos'], min($next_spot, $section_end) - $spot['pos']), $texts)) {
                foreach ($texts[1] as $text) {
                    $text = s1_page_image_text($text);
                    if ($text !== '') { $heading = $text; break; }
                }
            }
        }
        if ($heading === '') {
            $best = PHP_INT_MAX;
            foreach ($heads as $h) {
                $pos = $h[0][1];
                if ($pos < $section_start || $pos >= $section_end) continue;
                $distance = abs($pos - $spot['pos']);
                if ($distance < $best) { $best = $distance; $heading = s1_page_image_text($h[2][0]); }
            }
        }
        $name = $spot['type'] === 'bg' ? 'Bakgrund' : 'Bild';
        $spot['label'] = $heading !== '' ? $name . ' vid "' . $heading . '"' : $name . ' ' . ($i + 1);
    }
    unset($spot);
    return $cache[$file] = $spots;
}

/** All meta keys used on any snapshot page, hero first, then by element number (roughly page order). */
function s1_page_image_keys(): array {
    $cache_key = 's1_page_image_keys_' . md5(wp_get_theme(get_template())->get('Version') . '|' . filemtime(__FILE__));
    $keys = get_transient($cache_key);
    if (is_array($keys)) return $keys;
    $keys = [];
    foreach (s1_snapshot_manifest() as $item) {
        $entry = s1_snapshot_entry((string) $item['locale'], (string) $item['slug']);
        if (!$entry) continue;
        foreach (s1_snapshot_images($entry) as $spot) $keys[$spot['key']] = true;
    }
    $keys = array_keys($keys);
    usort($keys, static function (string $a, string $b): int {
        if ($a === 's1_hero_image') return -1;
        if ($b === 's1_hero_image') return 1;
        return (int) substr($a, 7) <=> (int) substr($b, 7);
    });
    set_transient($cache_key, $keys, WEEK_IN_SECONDS);
    return $keys;
}

/** Swap the images chosen in wp-admin into the page: <img> sources, and background images through a style block. */
function s1_apply_page_images(string $html, int $post_id, array $entry): string {
    $css = '';
    foreach (s1_snapshot_images($entry) as $spot) {
        $image_id = (int) get_post_meta($post_id, $spot['key'], true);
        if ($image_id <= 0) continue;
        $url = wp_get_attachment_image_url($image_id, 'full');
        if (!$url) continue;
        if ($spot['type'] === 'bg') {
            $base = (string) preg_replace('/-\d+-\d+$/', '', $spot['class']);
            $css .= '.breakdance .' . $spot['class'] . '.' . $base . '{background-image:url("' . esc_url($url) . '")!important}';
            continue;
        }
        $srcset = (string) wp_get_attachment_image_srcset($image_id, 'full');
        $alt = trim((string) get_post_meta($image_id, '_wp_attachment_image_alt', true));
        $html = (string) preg_replace_callback('/<img class="' . preg_quote($spot['class'], '/') . '[ "][^>]*>/', static function (array $m) use ($url, $srcset, $alt): string {
            $class = preg_match('/class="([^"]*)"/', $m[0], $c) ? $c[1] : '';
            $sizes = preg_match('/\ssizes="([^"]*)"/', $m[0], $s) ? $s[1] : '100vw';
            return '<img class="' . esc_attr($class) . '" src="' . esc_url($url) . '" loading="lazy"'
                . ($srcset !== '' ? ' srcset="' . esc_attr($srcset) . '" sizes="' . esc_attr($sizes) . '"' : '')
                . ' alt="' . esc_attr($alt) . '">';
        }, $html, 1);
    }
    if ($css !== '' && ($header_end = strpos($html, '</header>')) !== false) {
        $html = substr_replace($html, '<style id="s1-page-images">' . $css . '</style>', $header_end + strlen('</header>'), 0);
    }
    return $html;
}

/* The pages' texts live in the theme, so the block editor has nothing to edit there: use the classic screen with just the title and the fields. */
add_filter('use_block_editor_for_post', function (bool $use, WP_Post $post): bool {
    return $post->post_type === 'page' && s1_is_snapshot_page((int) $post->ID) ? false : $use;
}, 10, 2);

add_action('acf/init', function (): void {
    if (!function_exists('acf_add_local_field_group')) return;
    $locations = array_map(static fn(int $id): array => [['param' => 'page', 'operator' => '==', 'value' => (string) $id]], s1_snapshot_page_ids());
    if (!$locations) return;
    $fields = [
        ['key' => 'field_s1_page_hero_help', 'label' => '', 'type' => 'message',
            'message' => 'Texterna på den här sidan ligger i temat och ändras inte här. Här byter du bilderna, i samma ordning som de syns på sidan. Ett tomt fält behåller bilden som syns i dag.'],
    ];
    foreach (s1_page_image_keys() as $key) {
        $fields[] = [
            'key' => $key === 's1_hero_image' ? 'field_s1_hero_image' : 'field_s1_page_' . $key,
            'label' => $key === 's1_hero_image' ? 'Toppbild' : 'Bild',
            'name' => $key,
            'type' => 'image',
            'return_format' => 'id',
            'preview_size' => 'medium',
            'library' => 'all',
            'wpml_cf_preferences' => 1,
        ];
    }
    acf_add_local_field_group([
        'key' => 'group_s1_page_hero',
        'title' => 'Bilder på sidan',
        'acfml_field_group_mode' => 'advanced',
        'fields' => $fields,
        'location' => $locations,
        'menu_order' => 0,
        'position' => 'acf_after_title',
        'style' => 'default',
        'label_placement' => 'top',
        'hide_on_screen' => ['the_content', 'featured_image', 'excerpt', 'discussion', 'comments', 'author', 'format', 'categories', 'tags', 'send-trackbacks'],
        'active' => true,
    ]);
});

function s1_page_images_admin_post_id(): int {
    if (isset($_GET['post'])) return absint($_GET['post']);
    if (isset($_POST['post_ID'])) return absint($_POST['post_ID']);
    return isset($GLOBALS['post']->ID) ? (int) $GLOBALS['post']->ID : 0;
}

/* Show only the images this page has, named after the heading next to them, with today's image as a preview. */
add_filter('acf/prepare_field', function ($field) {
    if (!is_array($field) || ($field['parent'] ?? '') !== 'group_s1_page_hero') return $field;
    $entry = s1_snapshot_entry_for_post(s1_page_images_admin_post_id());
    $spots = $entry ? s1_snapshot_images($entry) : [];
    if (($field['type'] ?? '') === 'message') {
        if (!$spots) $field['message'] = 'Texterna på den här sidan ligger i temat och ändras inte här. Sidan har inga bilder att byta.';
        return $field;
    }
    foreach ($spots as $spot) {
        if ($spot['key'] !== ($field['_name'] ?? $field['name'] ?? '')) continue;
        $field['label'] = $spot['label'];
        $field['instructions'] = 'I dag: <a href="' . esc_url($spot['url']) . '" target="_blank" rel="noopener">'
            . '<img src="' . esc_url($spot['url']) . '" alt="" style="display:block;max-width:220px;max-height:120px;margin:6px 0;border-radius:6px;background:#222"></a>'
            . 'Lämna tomt för att behålla den här bilden.';
        return $field;
    }
    return false;
});
