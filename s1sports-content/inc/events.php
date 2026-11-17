<?php
/**
 * Events for the Development Golf / Development Tennis pages.
 *
 * Editors add events under "Event" in wp-admin. An event counts as previous ("Tidigare")
 * from the day after its end date (or start date when it has no end date).
 */
defined('ABSPATH') || exit;

function s1_event_sports(): array {
    return ['golf' => 'Golf', 'tennis' => 'Tennis'];
}

add_action('init', function (): void {
    register_post_type('s1_event', [
        'labels' => [
            'name' => 'Event',
            'singular_name' => 'Event',
            'menu_name' => 'Event',
            'all_items' => 'Alla event',
            'add_new' => 'Lägg till event',
            'add_new_item' => 'Lägg till nytt event',
            'edit_item' => 'Redigera event',
            'new_item' => 'Nytt event',
            'search_items' => 'Sök event',
            'not_found' => 'Inga event hittades',
            'not_found_in_trash' => 'Inga event i papperskorgen',
            'featured_image' => 'Eventbild',
            'set_featured_image' => 'Välj eventbild',
            'remove_featured_image' => 'Ta bort eventbild',
            'use_featured_image' => 'Använd som eventbild',
        ],
        'description' => 'Turneringar, läger och tävlingsresor som visas på Development-sidorna.',
        'public' => false,
        'show_ui' => true,
        'show_in_menu' => true,
        'show_in_nav_menus' => false,
        'show_in_admin_bar' => true,
        'exclude_from_search' => true,
        'publicly_queryable' => false,
        'has_archive' => false,
        'rewrite' => false,
        'show_in_rest' => false,
        'supports' => ['title', 'thumbnail'],
        'menu_icon' => 'dashicons-calendar-alt',
        'menu_position' => 21,
    ]);
});

add_filter('enter_title_here', function (string $text, WP_Post $post): string {
    return $post->post_type === 's1_event' ? 'Eventets namn, t.ex. ITF Malmö' : $text;
}, 10, 2);

add_action('acf/include_field_types', function (): void {
    require_once __DIR__ . '/acf-gallery-field.php';
    acf_register_field_type('S1_ACF_Field_Gallery');
});

/*
 * The event form follows what the visitor sees: the card in the list, then the box that
 * opens when they click, then the photos, then what is added after the event.
 * The event name is the title field above the form.
 */
add_action('acf/init', function (): void {
    if (!function_exists('acf_add_local_field_group')) return;
    $half = ['width' => '50'];
    $date = ['type' => 'date_picker', 'display_format' => 'Y-m-d', 'return_format' => 'Ymd', 'first_day' => 1, 'wrapper' => $half];
    $text = ['type' => 'textarea', 'new_lines' => ''];
    acf_add_local_field_group([
        'key' => 'group_s1_event',
        'title' => 'Eventet',
        // ACF Multilingual "Expert" mode: each field says whether it is translated or copied (see s1_wpml_field_prefs).
        'acfml_field_group_mode' => 'advanced',
        'fields' => s1_wpml_field_prefs([
            ['key' => 'field_s1_event_tab_card', 'label' => '1. Listan', 'type' => 'tab'],
            ['key' => 'field_s1_event_card_help', 'label' => '', 'type' => 'message',
                'message' => 'Det här visas på eventets rad i listan. Eventets namn skriver du i titelfältet ovanför.'],
            ['key' => 'field_s1_event_sport', 'label' => 'Sport', 'name' => 's1_event_sport', 'type' => 'select', 'required' => 1,
                'instructions' => 'Eventet visas på Development Golf eller Development Tennis.',
                'choices' => s1_event_sports(), 'default_value' => 'tennis', 'return_format' => 'value', 'wrapper' => $half],
            ['key' => 'field_s1_event_series', 'label' => 'Typ av event', 'name' => 's1_event_series', 'type' => 'text',
                'instructions' => 'Liten text ovanför namnet.', 'placeholder' => 'ITF Junior · U18', 'wrapper' => $half],
            ['key' => 'field_s1_event_image', 'label' => 'Eventbild', 'name' => 's1_event_image', 'type' => 'image',
                'instructions' => 'Till höger på raden i listan och överst i rutan. Lämna tomt för att använda första bilden under fliken Bilder.',
                'return_format' => 'id', 'preview_size' => 'medium', 'library' => 'all'],
            ['key' => 'field_s1_event_start', 'label' => 'Startdatum', 'name' => 's1_event_start', 'required' => 1] + $date,
            ['key' => 'field_s1_event_end', 'label' => 'Slutdatum', 'name' => 's1_event_end',
                'instructions' => 'Lämna tomt för endagsevent. Eventet flyttas till Tidigare dagen efter.'] + $date,
            ['key' => 'field_s1_event_place', 'label' => 'Plats', 'name' => 's1_event_place', 'type' => 'text', 'placeholder' => 'Malmö, Sverige'],
            ['key' => 'field_s1_event_summary', 'label' => 'Kort beskrivning', 'name' => 's1_event_summary', 'rows' => 2,
                'instructions' => 'En eller två meningar.'] + $text,

            ['key' => 'field_s1_event_tab_box', 'label' => '2. Rutan', 'type' => 'tab'],
            ['key' => 'field_s1_event_box_help', 'label' => '', 'type' => 'message',
                'message' => 'Rutan öppnas när besökaren klickar på eventet. Den visar också datum och plats från fliken Listan.'],
            ['key' => 'field_s1_event_fee', 'label' => 'Avgift', 'name' => 's1_event_fee', 'type' => 'text', 'placeholder' => '450 kr/dag', 'wrapper' => ['width' => '34']],
            ['key' => 'field_s1_event_age', 'label' => 'Ålder', 'name' => 's1_event_age', 'type' => 'text', 'placeholder' => 'U18', 'wrapper' => ['width' => '33']],
            ['key' => 'field_s1_event_deadline', 'label' => 'Sista anmälan', 'name' => 's1_event_deadline', 'type' => 'text', 'placeholder' => '1 augusti', 'wrapper' => ['width' => '33'],
                'instructions' => 'Visas bara för kommande event.'],
            ['key' => 'field_s1_event_about', 'label' => 'Om eventet', 'name' => 's1_event_about', 'rows' => 6,
                'instructions' => 'Tom rad ger nytt stycke.'] + $text,
            ['key' => 'field_s1_event_includes', 'label' => 'Det här ingår', 'name' => 's1_event_includes', 'rows' => 4,
                'instructions' => 'En punkt per rad. Visas bara för kommande event, ovanför knappen Anmäl intresse.'] + $text,

            ['key' => 'field_s1_event_tab_photos', 'label' => '3. Bilder', 'type' => 'tab'],
            ['key' => 'field_s1_event_gallery_title', 'label' => 'Rubrik för bilderna', 'name' => 's1_event_gallery_title', 'type' => 'text',
                'instructions' => 'Lämna tomt för "Bilder från eventet" (tidigare event) eller "Bilder" (kommande).', 'placeholder' => 'Om arenan'],
            ['key' => 'field_s1_event_gallery', 'label' => 'Bildspel', 'name' => 's1_event_gallery', 'type' => 's1_gallery',
                'instructions' => 'Visas som bildspel i rutan. Lägg till bilder på banan eller arenan inför eventet, och bilder från eventet efteråt. Dra för att ändra ordning. Bildtexten hämtas från mediabiblioteket.'],

            ['key' => 'field_s1_event_tab_after', 'label' => '4. Efter eventet', 'type' => 'tab'],
            ['key' => 'field_s1_event_result', 'label' => 'Resultat och höjdpunkter', 'name' => 's1_event_result', 'rows' => 5,
                'instructions' => 'Visas i rutan när eventet har varit. Lägg gärna till bilder från eventet under fliken Bilder.'] + $text,
        ], ['s1_event_sport', 's1_event_start', 's1_event_end', 's1_event_image', 's1_event_gallery']),
        'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 's1_event']]],
        'position' => 'acf_after_title',
        'style' => 'default',
        'label_placement' => 'top',
        'hide_on_screen' => ['featured_image'],
        'active' => true,
    ]);
});

/**
 * Translation setting per field for WPML / ACF Multilingual: the fields in $copy are copied
 * from Swedish to the English translation (dates, sport, images, names), all other text is
 * translated. Matches wpml-config.xml. Ignored when WPML is not installed.
 */
function s1_wpml_field_prefs(array $fields, array $copy): array {
    foreach ($fields as &$field) {
        if (empty($field['name'])) continue;
        $field['wpml_cf_preferences'] = in_array($field['name'], $copy, true) ? 1 : 2;
    }
    return $fields;
}

/** Events made before the form had its own image field used the featured image: show it in the field until saved. */
add_filter('acf/load_value/key=field_s1_event_image', function ($value, $post_id) {
    return $value ?: ((int) get_post_thumbnail_id((int) $post_id) ?: $value);
}, 10, 2);

function s1_event_image_id(int $post_id): int {
    return metadata_exists('post', $post_id, 's1_event_image')
        ? (int) s1_event_meta($post_id, 's1_event_image')
        : (int) get_post_thumbnail_id($post_id);
}

function s1_event_meta(int $post_id, string $name): string {
    $value = get_post_meta($post_id, $name, true);
    return is_scalar($value) ? trim((string) $value) : '';
}

function s1_event_date(string $ymd): ?DateTimeImmutable {
    if (!preg_match('/^\d{8}$/', $ymd)) return null;
    $date = DateTimeImmutable::createFromFormat('!Ymd', $ymd, wp_timezone());
    return $date ?: null;
}

function s1_event_date_label(string $start, string $end): string {
    $from = s1_event_date($start);
    if (!$from) return '';
    $to = s1_event_date($end);
    if (!$to || $to <= $from) return wp_date('j M Y', $from->getTimestamp());
    if ($from->format('Ym') === $to->format('Ym')) {
        return wp_date('j', $from->getTimestamp()) . '–' . wp_date('j M Y', $to->getTimestamp());
    }
    if ($from->format('Y') === $to->format('Y')) {
        return wp_date('j M', $from->getTimestamp()) . ' – ' . wp_date('j M Y', $to->getTimestamp());
    }
    return wp_date('j M Y', $from->getTimestamp()) . ' – ' . wp_date('j M Y', $to->getTimestamp());
}

function s1_event_is_past(string $start, string $end): bool {
    $last = s1_event_date($end) ?: s1_event_date($start);
    return $last !== null && $last->format('Ymd') < current_time('Ymd');
}

/**
 * Published events for one sport in the current language, upcoming first
 * (soonest first), then previous (most recent first).
 */
function s1_get_events(string $sport): array {
    $posts = get_posts([
        'post_type' => 's1_event',
        'post_status' => 'publish',
        'numberposts' => -1,
        'suppress_filters' => false,
        'no_found_rows' => true,
        'meta_key' => 's1_event_sport',
        'meta_value' => $sport,
    ]);
    $events = [];
    foreach ($posts as $post) {
        $id = (int) $post->ID;
        $start = s1_event_meta($id, 's1_event_start');
        $end = s1_event_meta($id, 's1_event_end');
        $includes = array_values(array_filter(array_map('trim', preg_split('/\R/', s1_event_meta($id, 's1_event_includes')) ?: [])));
        $events[] = [
            'id' => $id,
            'title' => get_the_title($post),
            'series' => s1_event_meta($id, 's1_event_series'),
            'start' => $start,
            'end' => $end,
            'dates' => s1_event_date_label($start, $end),
            'place' => s1_event_meta($id, 's1_event_place'),
            'fee' => s1_event_meta($id, 's1_event_fee'),
            'age' => s1_event_meta($id, 's1_event_age'),
            'deadline' => s1_event_meta($id, 's1_event_deadline'),
            'summary' => s1_event_meta($id, 's1_event_summary'),
            'about' => s1_event_meta($id, 's1_event_about'),
            'includes' => $includes,
            'result' => s1_event_meta($id, 's1_event_result'),
            'image_id' => s1_event_image_id($id),
            'gallery' => s1_event_gallery_ids($id),
            'gallery_title' => s1_event_meta($id, 's1_event_gallery_title'),
            'past' => s1_event_is_past($start, $end),
        ];
    }
    usort($events, static function (array $a, array $b): int {
        if ($a['past'] !== $b['past']) return $a['past'] ? 1 : -1;
        $order = strcmp($a['start'] ?: '99999999', $b['start'] ?: '99999999');
        return $a['past'] ? -$order : $order;
    });
    return $events;
}

/*
 * Photo gallery: media library images per event, stored as comma-separated attachment IDs
 * in s1_event_gallery by the S1 gallery field (acf-gallery-field.php; ACF's own gallery needs ACF PRO).
 */
function s1_event_gallery_ids(int $post_id): array {
    $ids = array_filter(array_map('absint', explode(',', s1_event_meta($post_id, 's1_event_gallery'))));
    return array_values(array_unique(array_filter($ids, static fn(int $id): bool => wp_attachment_is_image($id))));
}

add_filter('manage_s1_event_posts_columns', function (array $columns): array {
    return [
        'cb' => $columns['cb'] ?? '',
        'title' => 'Event',
        's1_event_sport' => 'Sport',
        's1_event_dates' => 'Datum',
        's1_event_status' => 'Status',
        's1_event_photos' => 'Bilder',
        'date' => 'Publicerad',
    ];
});

add_action('manage_s1_event_posts_custom_column', function (string $column, int $post_id): void {
    $start = s1_event_meta($post_id, 's1_event_start');
    $end = s1_event_meta($post_id, 's1_event_end');
    if ($column === 's1_event_sport') {
        echo esc_html(s1_event_sports()[s1_event_meta($post_id, 's1_event_sport')] ?? '—');
    } elseif ($column === 's1_event_dates') {
        echo esc_html(s1_event_date_label($start, $end) ?: '—');
    } elseif ($column === 's1_event_status') {
        echo s1_event_is_past($start, $end) ? 'Tidigare' : 'Kommande';
    } elseif ($column === 's1_event_photos') {
        echo (int) count(s1_event_gallery_ids($post_id));
    }
}, 10, 2);

add_filter('manage_edit-s1_event_sortable_columns', function (array $columns): array {
    $columns['s1_event_dates'] = 's1_event_start';
    return $columns;
});

add_action('pre_get_posts', function (WP_Query $query): void {
    if (!is_admin() || !$query->is_main_query() || $query->get('post_type') !== 's1_event') return;
    $orderby = $query->get('orderby');
    if (!$orderby || $orderby === 's1_event_start') {
        $query->set('meta_key', 's1_event_start');
        $query->set('orderby', 'meta_value');
        if (!$query->get('order')) $query->set('order', 'DESC');
    }
});
