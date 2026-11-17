<?php
/**
 * Sample content for the local WordPress Playground test site (dev/blueprint.json).
 * Only ever runs inside Playground; it never touches the live site.
 * Images are downloaded from the live site's public media library.
 */
if (!defined('ABSPATH')) exit;

require_once ABSPATH . 'wp-admin/includes/file.php';
require_once ABSPATH . 'wp-admin/includes/media.php';
require_once ABSPATH . 'wp-admin/includes/image.php';

update_option('blogname', 'S1');
update_option('timezone_string', 'Europe/Stockholm');
update_option('permalink_structure', '/%postname%/');

function s1dev_image(string $url): int {
    $tmp = download_url($url, 60);
    if (is_wp_error($tmp)) {
        error_log('s1dev: could not download ' . $url . ': ' . $tmp->get_error_message());
        return 0;
    }
    $id = media_handle_sideload(['name' => basename((string) parse_url($url, PHP_URL_PATH)), 'tmp_name' => $tmp], 0);
    return is_wp_error($id) ? 0 : (int) $id;
}

function s1dev_page(string $title, string $slug, array $extra = []): int {
    return (int) wp_insert_post(array_merge([
        'post_type' => 'page',
        'post_status' => 'publish',
        'post_title' => $title,
        'post_name' => $slug,
    ], $extra));
}

$uploads = 'https://s1sports.se/wp-content/uploads/';
$img = [
    'golf_hero' => s1dev_image($uploads . '2026/06/IMG_3959-1024x683.jpeg'),
    'golf_card' => s1dev_image($uploads . '2026/09/WhatsApp-Image-2025-05-12-at-19.28.53-1.jpeg'),
    'tennis_hero' => s1dev_image($uploads . '2026/08/WhatsApp-Image-2026-06-16-at-13.37.16.jpeg'),
    'tennis_card' => s1dev_image($uploads . '2026/06/andrew-heald-n9RZ0dCunpY-unsplash-scaled.jpg'),
    'cta' => s1dev_image($uploads . '2026/05/Rectangle-93.png'),
    'arena1' => s1dev_image($uploads . '2026/05/Rectangle-70.png'),
    'arena2' => s1dev_image($uploads . '2026/05/Rectangle-71.png'),
    'arena3' => s1dev_image($uploads . '2026/05/Rectangle-72-1.png'),
    'tennis1' => s1dev_image($uploads . '2026/09/WhatsApp-Image-2026-07-29-at-17.18.58.jpeg'),
    'tennis2' => s1dev_image($uploads . '2026/09/WhatsApp-Image-2026-06-29-at-16.25.44.jpeg'),
    'tennis3' => s1dev_image($uploads . '2026/09/WhatsApp-Image-2025-08-10-at-21.26.32-e1790786759722.jpeg'),
    'golf1' => s1dev_image($uploads . '2026/08/Oscar-Andreasson.jpg'),
];
if ($img['tennis1']) wp_update_post(['ID' => $img['tennis1'], 'post_excerpt' => 'Testbildtext: matchspel under turneringen.']);
$galleries = [
    'ITF Malmö' => [$img['arena1'], $img['arena2'], $img['arena3']],
    'ITF Helsingborg' => [$img['tennis1'], $img['tennis2'], $img['tennis3'], $img['tennis_card']],
    'Vårläger Falsterbo' => [$img['golf1'], $img['golf_card'], $img['golf_hero']],
    'S1 Golf Camp' => [$img['golf_hero']],
];

$start = s1dev_page('Start', 'start');
update_option('show_on_front', 'page');
update_option('page_on_front', $start);
// Every page that has a snapshot, so all 18 can be checked locally (English is picked by slug without WPML).
foreach ([
    'junior-utveckling' => 'Juniorutveckling', 'boka-mote' => 'Boka möte', 'kontakt' => 'Kontakt',
    'vara-tjanster' => 'Våra tjänster', 'vara-idrottare' => 'Våra idrottare', 'om-oss' => 'Om oss',
    'professionell-radgivning' => 'Professionell rådgivning', 'college-rekrytering' => 'Collegerekrytering',
    'home' => 'Home', 'junior-development' => 'Junior development', 'about-us' => 'About us',
    'pro-consulting' => 'Professional guidance', 'our-services' => 'Our services', 'our-athletes' => 'Our athletes',
    'contact' => 'Contact', 'college-recruiting' => 'College recruiting', 'book-a-meeting' => 'Book a meeting',
] as $slug => $title) {
    s1dev_page($title, $slug);
}

$golf = s1dev_page('Development Golf', 'development-golf', [
    'menu_order' => 1,
    'page_template' => 'template-development.php',
    'meta_input' => [
        's1_dev_sport' => 'golf',
        's1_dev_hero_image' => $img['golf_hero'],
        's1_dev_intro' => 'Träningsläger, tävlingar och college showcases för juniorer som vill ta nästa steg, med S1-coach på plats.',
        's1_dev_heading_bold' => 'Development',
        's1_dev_heading_italic' => 'Golf',
        's1_card_image' => $img['golf_card'],
        's1_card_text' => 'Träningsläger, tävlingar och college showcases med S1-coach.',
        's1_dev_cta_image' => $img['cta'],
    ],
]);
$tennis = s1dev_page('Development Tennis', 'development-tennis', [
    'menu_order' => 2,
    'page_template' => 'template-development.php',
    'meta_input' => [
        's1_dev_sport' => 'tennis',
        's1_dev_hero_image' => $img['tennis_hero'],
        's1_dev_intro' => 'Vi reser med våra juniorer till utvalda ITF-turneringar och arrangerar egna läger, med S1-coach på plats hela vägen.',
        's1_dev_heading_bold' => 'Development',
        's1_dev_heading_italic' => 'Tennis',
        's1_card_image' => $img['tennis_card'],
        's1_card_text' => 'ITF-turneringar, läger och tävlingsresor med S1-coach.',
        's1_dev_cta_image' => $img['cta'],
    ],
]);

$events = [
    ['tennis', 'ITF Malmö', 'ITF Junior · U18', '20270309', '20270315', 'Malmö, Sverige', 'Testcoach', '450 kr/dag + coachkostnader', 'U18', '15 februari',
        'Turnering på hemmaplan med S1-coach på plats under hela veckan.',
        "Vi hjälper till med anmälan, planering och matchförberedelser.\n\nCoachen följer spelarna genom kval och huvudturnering.",
        "Coach på plats under hela turneringen\nHjälp med anmälan och planering\nMatchförberedelser och analys efter match", ''],
    ['tennis', 'ITF Athens', 'ITF Junior · U18', '20270405', '20270411', 'Aten, Grekland', 'Testcoach', '550 kr/dag + coachkostnader', 'U18', '1 mars',
        'Tävlingsresa där gruppen reser och bor tillsammans under turneringen.', 'Vi reser tillsammans och bor nära anläggningen.',
        "Coach på plats under hela resan\nHjälp med resa och boende", ''],
    ['tennis', 'S1 Tennis Camp', 'Träningsläger · U14–U18', '20270621', '20270625', 'Båstad', '', '', 'U14–U18', '',
        'Intensiva träningsdagar med fokus på teknik, matchspel och fysträning.', '', '', ''],
    ['tennis', 'ITF Helsingborg', 'ITF Junior · U18', '20261005', '20261011', 'Helsingborg, Sverige', 'Testcoach', '', 'U18', '',
        'Vecka med kval och huvudturnering.', 'Testbeskrivning av en genomförd turnering.', '', 'Två spelare gick vidare från kvalet och en nådde kvartsfinal.'],
    ['golf', 'S1 Golf Camp', 'Träningsläger · U16–U21', '20270510', '20270514', 'Halmstad', 'Testcoach', '6 500 kr', 'U16–U21', '15 april',
        'Träningsdagar med fokus på närspel, banstrategi och mental träning.', 'Testbeskrivning av lägret.',
        "Träning på range, närspel och bana\nBanstrategi och mental träning", ''],
    ['golf', 'College Showcase Stockholm', 'College Showcase · U18–U21', '20270612', '', 'Stockholm', 'Testcoach', '', 'U18–U21', '20 maj',
        'Visa upp ditt spel för collegetränare.', '', '', ''],
    ['golf', 'Vårläger Falsterbo', 'Träningsläger', '20260420', '20260424', 'Falsterbo', 'Testcoach', '', 'U16–U21', '',
        'Fem dagar med fokus på säsongsstarten.', '', '', 'Bra start på säsongen för hela gruppen.'],
];
foreach ($events as [$sport, $title, $series, $start_date, $end_date, $place, $coach, $fee, $age, $deadline, $summary, $about, $includes, $result]) {
    wp_insert_post([
        'post_type' => 's1_event',
        'post_status' => 'publish',
        'post_title' => $title,
        'meta_input' => [
            's1_event_gallery' => implode(',', array_filter($galleries[$title] ?? [])),
            's1_event_gallery_title' => $title === 'ITF Malmö' ? 'Om arenan' : '',
            's1_event_sport' => $sport, 's1_event_series' => $series,
            's1_event_start' => $start_date, 's1_event_end' => $end_date,
            's1_event_place' => $place, 's1_event_coach' => $coach, 's1_event_fee' => $fee,
            's1_event_age' => $age, 's1_event_deadline' => $deadline,
            's1_event_summary' => $summary, 's1_event_about' => $about,
            's1_event_includes' => $includes, 's1_event_result' => $result,
        ],
    ]);
}
flush_rewrite_rules();
