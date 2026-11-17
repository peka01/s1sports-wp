<?php
/**
 * Events for the Development Golf / Development Tennis pages.
 *
 * Editors add events under "Event" in wp-admin. An event counts as completed
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

add_action('acf/init', function (): void {
    if (!function_exists('acf_add_local_field_group')) return;
    $half = ['width' => '50'];
    acf_add_local_field_group([
        'key' => 'group_s1_event',
        'title' => 'Eventinformation',
        'fields' => [
            ['key' => 'field_s1_event_sport', 'label' => 'Sport', 'name' => 's1_event_sport', 'type' => 'select', 'required' => 1,
                'instructions' => 'Styr om eventet visas på Development Golf eller Development Tennis.',
                'choices' => s1_event_sports(), 'default_value' => 'tennis', 'return_format' => 'value', 'wrapper' => $half],
            ['key' => 'field_s1_event_series', 'label' => 'Typ av event', 'name' => 's1_event_series', 'type' => 'text',
                'instructions' => 'Visas ovanför namnet.', 'placeholder' => 'ITF Junior · U18', 'wrapper' => $half],
            ['key' => 'field_s1_event_start', 'label' => 'Startdatum', 'name' => 's1_event_start', 'type' => 'date_picker', 'required' => 1,
                'display_format' => 'Y-m-d', 'return_format' => 'Ymd', 'first_day' => 1, 'wrapper' => $half],
            ['key' => 'field_s1_event_end', 'label' => 'Slutdatum', 'name' => 's1_event_end', 'type' => 'date_picker',
                'instructions' => 'Lämna tomt för endagsevent. Eventet flyttas till Genomförda dagen efter.',
                'display_format' => 'Y-m-d', 'return_format' => 'Ymd', 'first_day' => 1, 'wrapper' => $half],
            ['key' => 'field_s1_event_place', 'label' => 'Plats', 'name' => 's1_event_place', 'type' => 'text', 'placeholder' => 'Malmö, Sverige', 'wrapper' => $half],
            ['key' => 'field_s1_event_coach', 'label' => 'Coach', 'name' => 's1_event_coach', 'type' => 'text', 'wrapper' => $half],
            ['key' => 'field_s1_event_fee', 'label' => 'Avgift', 'name' => 's1_event_fee', 'type' => 'text', 'placeholder' => '450 kr/dag + coachkostnader', 'wrapper' => $half],
            ['key' => 'field_s1_event_age', 'label' => 'Ålder', 'name' => 's1_event_age', 'type' => 'text', 'placeholder' => 'U18', 'wrapper' => $half],
            ['key' => 'field_s1_event_deadline', 'label' => 'Sista anmälan', 'name' => 's1_event_deadline', 'type' => 'text', 'placeholder' => '1 augusti', 'wrapper' => $half],
            ['key' => 'field_s1_event_summary', 'label' => 'Kort beskrivning', 'name' => 's1_event_summary', 'type' => 'textarea', 'rows' => 2, 'new_lines' => '',
                'instructions' => 'Visas på eventkortet. Håll den till en eller två meningar.'],
            ['key' => 'field_s1_event_about', 'label' => 'Om eventet', 'name' => 's1_event_about', 'type' => 'textarea', 'rows' => 6, 'new_lines' => '',
                'instructions' => 'Visas i rutan som öppnas när besökaren klickar på eventet.'],
            ['key' => 'field_s1_event_includes', 'label' => 'Det här ingår', 'name' => 's1_event_includes', 'type' => 'textarea', 'rows' => 4, 'new_lines' => '',
                'instructions' => 'En punkt per rad. Visas för kommande event.'],
            ['key' => 'field_s1_event_result', 'label' => 'Resultat och höjdpunkter', 'name' => 's1_event_result', 'type' => 'textarea', 'rows' => 4, 'new_lines' => '',
                'instructions' => 'Fyll i efter eventet. Visas när eventet är genomfört.'],
        ],
        'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 's1_event']]],
        'position' => 'acf_after_title',
        'style' => 'default',
        'label_placement' => 'top',
        'active' => true,
    ]);
});

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
 * (soonest first), then completed (most recent first).
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
            'coach' => s1_event_meta($id, 's1_event_coach'),
            'fee' => s1_event_meta($id, 's1_event_fee'),
            'age' => s1_event_meta($id, 's1_event_age'),
            'deadline' => s1_event_meta($id, 's1_event_deadline'),
            'summary' => s1_event_meta($id, 's1_event_summary'),
            'about' => s1_event_meta($id, 's1_event_about'),
            'includes' => $includes,
            'result' => s1_event_meta($id, 's1_event_result'),
            'image_id' => (int) get_post_thumbnail_id($post),
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

add_filter('manage_s1_event_posts_columns', function (array $columns): array {
    return [
        'cb' => $columns['cb'] ?? '',
        'title' => 'Event',
        's1_event_sport' => 'Sport',
        's1_event_dates' => 'Datum',
        's1_event_status' => 'Status',
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
        echo s1_event_is_past($start, $end) ? 'Genomfört' : 'Kommande';
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
