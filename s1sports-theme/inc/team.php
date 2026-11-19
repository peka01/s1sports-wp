<?php
/**
 * "Vårt team" on Om oss / About us. The section is in the snapshot but hidden by Breakdance on
 * every screen size. Editors prepare it in the tab "Vårt team" (photo, name, role and text per
 * person, two extra places for new people) and switch it on with "Visa teamet på sajten".
 * Signed-in editors can look at it before that with ?s1_team_preview=1.
 *
 * Meta: s1_team_show, s1_team_heading_b/_i, s1_team_<n>_show/_image/_name/_role/_text (n = 1..8).
 * Images are copied to the English page by WPML; the rest is per language.
 */
defined('ABSPATH') || exit;

const S1_TEAM_SLOTS = 8;

/** The team section in a snapshot: section and heading classes, and the cards with their markup and texts. */
function s1_snapshot_team(array $entry): ?array {
    static $cache = [];
    $file = (string) $entry['file'];
    if (array_key_exists($file, $cache)) return $cache[$file];
    ['body' => $body, 'pid' => $pid] = s1_snapshot_body($entry);
    if ($pid === '' || !preg_match('~<h2 class="(bde-heading-' . $pid . '-\d+) bde-heading">\s*(VÅRT TEAM|OUR TEAM)\s*</h2>~u', $body, $heading, PREG_OFFSET_CAPTURE)) {
        return $cache[$file] = null;
    }
    $before = substr($body, 0, (int) $heading[0][1]);
    if (!preg_match_all('~<section class="(bde-section-' . $pid . '-\d+) bde-section">~', $before, $sections)) return $cache[$file] = null;
    $card = '~<div class="(bde-div-' . $pid . '-\d+) bde-div">\s*<div class="section-background-overlay bde-section-background-overlay"></div>\s*'
        . '<div class="bde-text-' . $pid . '-\d+ bde-text">([^<]*)</div>\s*<div class="bde-text-' . $pid . '-\d+ bde-text">([^<]*)</div>\s*<div class="bde-text-' . $pid . '-\d+ bde-text">([^<]*)</div>\s*</div>~';
    if (!preg_match_all($card, substr($body, (int) $heading[0][1]), $cards, PREG_SET_ORDER)) return $cache[$file] = null;
    // The heading is shown like the page's other headings ("Vår Vision"): bold first word, italic rest.
    $words = preg_split('/\s+/u', s1_snapshot_plain($heading[2][0]), 2);
    $template = preg_match('~<h2 class="(bde-dual-heading-' . $pid . '-\d+)[ "]~', $body, $dual) ? $dual[1] : '';
    return $cache[$file] = [
        'section' => end($sections[1]),
        'heading_class' => $heading[1][0],
        'heading_template' => $template,
        'heading_b' => mb_convert_case(mb_strtolower($words[0] ?? ''), MB_CASE_TITLE, 'UTF-8'),
        'heading_i' => mb_strtolower($words[1] ?? ''),
        'card_pattern' => $card,
        'cards' => array_map(static fn(array $c): array => [
            'html' => $c[0], 'class' => $c[1],
            'name' => s1_snapshot_plain($c[2]), 'role' => s1_snapshot_plain($c[3]), 'text' => s1_snapshot_plain($c[4]),
            'hidden' => s1_team_card_hidden($entry, $c[1]),
        ], array_slice($cards, 0, S1_TEAM_SLOTS)),
    ];
}

/** Whether Breakdance hides this card itself on every screen size (Maximilian and Ludvig were hidden that way). */
function s1_team_card_hidden(array $entry, string $class): bool {
    $rules = s1_snapshot_hide_rules($entry)[$class] ?? [];
    foreach (S1_WIDTHS as $width) {
        $hidden = false;
        foreach ($rules as [$min, $max]) if ($width >= $min && $width <= $max) $hidden = true;
        if (!$hidden) return false;
    }
    return (bool) $rules;
}

/** Shown by default: the people the snapshot shows; new places and hidden cards are off. */
function s1_team_slot_default(array $team, int $n): bool {
    $card = $team['cards'][$n - 1] ?? null;
    return $card !== null && !$card['hidden'];
}

function s1_team_value(int $post_id, string $key, string $default): string {
    $value = get_post_meta($post_id, $key, true);
    return (is_string($value) && trim($value) !== '') ? $value : $default;
}

/** Whether a slot is shown: the saved choice, or the default. */
function s1_team_slot_shown(int $post_id, int $n, array $team): bool {
    $value = get_post_meta($post_id, 's1_team_' . $n . '_show', true);
    return $value === '' ? s1_team_slot_default($team, $n) : $value === '1';
}

/** Show the team (when switched on, or as a preview for signed-in editors) with the texts and photos from wp-admin. */
function s1_apply_page_team(string $html, int $post_id, array $entry): string {
    $team = s1_snapshot_team($entry);
    if (!$team) return $html;
    $preview = isset($_GET['s1_team_preview']) && current_user_can('edit_pages');
    if (get_post_meta($post_id, 's1_team_show', true) !== '1' && !$preview) return $html;

    // Cards: rebuild the grid from the snapshot cards, in slot order; new people use the last card as template.
    preg_match_all($team['card_pattern'], $html, $found, PREG_OFFSET_CAPTURE);
    if (!$found[0]) return $html;
    $first = (int) $found[0][0][1];
    $last = end($found[0]);
    $end = (int) $last[1] + strlen($last[0]);
    $visible = array_values(array_filter($team['cards'], static fn(array $c): bool => !$c['hidden']));
    $fallback = $visible ? end($visible) : end($team['cards']);
    $cards = '';
    $unhide = [];
    for ($n = 1; $n <= S1_TEAM_SLOTS; $n++) {
        if (!s1_team_slot_shown($post_id, $n, $team)) continue;
        $source = $team['cards'][$n - 1] ?? null;
        $template = $source ?? $fallback;
        if ($template['hidden']) $unhide[$template['class']] = true;
        $name = s1_team_value($post_id, 's1_team_' . $n . '_name', $source['name'] ?? '');
        if (trim($name) === '') continue;
        $values = [
            $name,
            s1_team_value($post_id, 's1_team_' . $n . '_role', $source['role'] ?? ''),
            s1_team_value($post_id, 's1_team_' . $n . '_text', $source['text'] ?? ''),
        ];
        $index = 0;
        $card = (string) preg_replace_callback('~(<div class="bde-text-[^"]*">)[^<]*(</div>)~', static function (array $m) use ($values, &$index): string {
            $text = $values[$index++] ?? '';
            return $m[1] . ' ' . implode('<br>', array_map('esc_html', explode("\n", str_replace("\r", '', trim($text))))) . ' ' . $m[2];
        }, $template['html']);
        $image = (int) get_post_meta($post_id, 's1_team_' . $n . '_image', true);
        $url = $image > 0 ? wp_get_attachment_image_url($image, 'large') : false;
        if ($url) {
            // Photo behind the card, darker towards the bottom where the texts are.
            $card = (string) preg_replace('~^<div class="([^"]*)"~', '<div class="$1" style="background:#000 url(&quot;' . esc_url($url) . '&quot;) center/cover no-repeat"', $card, 1);
            $card = str_replace('<div class="section-background-overlay bde-section-background-overlay"></div>',
                '<div class="section-background-overlay bde-section-background-overlay" style="position:absolute;inset:0;background:linear-gradient(180deg,rgba(0,0,0,0) 30%,rgba(0,0,0,.85) 100%)"></div>', $card);
        }
        $cards .= $card . ' ';
    }
    $html = substr_replace($html, $cards, $first, $end - $first);

    $bold = s1_team_value($post_id, 's1_team_heading_b', $team['heading_b']);
    $italic = s1_team_value($post_id, 's1_team_heading_i', $team['heading_i']);
    $new = $team['heading_template'] !== ''
        ? '<h2 class="' . esc_attr($team['heading_template']) . ' bde-dual-heading"> <span data-style="primary" class="dual-heading--primary ">' . esc_html($bold) . '</span> <span data-style="secondary" class="dual-heading--secondary ">' . esc_html($italic) . '</span> </h2>'
        : '<h2 class="' . esc_attr($team['heading_class']) . ' bde-heading"> ' . esc_html(trim($bold . ' ' . $italic)) . ' </h2>';
    $html = (string) preg_replace_callback('~<h2 class="' . preg_quote($team['heading_class'], '~') . ' bde-heading">[\s\S]*?</h2>~', static fn(): string => $new, $html, 1);

    $style = '<style id="s1-team">.breakdance .' . $team['section'] . '.bde-section{display:block!important}'
        . implode('', array_map(static fn(string $class): string => '.breakdance .' . $team['section'] . ' .' . $class . '.bde-div{display:flex!important}', array_keys($unhide)))
        . '.breakdance .' . $team['section'] . ' .bde-section-background-overlay{pointer-events:none}</style>';
    if (($header_end = strpos($html, '</header>')) !== false) $html = substr_replace($html, $style, $header_end + strlen('</header>'), 0);
    return $html;
}

/** The fields of the tab "Vårt team" for a page that has the team section. */
function s1_team_fields(array $entry, string $prefix, int $post_id): array {
    $team = s1_snapshot_team($entry);
    if (!$team) return [];
    $preview = $post_id ? add_query_arg('s1_team_preview', '1', get_permalink($post_id)) : '';
    $text = static fn(string $key, string $label, string $original, array $extra = []): array => [
        'key' => $prefix . $key, 'name' => $key, 'label' => $label, 'type' => 'text',
        'default_value' => $original, 's1_original' => $original,
        'wrapper' => ['data-s1-original' => $original] + ($extra['wrapper'] ?? []), 'wpml_cf_preferences' => 2,
    ] + $extra;
    $fields = [
        ['key' => $prefix . 'tab_team', 'label' => 'Vårt team', 'type' => 'tab', 'placement' => 'top'],
        ['key' => $prefix . 'team_help', 'label' => '', 'type' => 'message',
            'message' => 'Teamet syns inte på sajten förrän du slår på <strong>Visa teamet på sajten</strong> och klickar på <strong>Uppdatera</strong>. '
                . 'Du kan förbereda allt innan dess. Spara med Uppdatera och titta sedan här: '
                . ($preview ? '<a href="' . esc_url($preview) . '" target="_blank" rel="noopener">Förhandsgranska teamet</a> (bara du som är inloggad ser det).' : '')
                . '<br>Fotot blir bakgrund i personens kort och mörkas nertill så att texten syns. Stående foton passar bäst.'],
        ['key' => $prefix . 's1_team_show', 'name' => 's1_team_show', 'label' => 'Visa teamet på sajten', 'type' => 'true_false',
            'ui' => 1, 'ui_on_text' => 'Ja', 'ui_off_text' => 'Nej', 'default_value' => 0, 'wpml_cf_preferences' => 2],
        $text('s1_team_heading_b', 'Rubrik, fet del', $team['heading_b'], ['wrapper' => ['width' => '50']]),
        $text('s1_team_heading_i', 'Rubrik, kursiv del', $team['heading_i'], ['wrapper' => ['width' => '50']]),
    ];
    for ($n = 1; $n <= S1_TEAM_SLOTS; $n++) {
        $card = $team['cards'][$n - 1] ?? null;
        $title = $card ? 'Person ' . $n . ': ' . esc_html(mb_convert_case($card['name'], MB_CASE_TITLE, 'UTF-8')) . ($card['hidden'] ? ' (dold i dag)' : '') : 'Person ' . $n . ' (ny plats)';
        $fields[] = ['key' => $prefix . 'team_person_' . $n, 'label' => '', 'type' => 'message',
            'message' => '<strong style="font-size:14px">' . $title . '</strong>'];
        $fields[] = ['key' => $prefix . 's1_team_' . $n . '_show', 'name' => 's1_team_' . $n . '_show', 'label' => 'Visa personen', 'type' => 'true_false',
            'ui' => 1, 'ui_on_text' => 'Ja', 'ui_off_text' => 'Nej', 'default_value' => s1_team_slot_default($team, $n) ? 1 : 0, 'wrapper' => ['width' => '20'], 'wpml_cf_preferences' => 2];
        $fields[] = ['key' => $prefix . 's1_team_' . $n . '_image', 'name' => 's1_team_' . $n . '_image', 'label' => 'Foto', 'type' => 'image',
            'return_format' => 'id', 'preview_size' => 'thumbnail', 'library' => 'all', 'wrapper' => ['width' => '80'], 'wpml_cf_preferences' => 1];
        if ($card) {
            $fields[] = $text('s1_team_' . $n . '_name', 'Namn', $card['name'], ['wrapper' => ['width' => '50']]);
            $fields[] = $text('s1_team_' . $n . '_role', 'Roll', $card['role'], ['wrapper' => ['width' => '50']]);
            $fields[] = $text('s1_team_' . $n . '_text', 'Text', $card['text']);
        } else {
            $fields[] = ['key' => $prefix . 's1_team_' . $n . '_name', 'name' => 's1_team_' . $n . '_name', 'label' => 'Namn', 'type' => 'text', 'wrapper' => ['width' => '50'], 'wpml_cf_preferences' => 2];
            $fields[] = ['key' => $prefix . 's1_team_' . $n . '_role', 'name' => 's1_team_' . $n . '_role', 'label' => 'Roll', 'type' => 'text', 'wrapper' => ['width' => '50'], 'wpml_cf_preferences' => 2];
            $fields[] = ['key' => $prefix . 's1_team_' . $n . '_text', 'name' => 's1_team_' . $n . '_text', 'label' => 'Text', 'type' => 'text', 'wpml_cf_preferences' => 2];
        }
    }
    return $fields;
}
