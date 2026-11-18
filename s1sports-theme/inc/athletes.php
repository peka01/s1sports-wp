<?php
defined('ABSPATH') || exit;

function s1_athlete_seed(): array {
    static $seed = null;
    if ($seed !== null) return $seed;
    $file = get_template_directory() . '/data/athletes.json';
    $rows = is_readable($file) ? json_decode((string) file_get_contents($file), true) : [];
    $seed = [];
    foreach ((array) $rows as $row) $seed[sanitize_title($row['name'])] = $row;
    return $seed;
}

function s1_athlete_field(string $name, int $post_id): mixed {
    if (function_exists('get_field')) {
        $value = get_field($name, $post_id);
        if ($value !== null && $value !== false && $value !== '') return $value;
    }
    return get_post_meta($post_id, $name, true);
}

function s1_athlete_posts(): array {
    if (!post_type_exists('atleter')) return [];
    $posts = get_posts(['post_type' => 'atleter', 'post_status' => 'publish', 'numberposts' => -1, 'suppress_filters' => false]);
    if (has_filter('wpml_current_language')) {
        // Use a translated profile when available, then fill gaps with the source profile.
        $all = get_posts(['post_type' => 'atleter', 'post_status' => 'publish', 'numberposts' => -1, 'suppress_filters' => true]);
        $seen = [];
        $merged = [];
        foreach (array_merge($posts, $all) as $post) {
            $key = sanitize_title($post->post_title);
            if (isset($seen[$key])) continue;
            $seen[$key] = true;
            $merged[] = $post;
        }
        $posts = $merged;
    }
    $seed = s1_athlete_seed();
    usort($posts, static function ($a, $b) use ($seed) {
        $oa = (int) s1_athlete_field('s1_display_order', $a->ID) ?: (int) ($seed[sanitize_title($a->post_title)]['order'] ?? 999);
        $ob = (int) s1_athlete_field('s1_display_order', $b->ID) ?: (int) ($seed[sanitize_title($b->post_title)]['order'] ?? 999);
        return $oa <=> $ob ?: strcmp($a->post_title, $b->post_title);
    });
    return $posts;
}

function s1_athlete_card(WP_Post $post, bool $slide): string {
    $seed = s1_athlete_seed()[sanitize_title($post->post_title)] ?? [];
    $name = (string) (s1_athlete_field('namn', $post->ID) ?: $post->post_title);
    $sport = (string) (s1_athlete_field('sport', $post->ID) ?: ($seed['sport'] ?? ''));
    $college = (string) (s1_athlete_field('skola', $post->ID) ?: ($seed['college'] ?? ''));
    $profile = s1_athlete_field('profilbild', $post->ID);
    $image = '';
    if (is_array($profile)) $image = (string) ($profile['sizes']['large'] ?? $profile['url'] ?? '');
    elseif (is_numeric($profile)) $image = (string) wp_get_attachment_image_url((int) $profile, 'large');
    elseif (is_string($profile) && filter_var($profile, FILTER_VALIDATE_URL)) $image = $profile;
    if (!$image) $image = get_the_post_thumbnail_url($post->ID, 'large');
    if (!$image && !empty($seed['local_image'])) {
        $fallback = (string) $seed['local_image'];
        $image = str_starts_with($fallback, 'https://') ? $fallback : get_template_directory_uri() . '/' . $fallback;
    }
    $sport_key = sanitize_title($sport);
    $data = ' data-sport="' . esc_attr($sport_key) . '" data-status="' . esc_attr(s1_athlete_status($college)) . '"';
    $wrapper = $slide ? '<article class="bde-loop-item ee-post swiper-slide"' . $data . '>' : '<div class="athlete-card-wrapper"' . $data . '>';
    $end = $slide ? '</article>' : '</div>';
    $style = $image ? ' style="background-image:url(' . esc_url($image) . ')"' : '';
    return $wrapper . '<div class="bde-div flip-card bde-div-62-104"><div id="flip-card__inner" class="bde-div bde-div-62-105"><div class="bde-div bde-div-62-106"' . $style . '><h3 class="bde-heading bde-heading-62-119">' . esc_html($name) . '</h3><div class="bde-text bde-text-62-108">' . esc_html($sport) . '</div></div><div class="bde-div bde-div-62-107"><div class="bde-text bde-text-62-109">' . esc_html($name) . '</div><div class="bde-text bde-text-62-110">' . esc_html($college) . '</div><div class="bde-text bde-text-62-120"></div></div></div></div>' . $end;
}

function s1_render_dynamic_athletes(string $html): string {
    $posts = s1_athlete_posts();
    if (!$posts) return $html;
    $slug = (string) get_post_field('post_name', get_queried_object_id());
    if (in_array($slug, ['vara-idrottare', 'our-athletes'], true)) {
        $cards = implode('', array_map(static fn($post) => s1_athlete_card($post, false), $posts));
        $html = (string) preg_replace_callback('/(<div id="my-ajax-loop" class="[^"]*">)[\s\S]*?(<\/div>)/', static fn($m) => $m[1] . $cards . $m[2], $html, 1);
        $filter = s1_athlete_filter_html($posts);
        return (string) preg_replace_callback('/<div class="icon-filter-container" id="acf-filter-group">[\s\S]*?<\/button>\s*<\/div>/', static fn() => $filter, $html, 1);
    }
    $seed = s1_athlete_seed();
    $featured = array_values(array_filter($posts, static function ($post) use ($seed) {
        $field = s1_athlete_field('s1_featured', $post->ID);
        return $field !== '' ? (bool) $field : !empty($seed[sanitize_title($post->post_title)]['featured']);
    }));
    $featured = array_slice($featured, 0, 8);
    if (!$featured) return $html;
    $cards = implode('', array_map(static fn($post) => s1_athlete_card($post, true), $featured));
    return preg_replace_callback('/(?:<article class="bde-loop-item ee-post swiper-slide">[\s\S]*?<\/article>)+/', static function ($match) use ($cards) {
        return str_contains($match[0], 'flip-card') ? $cards : $match[0];
    }, $html, 1);
}

/**
 * "College Recruit 2027" -> "2027" (on the way to the USA that year); any other school name ->
 * "signed"; empty -> "". Drives the year / signed dropdown on Våra idrottare.
 */
function s1_athlete_status(string $college): string {
    $college = trim($college);
    if ($college === '') return '';
    if (preg_match('/recruit\D*(20\d\d)/i', $college, $m)) return $m[1];
    return 'signed';
}

function s1a_icon(string $name): string {
    $paths = [
        'all' => '<rect x="3.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="3.5" width="7" height="7" rx="1.5"/><rect x="3.5" y="13.5" width="7" height="7" rx="1.5"/><rect x="13.5" y="13.5" width="7" height="7" rx="1.5"/>',
        'fotboll' => '<circle cx="12" cy="12" r="9"/><path d="M12 7.6l4.2 3-1.6 4.9H9.4l-1.6-4.9z"/><path d="M12 3v4.6M20.6 9.3l-4.4 1.3M17.4 19.2l-2.8-3.7M6.6 19.2l2.8-3.7M3.4 9.3l4.4 1.3"/>',
        'golf' => '<path d="M9 20V3.5l8.5 3.8L9 11"/><path d="M4 20.5c1.5-1 3.3-1.5 5-1.5s3.5.5 5 1.5"/>',
        'tennis' => '<ellipse cx="10" cy="9.5" rx="5" ry="6.5" transform="rotate(-45 10 9.5)"/><path d="M13.6 13.1l6.4 6.4"/><path d="M6.5 6l7 7M9 4l5.5 5.5M5 9l5.5 5.5"/>',
        'friidrott' => '<circle cx="15.5" cy="4.5" r="2"/><path d="M7.5 11.5l3-3 4.5.5 2 3.5h3"/><path d="M15 9l-2 6 3.5 2.5V21"/><path d="M13 15l-3 2.5H6"/>',
    ];
    return '<svg class="s1a-icon" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
}

/** Sport buttons (with icons and names) plus the year / signed dropdown for Våra idrottare. */
function s1_athlete_filter_html(array $posts): string {
    $sports = [
        '' => s1_text('Alla', 'All'),
        'fotboll' => s1_text('Fotboll', 'Football'),
        'golf' => 'Golf',
        'tennis' => 'Tennis',
        'friidrott' => s1_text('Friidrott', 'Athletics'),
    ];
    $years = [];
    $signed = false;
    foreach ($posts as $post) {
        $seed = s1_athlete_seed()[sanitize_title($post->post_title)] ?? [];
        $status = s1_athlete_status((string) (s1_athlete_field('skola', $post->ID) ?: ($seed['college'] ?? '')));
        if ($status === 'signed') $signed = true;
        elseif ($status !== '') $years[$status] = true;
    }
    ksort($years);
    $buttons = '';
    foreach ($sports as $key => $label) {
        $buttons .= '<button type="button" class="s1a-chip" data-s1a-sport="' . esc_attr($key) . '" aria-pressed="' . ($key === '' ? 'true' : 'false') . '">'
            . s1a_icon($key === '' ? 'all' : $key) . '<span>' . esc_html($label) . '</span></button>';
    }
    $options = '<option value="">' . esc_html(s1_text('Alla idrottare', 'All athletes')) . '</option>';
    foreach (array_keys($years) as $year) {
        $options .= '<option value="' . esc_attr((string) $year) . '">' . esc_html(sprintf(s1_text('Åker till USA %s', 'Heading to the USA in %s'), $year)) . '</option>';
    }
    if ($signed) $options .= '<option value="signed">' . esc_html(s1_text('Signerade med skola', 'Signed with a school')) . '</option>';
    return '<div class="s1a-filter" id="s1a-filter">'
        . '<div class="s1a-sports" role="group" aria-label="' . esc_attr(s1_text('Filtrera på sport', 'Filter by sport')) . '">' . $buttons . '</div>'
        . '<label class="s1a-select"><span class="s1a-sr">' . esc_html(s1_text('Visa', 'Show')) . '</span>'
        . '<select data-s1a-status>' . $options . '</select>'
        . '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 9l6 6 6-6"/></svg></label>'
        . '</div>'
        . '<p class="s1a-empty" hidden>' . esc_html(s1_text('Inga idrottare matchar filtret.', 'No athletes match this filter.')) . '</p>';
}
