<?php
/**
 * Development Golf / Development Tennis pages, and the sport cards on the
 * Juniorutveckling page. Everything shown here is editable in wp-admin:
 * - page title, featured image (hero), page text (intro) and the fields below on each Development page;
 * - the section heading on the Juniorutveckling page;
 * - the events themselves (post type s1_event, from the s1sports-content plugin).
 */
defined('ABSPATH') || exit;

const S1_DEV_TEMPLATE = 'template-development.php';

function s1_dev_sports(): array {
    return function_exists('s1_event_sports') ? s1_event_sports() : ['golf' => 'Golf', 'tennis' => 'Tennis'];
}

function s1_dev_meta(int $post_id, string $name, string $fallback = ''): string {
    $value = get_post_meta($post_id, $name, true);
    $value = is_scalar($value) ? trim((string) $value) : '';
    return $value !== '' ? $value : $fallback;
}

function s1_dev_is_page(?int $post_id = null): bool {
    $post_id = $post_id ?: (int) get_queried_object_id();
    return $post_id > 0 && get_page_template_slug($post_id) === S1_DEV_TEMPLATE;
}

/** IDs of the Swedish and English Juniorutveckling pages (they hold the section heading fields). */
function s1_junior_page_ids(): array {
    static $ids = null;
    if ($ids !== null) return $ids;
    $ids = get_posts([
        'post_type' => 'page',
        'post_status' => 'any',
        'post_name__in' => ['junior-utveckling', 'junior-development'],
        'numberposts' => 4,
        'fields' => 'ids',
        'suppress_filters' => true,
    ]);
    return $ids = array_map('intval', $ids);
}

add_action('acf/init', function (): void {
    if (!function_exists('acf_add_local_field_group')) return;
    $half = ['width' => '50'];
    // The form follows the page from top to bottom; the card on Juniorutveckling comes last
    // because it is shown on another page. Empty text fields fall back to the grey hint.
    $image = ['type' => 'image', 'return_format' => 'id', 'preview_size' => 'medium', 'library' => 'all'];
    acf_add_local_field_group([
        'key' => 'group_s1_development_page',
        'title' => 'Sidans innehåll',
        'acfml_field_group_mode' => 'advanced',
        'fields' => s1d_wpml_field_prefs([
            ['key' => 'field_s1_dev_tab_top', 'label' => '1. Toppen', 'type' => 'tab'],
            ['key' => 'field_s1_dev_hero_image', 'label' => 'Toppbild', 'name' => 's1_dev_hero_image', 'instructions' => 'Stor bild överst på sidan, bakom rubriken.'] + $image,
            ['key' => 'field_s1_dev_heading_bold', 'label' => 'Rubrik – fet del', 'name' => 's1_dev_heading_bold', 'type' => 'text', 'placeholder' => 'Development', 'wrapper' => $half],
            ['key' => 'field_s1_dev_heading_italic', 'label' => 'Rubrik – kursiv del', 'name' => 's1_dev_heading_italic', 'type' => 'text', 'placeholder' => 'Golf', 'wrapper' => $half],
            ['key' => 'field_s1_dev_intro', 'label' => 'Ingress', 'name' => 's1_dev_intro', 'type' => 'textarea', 'rows' => 3, 'new_lines' => '',
                'instructions' => 'Text under rubriken. Tom rad ger nytt stycke.'],

            ['key' => 'field_s1_dev_tab_events', 'label' => '2. Event', 'type' => 'tab'],
            ['key' => 'field_s1_dev_sport', 'label' => 'Sport', 'name' => 's1_dev_sport', 'type' => 'select', 'required' => 1,
                'instructions' => 'Sidan visar alla publicerade event för den här sporten. Eventen läggs till under menyn Event.',
                'choices' => s1_dev_sports(), 'return_format' => 'value'],
            ['key' => 'field_s1_dev_list_bold', 'label' => 'Rubrik ovanför eventen – fet del', 'name' => 's1_dev_list_bold', 'type' => 'text', 'placeholder' => 'Turneringar', 'wrapper' => $half],
            ['key' => 'field_s1_dev_list_italic', 'label' => 'Rubrik ovanför eventen – kursiv del', 'name' => 's1_dev_list_italic', 'type' => 'text', 'placeholder' => '& läger', 'wrapper' => $half],

            ['key' => 'field_s1_dev_tab_cta', 'label' => '3. Avslutning', 'type' => 'tab'],
            ['key' => 'field_s1_dev_cta_image', 'label' => 'Bakgrundsbild', 'name' => 's1_dev_cta_image', 'instructions' => 'Bilden bakom rutan längst ner. Lämna tomt för att använda toppbilden.'] + $image,
            ['key' => 'field_s1_dev_cta_bold', 'label' => 'Rubrik – fet del', 'name' => 's1_dev_cta_bold', 'type' => 'text', 'placeholder' => 'Hittar du inte', 'wrapper' => $half],
            ['key' => 'field_s1_dev_cta_italic', 'label' => 'Rubrik – kursiv del', 'name' => 's1_dev_cta_italic', 'type' => 'text', 'placeholder' => 'rätt event?', 'wrapper' => $half],
            ['key' => 'field_s1_dev_cta_text', 'label' => 'Text', 'name' => 's1_dev_cta_text', 'type' => 'textarea', 'rows' => 2, 'new_lines' => '',
                'placeholder' => 'Berätta vad du siktar på, så hjälper vi dig att hitta rätt turnering eller läger.'],
            ['key' => 'field_s1_dev_cta_button', 'label' => 'Knapptext', 'name' => 's1_dev_cta_button', 'type' => 'text', 'placeholder' => 'Boka möte', 'wrapper' => $half,
                'instructions' => 'Knappen leder till Boka möte.'],

            ['key' => 'field_s1_dev_tab_card', 'label' => '4. Kort på Juniorutveckling', 'type' => 'tab'],
            ['key' => 'field_s1_card_help', 'label' => '', 'type' => 'message',
                'message' => 'Kortet visas på sidan Juniorutveckling och leder hit när besökaren klickar på det.'],
            ['key' => 'field_s1_card_image', 'label' => 'Kortbild', 'name' => 's1_card_image', 'instructions' => 'Lämna tomt för att använda toppbilden.'] + $image,
            ['key' => 'field_s1_card_label', 'label' => 'Liten rubrik', 'name' => 's1_card_label', 'type' => 'text', 'placeholder' => 'Development', 'wrapper' => $half],
            ['key' => 'field_s1_card_title', 'label' => 'Stor rubrik', 'name' => 's1_card_title', 'type' => 'text', 'placeholder' => 'Golf', 'wrapper' => $half],
            ['key' => 'field_s1_card_text', 'label' => 'Text', 'name' => 's1_card_text', 'type' => 'textarea', 'rows' => 2, 'new_lines' => '',
                'placeholder' => 'Träningsläger, tävlingar och college showcases med S1-coach.'],
        ], ['s1_dev_sport', 's1_dev_hero_image', 's1_dev_cta_image', 's1_card_image']),
        'location' => [[['param' => 'page_template', 'operator' => '==', 'value' => S1_DEV_TEMPLATE]]],
        'position' => 'acf_after_title',
        'style' => 'default',
        'label_placement' => 'top',
        'hide_on_screen' => ['the_content', 'featured_image', 'excerpt', 'discussion', 'comments', 'author', 'format', 'categories', 'tags', 'send-trackbacks'],
        'active' => true,
    ]);

    $junior_locations = array_map(static fn(int $id): array => [['param' => 'page', 'operator' => '==', 'value' => (string) $id]], s1_junior_page_ids());
    if ($junior_locations) {
        acf_add_local_field_group([
            'key' => 'group_s1_junior_sports',
            'title' => 'Golf och Tennis på Juniorutveckling',
            'acfml_field_group_mode' => 'advanced',
            'fields' => s1d_wpml_field_prefs([
                ['key' => 'field_s1_jr_help', 'label' => 'Om sektionen', 'type' => 'message',
                    'message' => 'Sektionen under sidans toppbild visar ett kort för varje publicerad sida med mallen "Development (sport)". Kortens bild och text redigeras på respektive Development-sida.'],
                ['key' => 'field_s1_jr_heading_bold', 'label' => 'Rubrik, fet del', 'name' => 's1_jr_heading_bold', 'type' => 'text', 'placeholder' => 'Junior Development', 'wrapper' => $half],
                ['key' => 'field_s1_jr_heading_italic', 'label' => 'Rubrik, kursiv del', 'name' => 's1_jr_heading_italic', 'type' => 'text', 'placeholder' => 'camps & event', 'wrapper' => $half],
                ['key' => 'field_s1_jr_intro', 'label' => 'Text', 'name' => 's1_jr_intro', 'type' => 'textarea', 'rows' => 2, 'new_lines' => '',
                    'placeholder' => 'Följ våra kommande läger och tävlingsresor, och se vad vi har gjort tidigare. Välj din sport.'],
            ], []),
            'location' => $junior_locations,
            'position' => 'normal',
            'style' => 'default',
            'active' => true,
        ]);
    }
});

/*
 * Development pages use the classic editor so the page is just a title plus the form above,
 * in page order. (In the block editor the form is squeezed into a panel at the bottom.)
 */
add_filter('use_block_editor_for_post', function (bool $use, WP_Post $post): bool {
    return $post->post_type === 'page' && get_page_template_slug($post) === S1_DEV_TEMPLATE ? false : $use;
}, 10, 2);

/** The page's sport while editing it in wp-admin (or from the form being saved). */
function s1_dev_admin_sport(): string {
    $post_id = isset($_GET['post']) ? absint($_GET['post']) : (isset($_POST['post_ID']) ? absint($_POST['post_ID']) : 0);
    return $post_id ? s1_dev_meta($post_id, 's1_dev_sport') : '';
}

/** Grey hints that match what an empty field shows on this page's sport. */
add_filter('acf/prepare_field', function ($field) {
    if (!is_array($field) || !str_starts_with((string) ($field['key'] ?? ''), 'field_s1_')) return $field;
    $sport = s1_dev_admin_sport();
    if ($sport === '') return $field;
    $name = s1_dev_sports()[$sport] ?? '';
    $golf = $sport === 'golf';
    $hints = [
        'field_s1_dev_heading_italic' => $name,
        'field_s1_card_title' => $name,
        'field_s1_dev_list_bold' => $golf ? 'Tävlingar' : 'Turneringar',
        'field_s1_dev_cta_text' => $golf
            ? 'Berätta vad du siktar på, så hjälper vi dig att hitta rätt tävling eller läger.'
            : 'Berätta vad du siktar på, så hjälper vi dig att hitta rätt turnering eller läger.',
        'field_s1_card_text' => $golf
            ? 'Träningsläger, tävlingar och college showcases med S1-coach.'
            : 'ITF-turneringar, läger och tävlingsresor med S1-coach.',
    ];
    if (isset($hints[$field['key']])) $field['placeholder'] = $hints[$field['key']];
    return $field;
});

/*
 * Earlier versions used the featured image and the editor text for the top of the page.
 * Show those in the new fields until the page is saved, so nothing looks missing.
 */
add_filter('acf/load_value/key=field_s1_dev_hero_image', function ($value, $post_id) {
    return $value ?: ((int) get_post_thumbnail_id((int) $post_id) ?: $value);
}, 10, 2);
add_filter('acf/load_value/key=field_s1_dev_intro', function ($value, $post_id) {
    if ($value) return $value;
    $content = trim(wp_strip_all_tags((string) get_post_field('post_content', (int) $post_id)));
    return $content !== '' ? $content : $value;
}, 10, 2);

/**
 * Translation setting per field for WPML / ACF Multilingual: fields in $copy (sport, images)
 * are copied to the English translation, all other text is translated. Matches wpml-config.xml.
 */
function s1d_wpml_field_prefs(array $fields, array $copy): array {
    foreach ($fields as &$field) {
        if (empty($field['name'])) continue;
        $field['wpml_cf_preferences'] = in_array($field['name'], $copy, true) ? 1 : 2;
    }
    return $fields;
}

function s1d_icon(string $name, int $size = 18): string {
    $paths = [
        'arrow-up-right' => '<path d="M7 17L17 7M8 7h9v9"/>',
        'arrow-right' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'arrow-left' => '<path d="M19 12H5M11 6l-6 6 6 6"/>',
        'calendar' => '<rect x="3" y="5" width="18" height="16" rx="2"/><path d="M3 10h18M8 3v4M16 3v4"/>',
        'pin' => '<path d="M12 21s-7-6.2-7-11.5a7 7 0 0 1 14 0C19 14.8 12 21 12 21z"/><circle cx="12" cy="9.5" r="2.5"/>',
        'user' => '<circle cx="12" cy="8" r="4"/><path d="M4 21c1.5-4 4.5-6 8-6s6.5 2 8 6"/>',
        'check' => '<path d="M5 12l5 5 9-10"/>',
        'close' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'chevron-left' => '<path d="M15 6l-6 6 6 6"/>',
        'chevron-right' => '<path d="M9 6l6 6-6 6"/>',
        'camera' => '<path d="M4 8h3l2-3h6l2 3h3v11H4z"/><circle cx="12" cy="13" r="3.5"/>',
    ];
    return '<svg class="s1d-icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
}

function s1d_heading(string $tag, string $bold, string $italic, string $attrs = ''): string {
    $parts = [];
    if ($bold !== '') $parts[] = '<span class="s1d-b">' . esc_html($bold) . '</span>';
    if ($italic !== '') $parts[] = '<span class="s1d-i">' . esc_html($italic) . '</span>';
    return '<' . $tag . $attrs . '>' . implode(' ', $parts) . '</' . $tag . '>';
}

function s1d_paragraphs(string $text): string {
    $out = '';
    foreach (preg_split('/\R{2,}/', trim($text)) ?: [] as $paragraph) {
        if (trim($paragraph) !== '') $out .= '<p>' . nl2br(esc_html(trim($paragraph))) . '</p>';
    }
    return $out;
}

function s1d_image(int $attachment_id, string $class, string $sizes, bool $lazy = true): string {
    if ($attachment_id <= 0) return '';
    return (string) wp_get_attachment_image($attachment_id, 'full', false, [
        'class' => $class,
        'alt' => '',
        'sizes' => $sizes,
        'loading' => $lazy ? 'lazy' : 'eager',
        'decoding' => 'async',
    ]);
}

function s1_booking_url(array $args = []): string {
    $url = home_url(s1_lang() === 'en' ? '/book-a-meeting/' : '/boka-mote/');
    return $args ? add_query_arg(array_map('rawurlencode', $args), $url) : $url;
}

/**
 * The top image: the "Toppbild" field once the page has been saved with the new form,
 * before that the featured image (how the first version worked).
 */
function s1_dev_hero_id(int $post_id): int {
    return metadata_exists('post', $post_id, 's1_dev_hero_image')
        ? (int) s1_dev_meta($post_id, 's1_dev_hero_image')
        : (int) get_post_thumbnail_id($post_id);
}

/** Published Development pages in the current language. */
function s1_development_pages(): array {
    return get_posts([
        'post_type' => 'page',
        'post_status' => 'publish',
        'numberposts' => -1,
        'suppress_filters' => false,
        'meta_key' => '_wp_page_template',
        'meta_value' => S1_DEV_TEMPLATE,
        'orderby' => ['menu_order' => 'ASC', 'title' => 'ASC'],
    ]);
}

/** Replace the empty block under the Juniorutveckling hero with one card per Development page. */
function s1_render_development_cards(string $html): string {
    $pages = s1_development_pages();
    if (!$pages) return $html;
    $junior_id = (int) get_queried_object_id();
    $cards = '';
    foreach ($pages as $page) {
        $id = (int) $page->ID;
        $sport = s1_dev_meta($id, 's1_dev_sport');
        $image_id = (int) s1_dev_meta($id, 's1_card_image') ?: s1_dev_hero_id($id);
        $title = s1_dev_meta($id, 's1_card_title', s1_dev_sports()[$sport] ?? get_the_title($page));
        $cards .= '<a class="s1d-sport" href="' . esc_url(get_permalink($page)) . '">'
            . s1d_image($image_id, 's1d-sport__img', '(max-width: 767px) 100vw, 520px')
            . '<span class="s1d-sport__shade"></span>'
            . '<span class="s1d-sport__body"><span class="s1d-sport__text">'
            . '<span class="s1d-eyebrow">' . esc_html(s1_dev_meta($id, 's1_card_label', 'Development')) . '</span>'
            . '<span class="s1d-sport__title">' . esc_html($title) . '</span>'
            . (($text = s1_dev_meta($id, 's1_card_text')) !== '' ? '<span class="s1d-sport__desc">' . esc_html($text) . '</span>' : '')
            . '</span><span class="s1d-round">' . s1d_icon('arrow-up-right', 22) . '</span></span></a>';
    }
    $section = '<section id="las-mer" class="s1d-sports"><div class="s1d-wrap">'
        . '<div class="s1d-sports__head">'
        . s1d_heading('h2', s1_dev_meta($junior_id, 's1_jr_heading_bold', 'Junior Development'), s1_dev_meta($junior_id, 's1_jr_heading_italic', s1_text('camps & event', 'camps & events')), ' class="s1d-h2"')
        . (($intro = s1_dev_meta($junior_id, 's1_jr_intro', s1_text('Följ våra kommande läger och tävlingsresor, och se vad vi har gjort tidigare. Välj din sport.', 'Follow our upcoming camps and competition trips, and see what we have done before. Choose your sport.'))) !== '' ? '<p class="s1d-sports__intro">' . esc_html($intro) . '</p>' : '')
        . '</div><div class="s1d-sports__grid">' . $cards . '</div></div></section>';
    $replaced = preg_replace_callback('/<section id="las-mer"[^>]*>[\s\S]*?<\/section>/', static fn() => $section, $html, 1, $count);
    return $count ? (string) $replaced : $html;
}

/** Render a Development page inside the Juniorutveckling page's header and footer. */
function s1_render_development_page(): bool {
    $post_id = (int) get_queried_object_id();
    if (!s1_dev_is_page($post_id)) return false;
    $locale = s1_lang() === 'en' ? 'en' : 'sv';
    $shell = s1_snapshot_entry($locale, $locale === 'en' ? 'junior-development' : 'junior-utveckling');
    if (!$shell) return false;
    $html = str_replace('{{THEME}}', get_template_directory_uri(), (string) file_get_contents($shell['file']));
    $header_end = strpos($html, '</header>');
    if ($header_end === false || !preg_match('/<section class="bde-section-108-\d+ bde-section">/', $html, $footer, PREG_OFFSET_CAPTURE)) return false;
    $header_end += strlen('</header>');
    $html = substr($html, 0, $header_end) . s1_development_main($post_id) . substr($html, (int) $footer[0][1]);
    s1_output_snapshot_document(s1_prepare_snapshot_html($html, $locale), $shell['css'], $locale, true);
    return true;
}

function s1_development_main(int $post_id): string {
    $sport = s1_dev_meta($post_id, 's1_dev_sport', 'tennis');
    $sport_name = s1_dev_sports()[$sport] ?? ucfirst($sport);
    $events = function_exists('s1_get_events') ? s1_get_events($sport) : [];
    $hero_id = s1_dev_hero_id($post_id);
    $is_golf = $sport === 'golf';

    $bold = s1_dev_meta($post_id, 's1_dev_heading_bold');
    $italic = s1_dev_meta($post_id, 's1_dev_heading_italic');
    if ($bold === '' && $italic === '') $bold = get_the_title($post_id);
    $intro = metadata_exists('post', $post_id, 's1_dev_intro')
        ? s1d_paragraphs(s1_dev_meta($post_id, 's1_dev_intro'))
        : trim((string) apply_filters('the_content', (string) get_post_field('post_content', $post_id)));
    $junior_url = home_url(s1_lang() === 'en' ? '/junior-development/' : '/junior-utveckling/');

    $upcoming = count(array_filter($events, static fn(array $e): bool => !$e['past']));
    $past = count($events) - $upcoming;
    $default = $upcoming > 0 ? 'upcoming' : 'all';

    $out = '<main class="s1d" id="s1d-main">';
    $out .= '<section class="s1d-hero">' . s1d_image($hero_id, 's1d-hero__img', '100vw', false) . '<div class="s1d-hero__shade"></div>'
        . '<div class="s1d-wrap s1d-hero__body">'
        . '<a class="s1d-back" href="' . esc_url($junior_url) . '">' . s1d_icon('arrow-left', 16) . esc_html(s1_text('Juniorutveckling', 'Junior development')) . '</a>'
        . s1d_heading('h1', $bold, $italic, ' class="s1d-h1"')
        . ($intro !== '' ? '<div class="s1d-lead">' . $intro . '</div>' : '')
        . '</div></section>';

    $out .= '<section class="s1d-events" data-s1d-default="' . esc_attr($default) . '"><div class="s1d-wrap">'
        . '<div class="s1d-events__head">'
        . s1d_heading('h2', s1_dev_meta($post_id, 's1_dev_list_bold', $is_golf ? s1_text('Tävlingar', 'Competitions') : s1_text('Turneringar', 'Tournaments')), s1_dev_meta($post_id, 's1_dev_list_italic', s1_text('& läger', '& camps')), ' class="s1d-h2"');
    if ($events) {
        $out .= '<div class="s1d-filter" role="group" aria-label="' . esc_attr(s1_text('Filtrera event', 'Filter events')) . '">';
        foreach (['upcoming' => [s1_text('Kommande', 'Upcoming'), $upcoming], 'past' => [s1_text('Genomförda', 'Completed'), $past], 'all' => [s1_text('Alla', 'All'), count($events)]] as $key => [$label, $count]) {
            $out .= '<button type="button" class="s1d-chip" data-s1d-filter="' . esc_attr($key) . '" aria-pressed="' . ($key === $default ? 'true' : 'false') . '">' . esc_html($label) . ' (' . (int) $count . ')</button>';
        }
        $out .= '</div>';
    }
    $out .= '</div>';

    if ($events) {
        $out .= '<div class="s1d-grid">';
        foreach ($events as $event) $out .= s1_development_event_card($event);
        $out .= '</div>';
        $out .= '<p class="s1d-empty" hidden data-empty-upcoming="' . esc_attr(s1_text('Inga kommande event just nu. Hör av dig så berättar vi vad som är på gång.', 'No upcoming events right now. Get in touch and we will tell you what is coming up.')) . '" data-empty-past="' . esc_attr(s1_text('Inga genomförda event ännu.', 'No completed events yet.')) . '"></p>';
    } else {
        $out .= '<p class="s1d-empty">' . esc_html(s1_text('Inga event publicerade ännu. Hör av dig så berättar vi vad som är på gång.', 'No events published yet. Get in touch and we will tell you what is coming up.')) . '</p>';
    }
    $out .= '</div></section>';

    foreach ($events as $event) $out .= s1_development_event_dialog($event, $hero_id, $sport_name);

    $out .= '<section class="s1d-cta-wrap"><div class="s1d-wrap"><div class="s1d-cta">'
        . s1d_image((int) s1_dev_meta($post_id, 's1_dev_cta_image') ?: $hero_id, 's1d-cta__img', '(max-width: 1090px) 100vw, 1050px')
        . '<div class="s1d-cta__shade"></div>'
        . s1d_heading('h2', s1_dev_meta($post_id, 's1_dev_cta_bold', s1_text('Hittar du inte', "Can't find")), s1_dev_meta($post_id, 's1_dev_cta_italic', s1_text('rätt event?', 'the right event?')), ' class="s1d-h2"')
        . '<p class="s1d-cta__text">' . esc_html(s1_dev_meta($post_id, 's1_dev_cta_text', $is_golf
            ? s1_text('Berätta vad du siktar på, så hjälper vi dig att hitta rätt tävling eller läger.', 'Tell us what you are aiming for and we will help you find the right competition or camp.')
            : s1_text('Berätta vad du siktar på, så hjälper vi dig att hitta rätt turnering eller läger.', 'Tell us what you are aiming for and we will help you find the right tournament or camp.'))) . '</p>'
        . '<a class="s1d-btn s1d-btn--light" href="' . esc_url(s1_booking_url(['s1_sport' => $sport_name])) . '">' . esc_html(s1_dev_meta($post_id, 's1_dev_cta_button', s1_text('Boka möte', 'Book a meeting'))) . s1d_icon('arrow-up-right', 16) . '</a>'
        . '</div></div></section>';

    return $out . '</main>';
}

/** Slideshow of an event's photos (Swiper, started by development.js when the dialog opens). */
function s1d_gallery(array $ids, string $title): string {
    $ids = array_values(array_filter(array_map('intval', $ids)));
    if (!$ids) return '';
    $multiple = count($ids) > 1;
    $slides = '';
    $thumbs = '';
    foreach ($ids as $index => $id) {
        $caption = trim((string) wp_get_attachment_caption($id));
        $slides .= '<figure class="swiper-slide s1d-gallery__slide">'
            . wp_get_attachment_image($id, 'large', false, ['class' => 's1d-gallery__img', 'sizes' => '(max-width: 760px) 100vw, 660px', 'loading' => $index === 0 ? 'eager' : 'lazy', 'decoding' => 'async'])
            . ($caption !== '' ? '<figcaption>' . esc_html($caption) . '</figcaption>' : '')
            . '</figure>';
        if ($multiple) {
            $thumbs .= '<div class="swiper-slide s1d-gallery__thumb">' . wp_get_attachment_image($id, 'thumbnail', false, ['alt' => '', 'loading' => 'lazy']) . '</div>';
        }
    }
    return '<section class="s1d-modal__section s1d-gallery" data-s1d-gallery>'
        . '<h3>' . esc_html($title) . '</h3>'
        . '<div class="s1d-gallery__stage"><div class="swiper s1d-gallery__main"><div class="swiper-wrapper">' . $slides . '</div></div>'
        . ($multiple
            ? '<button type="button" class="s1d-gallery__nav s1d-gallery__prev" aria-label="' . esc_attr(s1_text('Föregående bild', 'Previous photo')) . '">' . s1d_icon('chevron-left', 22) . '</button>'
              . '<button type="button" class="s1d-gallery__nav s1d-gallery__next" aria-label="' . esc_attr(s1_text('Nästa bild', 'Next photo')) . '">' . s1d_icon('chevron-right', 22) . '</button>'
              . '<span class="s1d-gallery__count" aria-live="polite">1 / ' . count($ids) . '</span>'
            : '')
        . '</div>'
        . ($multiple ? '<div class="swiper s1d-gallery__thumbs" aria-hidden="true"><div class="swiper-wrapper">' . $thumbs . '</div></div>' : '')
        . '</section>';
}

function s1_development_event_card(array $event): string {
    $status = $event['past'] ? 'past' : 'upcoming';
    $dialog = 's1d-event-' . (int) $event['id'];
    $photos = count($event['gallery'] ?? []);
    $cover = (int) ($event['image_id'] ?: ($event['gallery'][0] ?? 0));
    $media = $cover > 0
        ? '<div class="s1d-card__media">' . s1d_image($cover, 's1d-card__img', '(max-width: 767px) 100vw, 340px')
            . ($photos > 0 ? '<span class="s1d-card__photos">' . s1d_icon('camera', 16) . esc_html($photos === 1 ? s1_text('1 bild', '1 photo') : sprintf(s1_text('%d bilder', '%d photos'), $photos)) . '</span>' : '')
            . '</div>'
        : '';
    $meta = '';
    if ($event['dates'] !== '') $meta .= '<li>' . s1d_icon('calendar') . '<span>' . esc_html($event['dates']) . '</span></li>';
    if ($event['place'] !== '') $meta .= '<li>' . s1d_icon('pin') . '<span>' . esc_html($event['place']) . '</span></li>';
    if ($event['coach'] !== '') $meta .= '<li class="s1d-card__muted">' . s1d_icon('user') . '<span>' . esc_html(s1_text('Coach', 'Coach')) . ': <strong>' . esc_html($event['coach']) . '</strong></span></li>';
    return '<article class="s1d-card' . ($media !== '' ? ' s1d-card--media' : '') . '" data-s1d-status="' . $status . '">'
        . $media
        . '<div class="s1d-card__top"><span class="s1d-eyebrow">' . esc_html($event['series']) . '</span>'
        . ($event['past']
            ? '<span class="s1d-badge s1d-badge--past">' . esc_html(s1_text('Genomfört', 'Completed')) . '</span>'
            : '<span class="s1d-badge">' . esc_html(s1_text('Kommande', 'Upcoming')) . '</span>')
        . '</div>'
        . '<h3 class="s1d-card__title">' . esc_html($event['title']) . '</h3>'
        . ($meta !== '' ? '<ul class="s1d-card__meta">' . $meta . '</ul>' : '')
        . ($event['summary'] !== '' ? '<p class="s1d-card__summary">' . esc_html($event['summary']) . '</p>' : '')
        . '<button type="button" class="s1d-btn ' . ($event['past'] ? 's1d-btn--ghost' : 's1d-btn--light') . ' s1d-card__btn" data-s1d-open="' . esc_attr($dialog) . '" aria-haspopup="dialog">'
        . esc_html($event['past'] ? s1_text('Se sammanfattning', 'View summary') : s1_text('Visa detaljer', 'View details')) . s1d_icon('arrow-right', 16) . '</button>'
        . '</article>';
}

function s1_development_event_dialog(array $event, int $fallback_image, string $sport_name): string {
    $id = 's1d-event-' . (int) $event['id'];
    $facts = [
        s1_text('Datum', 'Dates') => $event['dates'],
        s1_text('Plats', 'Location') => $event['place'],
        s1_text('Coach', 'Coach') => $event['coach'],
        s1_text('Avgift', 'Fee') => $event['fee'],
        s1_text('Ålder', 'Age group') => $event['age'],
        s1_text('Sista anmälan', 'Registration deadline') => $event['past'] ? '' : $event['deadline'],
    ];
    $dl = '';
    foreach ($facts as $label => $value) {
        if ($value !== '') $dl .= '<div><dt>' . esc_html($label) . '</dt><dd>' . esc_html($value) . '</dd></div>';
    }
    $gallery_ids = $event['gallery'] ?? [];
    $image_id = $event['image_id'] ?: ($gallery_ids[0] ?? 0) ?: $fallback_image;
    $gallery = s1d_gallery($gallery_ids, ($event['gallery_title'] ?? '') !== '' ? $event['gallery_title'] : ($event['past']
        ? s1_text('Bilder från eventet', 'Photos from the event')
        : s1_text('Bilder', 'Photos')));
    $body = '<span class="s1d-eyebrow">' . esc_html($event['series']) . '</span>'
        . '<h2 class="s1d-modal__title" id="' . esc_attr($id) . '-title">' . esc_html($event['title']) . '</h2>'
        . ($dl !== '' ? '<dl class="s1d-facts">' . $dl . '</dl>' : '');
    if ($event['past']) $body .= $gallery;
    if ($event['about'] !== '') {
        $body .= '<div class="s1d-modal__section"><h3>' . esc_html(s1_text('Om eventet', 'About the event')) . '</h3>' . s1d_paragraphs($event['about']) . '</div>';
    }
    if ($event['past']) {
        if ($event['result'] !== '') {
            $body .= '<div class="s1d-modal__section"><h3>' . esc_html(s1_text('Resultat och höjdpunkter', 'Results and highlights')) . '</h3>' . s1d_paragraphs($event['result']) . '</div>';
        }
        $body .= '<div class="s1d-modal__note"><p>' . esc_html(s1_text('Eventet är genomfört. Vill du vara med nästa gång?', 'This event has finished. Want to join next time?')) . '</p>'
            . '<a class="s1d-btn s1d-btn--light" href="' . esc_url(s1_booking_url(['s1_sport' => $sport_name])) . '">' . esc_html(s1_text('Kontakta oss', 'Contact us')) . '</a></div>';
    } else {
        if ($event['includes']) {
            $body .= '<div class="s1d-modal__section"><h3>' . esc_html(s1_text('Det här ingår', "What's included")) . '</h3><ul class="s1d-includes">';
            foreach ($event['includes'] as $item) $body .= '<li>' . s1d_icon('check') . '<span>' . esc_html($item) . '</span></li>';
            $body .= '</ul></div>';
        }
        $body .= $gallery;
        $body .= '<div class="s1d-modal__actions">'
            . '<a class="s1d-btn s1d-btn--light" href="' . esc_url(s1_booking_url(['s1_event' => $event['title'] . ($event['dates'] !== '' ? ' (' . $event['dates'] . ')' : ''), 's1_sport' => $sport_name])) . '">' . esc_html(s1_text('Anmäl intresse', 'Register interest')) . s1d_icon('arrow-up-right', 16) . '</a>'
            . '<a class="s1d-btn s1d-btn--outline" href="' . esc_url(home_url(s1_lang() === 'en' ? '/contact/' : '/kontakt/')) . '">' . esc_html(s1_text('Ställ en fråga', 'Ask a question')) . '</a>'
            . '</div>';
    }
    return '<dialog class="s1d-modal" id="' . esc_attr($id) . '" aria-labelledby="' . esc_attr($id) . '-title"><div class="s1d-modal__panel">'
        . '<div class="s1d-modal__media">' . s1d_image((int) $image_id, 's1d-modal__img', '(max-width: 760px) 100vw, 720px')
        . '<div class="s1d-modal__shade"></div>'
        . '<form method="dialog"><button class="s1d-close" type="submit" aria-label="' . esc_attr(s1_text('Stäng', 'Close')) . '">' . s1d_icon('close') . '</button></form></div>'
        . '<div class="s1d-modal__body">' . $body . '</div>'
        . '</div></dialog>';
}
