<?php
/**
 * "Innehåll på sidan" on the pages built from snapshots (Start, Juniorutveckling, Om oss …):
 * the texts and images in each page body can be changed in wp-admin, without touching the
 * design. The fields are found in the snapshot itself, one tab per part of the page, in page
 * order. Nothing is stored until something is changed: a text field shows today's text, an
 * emptied text field brings it back, and an empty image field keeps today's image.
 *
 * Meta keys:
 * - s1_hero_image (the big image at the top) and s1_img_<element number> for other images.
 *   Breakdance gives the same element the same number on the Swedish and the English page,
 *   so WPML copies an image choice to the English page.
 * - s1_txt_<element number> for texts, s1_txt_<n>_b / s1_txt_<n>_i for the bold and the
 *   italic part of a heading. Texts are written per language.
 *
 * Left out: the header and the shared footer, blocks shared by several pages (testimonials,
 * the athlete slider, the facts block), the athlete cards and the section on Juniorutveckling
 * that the Golf/Tennis cards replace.
 */
defined('ABSPATH') || exit;

/* ---------------------------------------------------------------- pages */

function s1_snapshot_manifest(): array {
    static $manifest = null;
    if ($manifest !== null) return $manifest;
    $manifest = json_decode((string) file_get_contents(get_template_directory() . '/snapshots/manifest.json'), true);
    return $manifest = is_array($manifest) ? $manifest : [];
}

/** Slugs of the pages rendered from snapshots, both languages (the Swedish front page is "start"). */
function s1_snapshot_slugs(): array {
    static $slugs = null;
    if ($slugs !== null) return $slugs;
    return $slugs = array_values(array_unique(array_merge(['start'], array_column(s1_snapshot_manifest(), 'slug'))));
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

/* ------------------------------------------------------ reading snapshots */

/**
 * The page body of a snapshot (between the header and the shared footer) and the page's own
 * Breakdance id, which every element that belongs to this page carries in its class.
 */
function s1_snapshot_body(array $entry): array {
    static $cache = [];
    $file = (string) $entry['file'];
    if (isset($cache[$file])) return $cache[$file];
    $html = (string) file_get_contents($file);
    // On Juniorutveckling this section is replaced by the Golf/Tennis cards, which have their own fields.
    if (in_array($entry['slug'] ?? '', ['junior-utveckling', 'junior-development'], true)) {
        $html = (string) preg_replace('/<section id="las-mer"[^>]*>[\s\S]*?<\/section>/', '', $html, 1);
    }
    $start = strpos($html, '</header>');
    if ($start === false) return $cache[$file] = ['body' => '', 'pid' => ''];
    $end = preg_match('/<section class="bde-section-108-/', $html, $footer, PREG_OFFSET_CAPTURE, $start) ? (int) $footer[0][1] : strlen($html);
    $body = substr($html, $start, $end - $start);
    $pid = preg_match('/class="bde-section-(\d+)-100[ "]/', $body, $m) ? $m[1] : '';
    return $cache[$file] = ['body' => $body, 'pid' => $pid];
}

/** Screen widths the theme checks visibility at: desktop, small desktop, tablet, large phone, phone. */
const S1_WIDTHS = [1280, 1100, 900, 600, 375];

/** display:none rules on single Breakdance classes, with the width range each applies to. */
function s1_snapshot_hide_rules(array $entry): array {
    static $cache = [];
    $key = implode('|', (array) $entry['css']);
    if (isset($cache[$key])) return $cache[$key];
    $css = '';
    foreach ((array) $entry['css'] as $path) {
        $file = get_template_directory() . '/' . $path;
        if (is_readable($file)) $css .= (string) file_get_contents($file) . "\n";
    }
    $rules = [];
    $collect = static function (string $chunk, int $min, int $max) use (&$rules): void {
        if (!preg_match_all('/([^{}]+)\{([^{}]*)\}/', $chunk, $found, PREG_SET_ORDER)) return;
        foreach ($found as $rule) {
            if (!preg_match('/(?:^|;)\s*display\s*:\s*none\s*(?:!important)?\s*(?:;|$)/', $rule[2])) continue;
            foreach (explode(',', $rule[1]) as $selector) {
                if (preg_match('/^\s*\.(bde-[a-z0-9-]+-\d+-\d+)\s*$/', $selector, $c)) $rules[$c[1]][] = [$min, $max];
            }
        }
    };
    $length = strlen($css);
    $pos = 0;
    $top = '';
    while (($at = strpos($css, '@media', $pos)) !== false) {
        $top .= substr($css, $pos, $at - $pos);
        $open = strpos($css, '{', $at);
        if ($open === false) break;
        $condition = substr($css, $at + 6, $open - $at - 6);
        $depth = 1;
        $i = $open + 1;
        while ($i < $length && $depth > 0) {
            if ($css[$i] === '{') $depth++;
            elseif ($css[$i] === '}') $depth--;
            $i++;
        }
        if (stripos($condition, 'print') === false) {
            $min = preg_match('/min-width:\s*(\d+)/', $condition, $a) ? (int) $a[1] : 0;
            $max = preg_match('/max-width:\s*(\d+)/', $condition, $b) ? (int) $b[1] : PHP_INT_MAX;
            $collect(substr($css, $open + 1, $i - $open - 2), $min, $max);
        }
        $pos = $i;
    }
    $collect($top . substr($css, $pos), 0, PHP_INT_MAX);
    return $cache[$key] = $rules;
}

/**
 * For every Breakdance element in the page body: at which of S1_WIDTHS it is hidden, by its own
 * rules or a parent's (Breakdance's "hide on breakpoint" settings).
 */
function s1_snapshot_hidden(array $entry): array {
    static $cache = [];
    $file = (string) $entry['file'];
    if (isset($cache[$file])) return $cache[$file];
    $rules = s1_snapshot_hide_rules($entry);
    $body = s1_snapshot_body($entry)['body'];
    $none = array_fill(0, count(S1_WIDTHS), false);
    $void = ['img', 'br', 'input', 'meta', 'link', 'source', 'hr', 'area', 'base', 'col', 'embed', 'param', 'track', 'wbr'];
    $stack = [];
    $hidden = [];
    preg_match_all('~<(/?)([a-zA-Z][a-zA-Z0-9-]*)\b([^>]*?)(/?)>~', $body, $tags, PREG_SET_ORDER);
    foreach ($tags as $tag) {
        $name = strtolower($tag[2]);
        if ($tag[1] === '/') {
            for ($i = count($stack) - 1; $i >= 0; $i--) {
                if ($stack[$i][0] === $name) { array_splice($stack, $i); break; }
            }
            continue;
        }
        $mask = $stack ? $stack[count($stack) - 1][1] : $none;
        if (preg_match('/\sclass="([^"]*)"/', ' ' . $tag[3], $c)) {
            $own = [];
            foreach (preg_split('/\s+/', trim($c[1])) as $class) {
                if (!preg_match('/^bde-[a-z0-9-]+-\d+-\d+$/', $class)) continue;
                $own[] = $class;
                foreach ($rules[$class] ?? [] as [$min, $max]) {
                    foreach (S1_WIDTHS as $i => $width) if ($width >= $min && $width <= $max) $mask[$i] = true;
                }
            }
            foreach ($own as $class) $hidden[$class] ??= $mask;
        }
        if ($tag[4] !== '/' && !in_array($name, $void, true)) $stack[] = [$name, $mask];
    }
    return $cache[$file] = $hidden;
}

/** 'all' when an element never shows, 'mobil'/'dator' when it only shows on phones or desktops, '' when it always shows. */
function s1_snapshot_visibility(array $entry, string $class): string {
    $mask = s1_snapshot_hidden($entry)[$class] ?? null;
    if (!$mask || !in_array(true, $mask, true)) return '';
    if (!in_array(false, $mask, true)) return 'all';
    $last = count($mask) - 1;
    if ($mask[0] && !$mask[$last]) return 'mobil';
    if ($mask[$last] && !$mask[0]) return 'dator';
    return '';
}

/** Plain text from a piece of snapshot HTML: <br> becomes a line break, a new <p> an empty line. */
function s1_snapshot_plain(string $html): string {
    $html = (string) preg_replace('~<br\s*/?>~i', "\x01", $html);
    $html = (string) preg_replace('~</p>\s*<p\b[^>]*>~i', "\x02", $html);
    $text = html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8');
    $text = (string) preg_replace('/\s+/u', ' ', $text);
    $text = str_replace(["\x02", "\x01"], ["\n\n", "\n"], $text);
    return trim((string) preg_replace('/ *\n */', "\n", $text));
}

function s1_page_image_text(string $html): string {
    return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8')));
}

function s1_page_image_key(string $class): string {
    $number = (int) substr($class, strrpos($class, '-') + 1);
    return ($number === 100 && strpos($class, 'bde-section-') === 0) ? 's1_hero_image' : 's1_img_' . $number;
}

/**
 * The images in a page body, in page order: <img> elements and elements that get a background
 * image from the page's CSS. Each: pos, key (meta key), type (img|bg), class, url (today's image), label.
 */
function s1_snapshot_images(array $entry): array {
    static $cache = [];
    $file = (string) $entry['file'];
    if (isset($cache[$file])) return $cache[$file];
    $body = s1_snapshot_body($entry)['body'];
    if ($body === '') return $cache[$file] = [];

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
    foreach ($spots as $class => $spot) {
        $visibility = s1_snapshot_visibility($entry, $class);
        if ($visibility === 'all') unset($spots[$class]);
        else $spots[$class]['only'] = $visibility;
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

/**
 * The texts in a page body that belong to the page itself, in page order. Each: pos, kind
 * (text|rich-text|heading|badge|button|dual), class, tag, key, text; a "dual" heading has
 * parts ['b' => bold text, 'i' => italic text] instead of text.
 */
function s1_snapshot_texts(array $entry): array {
    static $cache = [];
    $file = (string) $entry['file'];
    if (isset($cache[$file])) return $cache[$file];
    ['body' => $body, 'pid' => $pid] = s1_snapshot_body($entry);
    if ($body === '' || $pid === '') return $cache[$file] = [];
    preg_match_all('/<(\w+) class="(bde-(text|rich-text|heading|badge|button|dual-heading)-' . $pid . '-(\d+))[ "][^>]*>/', $body, $found, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    $items = [];
    foreach ($found as $m) {
        $tag = $m[1][0];
        $class = $m[2][0];
        $kind = $m[3][0];
        $number = $m[4][0];
        if (isset($items[$class])) continue;
        $after = $m[0][1] + strlen($m[0][0]);
        $visibility = s1_snapshot_visibility($entry, $class);
        if ($visibility === 'all') continue; // hidden on every screen size: nothing to edit
        $item = ['pos' => $m[0][1], 'kind' => $kind, 'class' => $class, 'tag' => $tag, 'key' => 's1_txt_' . $number, 'only' => $visibility];
        if ($kind === 'dual-heading') {
            $close = strpos($body, '</' . $tag . '>', $after);
            if ($close === false) continue;
            $inner = substr($body, $after, $close - $after);
            // The theme shows the first span bold and every span after it italic, so the heading has two
            // fields: the first span, and the rest joined (the Start heading has three spans:
            // "Utvecklar" + "nästa" + "generations idrottare"). The key suffix follows the first span's
            // Breakdance style (_b for "primary", _i for "secondary"); the rest gets the other one.
            if (!preg_match_all('/dual-heading--(primary|secondary)[^>]*>([\s\S]*?)<\/span>/', $inner, $spans, PREG_SET_ORDER)) continue;
            $first = $spans[0][1] === 'primary' ? 'b' : 'i';
            $item['kind'] = 'dual';
            $item['spans'] = count($spans);
            $item['parts'] = [$first => s1_snapshot_plain($spans[0][2])];
            if (count($spans) > 1) {
                $rest = array_map(static fn(array $span): string => s1_snapshot_plain($span[2]), array_slice($spans, 1));
                $item['parts'][$first === 'b' ? 'i' : 'b'] = trim(implode(' ', array_filter($rest, 'strlen')));
            }
            $items[$class] = $item;
            continue;
        }
        if ($kind === 'button') {
            $link_end = strpos($body, '</a>', $after);
            if ($link_end === false || !preg_match('/class="button-atom__text">([\s\S]*?)<\/span>/', $body, $t, PREG_OFFSET_CAPTURE, $after) || $t[0][1] > $link_end) continue;
            $inner = $t[1][0];
        } elseif ($kind === 'heading') {
            $close = strpos($body, '</' . $tag . '>', $after);
            if ($close === false) continue;
            $inner = substr($body, $after, $close - $after);
        } else {
            $close = strpos($body, '</div>', $after);
            if ($close === false) continue;
            $inner = substr($body, $after, $close - $after);
            if (stripos($inner, '<div') !== false) continue;
            if ($kind === 'badge') {
                if (!preg_match('/<span[^>]*>([\s\S]*?)<\/span>/', $inner, $t)) continue;
                $inner = $t[1];
            }
        }
        // Keep a styled <span> that wraps each paragraph (one text on Om oss sets its font weight that way).
        if ($kind === 'rich-text' && preg_match('/^\s*<p[^>]*>\s*(<span style="[^"]*">)/', $inner, $wrap)) $item['wrap'] = $wrap[1];
        $item['text'] = s1_snapshot_plain($inner);
        if ($item['text'] === '') continue;
        $items[$class] = $item;
    }
    return $cache[$file] = array_values($items);
}

/**
 * The page's parts (top-level sections) with their texts and images, for the tabs in wp-admin.
 * Each: label, items (texts and images in page order).
 */
function s1_snapshot_parts(array $entry): array {
    $body = s1_snapshot_body($entry)['body'];
    $items = array_merge(
        array_map(static fn(array $i): array => $i + ['field' => 'text'], s1_snapshot_texts($entry)),
        array_map(static fn(array $i): array => $i + ['field' => 'image'], s1_snapshot_images($entry))
    );
    usort($items, static fn(array $a, array $b): int => $a['pos'] <=> $b['pos']);

    // Top-level sections: a <section> that opens when no other section is open.
    preg_match_all('~<(/?)section\b~', $body, $tags, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
    $starts = [];
    $depth = 0;
    foreach ($tags as $t) {
        if ($t[1][0] === '/') { $depth = max(0, $depth - 1); continue; }
        if ($depth === 0) $starts[] = $t[0][1];
        $depth++;
    }
    if (!$starts) $starts = [0];

    $parts = [];
    foreach ($items as $item) {
        $index = 0;
        foreach ($starts as $n => $s) if ($s <= $item['pos']) $index = $n;
        $parts[$index]['items'][] = $item;
    }
    ksort($parts);
    $out = [];
    foreach ($parts as $index => $part) {
        $label = '';
        foreach ($part['items'] as $item) {
            if ($item['field'] !== 'text' || !in_array($item['kind'], ['dual', 'heading'], true)) continue;
            $label = $item['kind'] === 'dual' ? trim(implode(' ', $item['parts'])) : $item['text'];
            break;
        }
        if ($label === '') {
            foreach ($part['items'] as $item) if ($item['field'] === 'text' && isset($item['text'])) { $label = $item['text']; break; }
        }
        if ($index === 0) $label = 'Toppen';
        if ($label === '') $label = 'Del ' . (count($out) + 1);
        $label = trim((string) preg_replace('/\s+/u', ' ', $label));
        if (function_exists('mb_strimwidth')) $label = mb_strimwidth($label, 0, 32, '…');
        $out[] = ['label' => (count($out) + 1) . '. ' . $label, 'items' => $part['items']];
    }
    return $out;
}

/* ------------------------------------------------------------- rendering */

function s1_page_text_html(string $value, string $kind, string $wrap = ''): string {
    $value = trim(str_replace("\r\n", "\n", $value));
    if (in_array($kind, ['text', 'rich-text'], true)) {
        $paragraphs = preg_split('/\n\s*\n/', $value);
        $paragraphs = array_map(static fn(string $p): string => implode('<br>', array_map('esc_html', explode("\n", trim($p)))), $paragraphs);
        if ($kind !== 'rich-text') return implode('<br><br>', $paragraphs);
        $close = $wrap !== '' ? '</span>' : '';
        return '<p>' . $wrap . implode($close . '</p><p>' . $wrap, $paragraphs) . $close . '</p>';
    }
    return esc_html((string) preg_replace('/\s+/u', ' ', $value));
}

/** Put the texts written in wp-admin into the page. Elements whose field is empty keep today's text. */
function s1_apply_page_texts(string $html, int $post_id, array $entry): string {
    $meta = get_post_meta($post_id);
    $values = [];
    foreach ($meta as $key => $value) {
        if (strpos($key, 's1_txt_') === 0 && isset($value[0]) && trim((string) $value[0]) !== '') $values[$key] = (string) $value[0];
    }
    if (!$values) return $html;
    foreach (s1_snapshot_texts($entry) as $item) {
        $class = preg_quote($item['class'], '/');
        if ($item['kind'] === 'dual') {
            // Field 1 goes into the first span, field 2 into the second; further spans are emptied when field 2 is changed.
            $suffixes = array_keys($item['parts']);
            $new = [];
            foreach ($suffixes as $n => $suffix) {
                if (isset($values[$item['key'] . '_' . $suffix])) $new[$n] = s1_page_text_html($values[$item['key'] . '_' . $suffix], 'dual');
            }
            if (!$new) continue;
            $html = (string) preg_replace_callback('/(<(h\d) class="' . $class . '[ "][^>]*>)([\s\S]*?)(<\/\2>)/', static function (array $h) use ($new): string {
                $index = 0;
                $inner = (string) preg_replace_callback('/(<span[^>]*dual-heading--[^>]*>)[\s\S]*?(<\/span>)/', static function (array $s) use ($new, &$index): string {
                    $n = $index++;
                    if ($n === 0) return isset($new[0]) ? $s[1] . $new[0] . $s[2] : $s[0];
                    if (!isset($new[1])) return $s[0];
                    return $s[1] . ($n === 1 ? $new[1] : '') . $s[2];
                }, $h[3]);
                return $h[1] . $inner . $h[4];
            }, $html);
            continue;
        }
        if (!isset($values[$item['key']])) continue;
        $new = s1_page_text_html($values[$item['key']], $item['kind'], $item['wrap'] ?? '');
        switch ($item['kind']) {
            case 'button':
                $pattern = '/(class="' . $class . '[ "][^>]*>(?:(?!<\/a>)[\s\S])*?class="button-atom__text">)[\s\S]*?(<\/span>)/';
                break;
            case 'badge':
                $pattern = '/(<div class="' . $class . '[ "][^>]*>\s*<span[^>]*>)[\s\S]*?(<\/span>)/';
                break;
            case 'heading':
                $pattern = '/(<' . $item['tag'] . ' class="' . $class . '[ "][^>]*>)[\s\S]*?(<\/' . $item['tag'] . '>)/';
                break;
            default:
                $pattern = '/(<div class="' . $class . '[ "][^>]*>)[^<]*(?:<(?!\/?div\b)[^<]*)*(<\/div>)/';
        }
        $html = (string) preg_replace_callback($pattern, static fn(array $m): string => $m[1] . $new . $m[2], $html);
    }
    return $html;
}

/** Swap the images chosen in wp-admin into the page: <img> sources, and background images through a style block. */
function s1_apply_page_images(string $html, int $post_id, array $entry): string {
    $css = '';
    foreach (s1_snapshot_images($entry) as $spot) {
        $image_id = (int) get_post_meta($post_id, $spot['key'], true);
        $url = $image_id > 0 ? wp_get_attachment_image_url($image_id, 'full') : false;
        if (!$url) {
            // The theme's own top image: darken only the strip under the menu, the rest looks as before.
            if ($spot['key'] === 's1_hero_image' && $spot['type'] === 'bg' && $spot['url'] !== '') {
                $css .= '.breakdance .' . $spot['class'] . '.bde-section{background-image:linear-gradient(180deg,rgba(0,0,0,.8) 0%,rgba(0,0,0,.45) 18%,rgba(0,0,0,0) 40%),url("' . esc_url($spot['url']) . '")!important}';
            }
            continue;
        }
        if ($spot['type'] === 'bg') {
            // The theme's own background images are already darkened so the white menu and texts stand out.
            // A chosen photo gets the same effect from a dark gradient on top: strongest under the menu.
            $shade = $spot['key'] === 's1_hero_image'
                ? 'linear-gradient(180deg,rgba(0,0,0,.92) 0%,rgba(0,0,0,.75) 18%,rgba(0,0,0,.55) 40%,rgba(0,0,0,.5) 70%,rgba(0,0,0,.75) 100%)'
                : 'linear-gradient(rgba(0,0,0,.45),rgba(0,0,0,.45))';
            $base = (string) preg_replace('/-\d+-\d+$/', '', $spot['class']);
            $css .= '.breakdance .' . $spot['class'] . '.' . $base . '{background-image:' . $shade . ',url("' . esc_url($url) . '")!important}';
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

/* --------------------------------------------------------------- wp-admin */

/* The pages' layout lives in the theme, so the block editor has nothing to edit there: use the classic screen with just the title and the fields. */
add_filter('use_block_editor_for_post', function (bool $use, WP_Post $post): bool {
    return $post->post_type === 'page' && s1_is_snapshot_page((int) $post->ID) ? false : $use;
}, 10, 2);

function s1_page_content_admin_post_id(): int {
    if (isset($_GET['post'])) return absint($_GET['post']);
    if (isset($_POST['post_ID'])) return absint($_POST['post_ID']);
    return 0;
}

function s1_page_field_label(string $label, string $only): string {
    return $only === 'mobil' ? $label . ' (syns bara på mobilen)' : ($only === 'dator' ? $label . ' (syns bara på datorn)' : $label);
}

/** The fields for one snapshot page: a tab per part of the page, its texts and images in page order. */
function s1_page_content_fields(array $entry): array {
    $prefix = 'field_s1pc_' . substr(md5($entry['locale'] . '-' . $entry['slug']), 0, 8) . '_';
    $parts = s1_snapshot_parts($entry);
    $fields = [[
        'key' => $prefix . 'help', 'label' => '', 'type' => 'message',
        'message' => $parts
            ? 'Här ändrar du texter och bilder på sidan. Flikarna följer sidan uppifrån och ner. Textfälten visar det som står på sidan i dag: ändra direkt i dem. Tömmer du ett textfält kommer dagens text tillbaka. Ett tomt bildfält behåller bilden som syns i dag. Utseendet ligger i temat och ändras inte här.'
            : 'Den här sidans innehåll ligger i temat. Här finns inga texter eller bilder att ändra.',
    ]];
    foreach ($parts as $n => $part) {
        $fields[] = ['key' => $prefix . 'tab_' . $n, 'label' => $part['label'], 'type' => 'tab', 'placement' => 'top'];
        foreach ($part['items'] as $item) {
            if ($item['field'] === 'image') {
                $fields[] = [
                    'key' => $prefix . $item['key'], 'name' => $item['key'], 'label' => s1_page_field_label($item['label'], $item['only'] ?? ''), 'type' => 'image',
                    'instructions' => 'I dag: <a href="' . esc_url($item['url']) . '" target="_blank" rel="noopener">'
                        . '<img src="' . esc_url($item['url']) . '" alt="" style="display:block;max-width:220px;max-height:120px;margin:6px 0;border-radius:6px;background:#222"></a>'
                        . 'Lämna tomt för att behålla den här bilden.',
                    'return_format' => 'id', 'preview_size' => 'medium', 'library' => 'all', 'wpml_cf_preferences' => 1,
                ];
                continue;
            }
            if ($item['kind'] === 'dual') {
                $position = 0;
                foreach ($item['parts'] as $part_key => $text) {
                    $fields[] = [
                        'key' => $prefix . $item['key'] . '_' . $part_key, 'name' => $item['key'] . '_' . $part_key,
                        'label' => s1_page_field_label(count($item['parts']) > 1 ? ($position++ === 0 ? 'Rubrik, fet del' : 'Rubrik, kursiv del') : 'Rubrik', $item['only'] ?? ''),
                        'type' => 'text', 'default_value' => $text, 's1_original' => $text,
                        'wrapper' => ['width' => count($item['parts']) > 1 ? '50' : '', 'data-s1-original' => $text], 'wpml_cf_preferences' => 2,
                    ];
                }
                continue;
            }
            $labels = ['text' => 'Text', 'rich-text' => 'Text', 'heading' => 'Rubrik', 'badge' => 'Liten etikett', 'button' => 'Knapptext'];
            $long = in_array($item['kind'], ['text', 'rich-text'], true);
            $fields[] = [
                'key' => $prefix . $item['key'], 'name' => $item['key'], 'label' => s1_page_field_label($labels[$item['kind']] ?? 'Text', $item['only'] ?? ''),
                'wrapper' => ['data-s1-original' => $item['text']],
                'type' => $long ? 'textarea' : 'text',
                'rows' => $long ? max(2, min(10, (int) ceil(mb_strlen($item['text']) / 85) + substr_count($item['text'], "\n"))) : null,
                'new_lines' => '',
                'instructions' => $long && strpos($item['text'], "\n") !== false ? 'Ny rad ger radbrytning, tom rad ger nytt stycke.' : '',
                'default_value' => $item['text'], 's1_original' => $item['text'], 'wpml_cf_preferences' => 2,
            ];
        }
    }
    return $fields;
}

/* Register the fields only for the page being edited: each page has its own set. */
add_action('acf/init', function (): void {
    if (!is_admin() || !function_exists('acf_add_local_field_group')) return;
    $post_id = s1_page_content_admin_post_id();
    $entry = s1_snapshot_entry_for_post($post_id);
    if (!$entry) return;
    acf_add_local_field_group([
        'key' => 'group_s1_page_hero',
        'title' => 'Innehåll på sidan',
        'acfml_field_group_mode' => 'advanced',
        'fields' => s1_page_content_fields($entry),
        'location' => [[['param' => 'page', 'operator' => '==', 'value' => (string) $post_id]]],
        'menu_order' => 0,
        'position' => 'acf_after_title',
        'style' => 'default',
        'label_placement' => 'top',
        'hide_on_screen' => ['the_content', 'featured_image', 'excerpt', 'discussion', 'comments', 'author', 'format', 'categories', 'tags', 'send-trackbacks'],
        'active' => true,
    ]);
});

function s1_page_text_normal(string $text): string {
    return trim((string) preg_replace(['/\r\n?/', '/[ \t]+/', '/ *\n */'], ["\n", ' ', "\n"], $text));
}

/* A text field always shows the text on the page: the saved text, or today's text when nothing is saved. */
add_filter('acf/load_value', function ($value, $post_id, $field) {
    if (!is_array($field) || !isset($field['s1_original'])) return $value;
    return ($value === null || $value === false || trim((string) $value) === '') ? $field['s1_original'] : $value;
}, 10, 3);

/* Store only real changes: a text that equals today's text is saved as empty, so later fixes in the theme still show. */
add_filter('acf/update_value', function ($value, $post_id, $field) {
    if (!is_array($field) || !isset($field['s1_original']) || !is_string($value)) return $value;
    return s1_page_text_normal($value) === s1_page_text_normal((string) $field['s1_original']) ? '' : $value;
}, 10, 3);

/* In the form: mark changed texts with "Ändrad" and a link back to today's text, and mark tabs that hold changes. */
add_action('acf/input/admin_footer', function (): void {
    $post_id = s1_page_content_admin_post_id();
    if (!$post_id || !s1_is_snapshot_page($post_id)) return;
    ?>
    <style>
    #acf-group_s1_page_hero .s1-pill{display:none;margin-left:8px;padding:1px 8px;border-radius:10px;background:#dbe9f6;color:#135e96;font-size:11px;font-weight:600;vertical-align:1px}
    #acf-group_s1_page_hero .s1-restore{display:none;margin-left:8px;font-size:12px}
    #acf-group_s1_page_hero .s1-changed .s1-pill{display:inline-block}
    #acf-group_s1_page_hero .s1-changed .s1-restore{display:inline}
    #acf-group_s1_page_hero .s1-changed input[type=text],#acf-group_s1_page_hero .s1-changed textarea{border-color:#72aee6;box-shadow:0 0 0 1px #72aee6}
    #acf-group_s1_page_hero .acf-tab-button.s1-tab-changed::after{content:"";display:inline-block;width:7px;height:7px;margin-left:6px;border-radius:50%;background:#2271b1;vertical-align:1px}
    </style>
    <script>
    (function () {
        const box = document.getElementById('acf-group_s1_page_hero');
        if (!box) return;
        const norm = s => (s || '').replace(/\r\n?/g, '\n').replace(/[ \t]+/g, ' ').replace(/ *\n */g, '\n').trim();
        const input = field => field.querySelector('input[type=text], textarea');
        const changed = field => {
            if (field.dataset.s1Original !== undefined) { const v = norm(input(field)?.value); return v !== '' && v !== norm(field.dataset.s1Original); }
            return !!field.querySelector('.acf-image-uploader.has-value');
        };
        const markTabs = () => {
            let tabKey = null; const changedTabs = new Set();
            box.querySelectorAll('.acf-fields > .acf-field').forEach(field => {
                if (field.classList.contains('acf-field-tab')) { tabKey = field.dataset.key; return; }
                if (tabKey && changed(field)) changedTabs.add(tabKey);
            });
            box.querySelectorAll('.acf-tab-button').forEach(button => button.classList.toggle('s1-tab-changed', changedTabs.has(button.dataset.key)));
        };
        const update = field => { if (field.dataset.s1Original !== undefined) field.classList.toggle('s1-changed', changed(field)); markTabs(); };
        box.querySelectorAll('.acf-field[data-s1-original]').forEach(field => {
            const label = field.querySelector('.acf-label label');
            if (!label || !input(field)) return;
            label.insertAdjacentHTML('beforeend', '<span class="s1-pill">Ändrad</span><button type="button" class="button-link s1-restore">Återställ dagens text</button>');
            field.querySelector('.s1-restore').addEventListener('click', () => { input(field).value = field.dataset.s1Original; update(field); });
            input(field).addEventListener('input', () => update(field));
            update(field);
        });
        if (window.acf) acf.addAction('change', markTabs);
        box.addEventListener('click', () => setTimeout(markTabs, 50));
        markTabs();
    })();
    </script>
    <?php
});
