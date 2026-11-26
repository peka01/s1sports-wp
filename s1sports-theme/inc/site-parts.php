<?php
/**
 * "Meny och sidfot": the menu in the header and the footer, editable in wp-admin per language.
 *
 * The header and footer come from the snapshots like the rest of the pages. Their content is read
 * from the start page snapshot of each language (that is the default), and what is saved on the
 * admin screen (option s1_site_parts_sv / s1_site_parts_en) replaces it on every page, including
 * the Development pages. The design stays in the theme: menu items, footer links and texts are
 * written into the existing Breakdance markup.
 */
defined('ABSPATH') || exit;

const S1_SOCIAL = ['facebook' => 'Facebook', 'instagram' => 'Instagram', 'youtube' => 'YouTube', 'linkedin' => 'LinkedIn', 'tiktok' => 'TikTok'];

function s1_site_parts_text(string $html): string {
    return trim((string) preg_replace('/\s+/u', ' ', html_entity_decode(wp_strip_all_tags($html), ENT_QUOTES, 'UTF-8')));
}

/** Where the footer starts in a page's HTML (the shared Breakdance block 108). */
function s1_site_parts_footer_start(string $html): ?int {
    return preg_match('/<section class="bde-section-108-\d+ bde-section">/', $html, $m, PREG_OFFSET_CAPTURE) ? (int) $m[0][1] : null;
}

/** Today's menu and footer for a language, read from its start page snapshot. */
function s1_site_parts_defaults(string $lang): array {
    static $cache = [];
    $lang = $lang === 'en' ? 'en' : 'sv';
    if (isset($cache[$lang])) return $cache[$lang];
    $parts = ['menu' => [], 'button' => ['label' => '', 'url' => ''], 'cta' => ['first' => '', 'second' => '', 'label' => '', 'url' => ''],
        'cta_hide' => [$lang === 'en' ? 'book-a-meeting' : 'boka-mote'],
        'columns' => [], 'social' => array_fill_keys(array_keys(S1_SOCIAL), ''), 'copyright' => ''];
    $file = get_template_directory() . '/snapshots/' . $lang . '-home.html';
    $html = is_readable($file) ? (string) file_get_contents($file) : '';

    // Menu: the items of the Breakdance menu, with the service pages as a submenu under "Våra tjänster".
    if (preg_match('~<ul class="breakdance-menu-list"[^>]*>([\s\S]*?)</ul>~', $html, $list)) {
        preg_match_all('~<li class="([^"]*)">\s*<a\b[^>]*href="([^"]*)"[^>]*>([\s\S]*?)</a>\s*</li>~', $list[1], $items, PREG_SET_ORDER);
        $services = $lang === 'en'
            ? ['https://s1sports.co/our-services/', [['College recruiting', 'https://s1sports.co/college-recruiting/'], ['Professional guidance', 'https://s1sports.co/pro-consulting/'], ['Junior development', 'https://s1sports.co/junior-development/']]]
            : ['https://s1sports.se/vara-tjanster/', [['Collegerekrytering', 'https://s1sports.se/college-rekrytering/'], ['Professionell rådgivning', 'https://s1sports.se/professionell-radgivning/'], ['Juniorutveckling', 'https://s1sports.se/junior-utveckling/']]];
        foreach ($items as $item) {
            $label = s1_site_parts_text($item[3]);
            $url = html_entity_decode($item[2]);
            if (strpos($item[1], 'bde-button') !== false) {
                $parts['button'] = ['label' => $label, 'url' => $url];
                continue;
            }
            $parts['menu'][] = ['label' => $label, 'url' => $url, 'sub' => false];
            if ($url === $services[0]) {
                foreach ($services[1] as [$sub_label, $sub_url]) $parts['menu'][] = ['label' => $sub_label, 'url' => $sub_url, 'sub' => true];
            }
        }
    }

    $start = s1_site_parts_footer_start($html);
    $footer = $start === null ? '' : substr($html, $start);
    if (preg_match('~<h\d class="bde-dual-heading-108-\d+[^"]*"[^>]*>([\s\S]*?)</h\d>~', $footer, $heading)) {
        preg_match_all('~<span[^>]*dual-heading--[^>]*>([\s\S]*?)</span>~', $heading[1], $spans);
        $parts['cta']['first'] = s1_site_parts_text($spans[1][0] ?? '');
        $parts['cta']['second'] = s1_site_parts_text($spans[1][1] ?? '');
    }
    if (preg_match('~class="bde-button-108-\d+ bde-button">\s*<a\b[^>]*href="([^"]*)"[^>]*>\s*<span class="button-atom__text">([\s\S]*?)</span>~', $footer, $button)) {
        $parts['cta']['label'] = s1_site_parts_text($button[2]);
        $parts['cta']['url'] = html_entity_decode($button[1]);
    }
    preg_match_all('~<div class="bde-text-108-\d+ bde-text">([^<]*)</div>\s*<div class="bde-icon-list-108-\d+ bde-icon-list">\s*<ul>([\s\S]*?)</ul>~', $footer, $columns, PREG_SET_ORDER);
    foreach ($columns as $column) {
        preg_match_all('~<a\b[^>]*href="([^"]*)"[^>]*>[\s\S]*?<span class=\'bde-icon-list__text\'\s*>([\s\S]*?)</span>~', $column[2], $links, PREG_SET_ORDER);
        $parts['columns'][] = [
            'heading' => s1_site_parts_text($column[1]),
            'links' => array_map(static fn(array $l): array => ['label' => s1_site_parts_text($l[2]), 'url' => html_entity_decode($l[1])], $links),
        ];
    }
    if (preg_match_all('~<div class="bde-text-108-\d+ bde-text">([\s\S]*?)</div>~', $footer, $texts)) {
        $parts['copyright'] = s1_site_parts_text((string) end($texts[1]));
    }
    return $cache[$lang] = $parts;
}

/** The menu and footer to show: what is saved in wp-admin, or today's content. */
function s1_site_parts(string $lang): array {
    $lang = $lang === 'en' ? 'en' : 'sv';
    $defaults = s1_site_parts_defaults($lang);
    $saved = get_option('s1_site_parts_' . $lang);
    if (!is_array($saved)) return $defaults;
    $parts = array_replace($defaults, array_intersect_key($saved, $defaults));
    $parts['social'] = array_replace($defaults['social'], array_intersect_key((array) ($saved['social'] ?? []), $defaults['social']));
    return $parts;
}

/* -------------------------------------------------------------- rendering */

function s1_site_parts_is_current(string $url): bool {
    $path = static fn(string $u): string => '/' . trim((string) parse_url($u, PHP_URL_PATH), '/');
    $current = $path((string) ($_SERVER['REQUEST_URI'] ?? '/'));
    return $url !== '' && $url !== '#' && $path($url) === $current;
}

/** Write the menu and footer from wp-admin into a page's HTML. */
function s1_render_site_parts(string $html, string $locale): string {
    $parts = s1_site_parts($locale);
    $html = s1_render_site_menu($html, $parts, $locale);
    $start = s1_site_parts_footer_start($html);
    if ($start === null) return $html;
    return substr($html, 0, $start) . s1_render_site_footer(substr($html, $start), $parts, (string) get_post_field('post_name', get_queried_object_id()));
}

/** The pages of a language that have the footer, for the "Visa inte rutan på" choice: slug => title. */
function s1_site_parts_pages(string $lang): array {
    $slugs = [];
    foreach (s1_snapshot_manifest() as $item) {
        if (($item['locale'] ?? '') !== $lang) continue;
        $slugs[] = ($lang === 'sv' && $item['slug'] === 'home') ? 'start' : (string) $item['slug'];
    }
    $pages = [];
    foreach (get_posts(['post_type' => 'page', 'post_status' => 'any', 'post_name__in' => $slugs, 'numberposts' => -1, 'suppress_filters' => true, 'orderby' => 'title', 'order' => 'ASC']) as $page) {
        $pages[$page->post_name] = $page->post_title;
    }
    foreach ($slugs as $slug) $pages[$slug] ??= $slug;
    return $pages;
}

function s1_render_site_menu(string $html, array $parts, string $locale): string {
    return (string) preg_replace_callback('~(<ul class="breakdance-menu-list"[^>]*>)([\s\S]*?)(</ul>)~', static function (array $m) use ($parts, $locale): string {
        preg_match_all('~<li class="([^"]*)">[\s\S]*?</li>~', $m[2], $items, PREG_SET_ORDER);
        $classes = [];
        $button_li = '';
        foreach ($items as $item) {
            if (strpos($item[1], 'bde-button') !== false) $button_li = $item[0];
            else $classes[] = $item[1];
        }
        if (!$classes) return $m[0];

        // Group the rows: a submenu row belongs to the main row above it.
        $tree = [];
        foreach ($parts['menu'] as $row) {
            if (($row['label'] ?? '') === '') continue;
            if (!empty($row['sub']) && $tree) $tree[count($tree) - 1]['children'][] = $row;
            else $tree[] = $row + ['children' => []];
        }
        $chevron = '<svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false"><path d="M6 9l6 6 6-6"/></svg>';
        $out = '';
        foreach ($tree as $n => $row) {
            $class = $classes[$n] ?? $classes[0];
            $link = '<a class="breakdance-link breakdance-menu-link" href="' . esc_url($row['url']) . '" target="_self" data-type="url"' . (s1_site_parts_is_current($row['url']) ? ' aria-current="page"' : '') . '> ' . esc_html($row['label']) . ' </a>';
            if (!$row['children']) {
                $out .= '<li class="' . esc_attr($class) . '"> ' . $link . ' </li>';
                continue;
            }
            $id = 's1-sub-' . ($n + 1);
            $sub = '';
            foreach ($row['children'] as $child) {
                $sub .= '<li><a class="s1-submenu__link" href="' . esc_url($child['url']) . '"' . (s1_site_parts_is_current($child['url']) ? ' aria-current="page"' : '') . '>' . esc_html($child['label']) . '</a></li>';
            }
            $toggle = ($locale === 'en' ? 'Show the pages under ' : 'Visa sidorna under ') . $row['label'];
            $out .= '<li class="' . esc_attr($class) . ' s1-has-sub"> ' . $link
                . '<button type="button" class="s1-sub-toggle" aria-expanded="false" aria-controls="' . $id . '" aria-label="' . esc_attr($toggle) . '">' . $chevron . '</button>'
                . '<ul class="s1-submenu" id="' . $id . '">' . $sub . '</ul></li>';
        }
        if ($button_li !== '' && ($parts['button']['label'] ?? '') !== '') {
            $button = (string) preg_replace('~href="[^"]*"~', 'href="' . esc_url($parts['button']['url']) . '"', $button_li, 1);
            $out .= (string) preg_replace('~(<span class="button-atom__text">)[\s\S]*?(</span>)~', '${1}' . str_replace(['\\', '$'], ['\\\\', '\\$'], esc_html($parts['button']['label'])) . '${2}', $button, 1);
        }
        return $m[1] . ' ' . $out . ' ' . $m[3];
    }, $html, 1);
}

function s1_render_site_footer(string $footer, array $parts, string $slug = ''): string {
    $put = static fn(string $text): string => str_replace(['\\', '$'], ['\\\\', '\\$'], esc_html($text));

    // The box at the top is left out on the pages chosen in wp-admin (from the start: the contact form page).
    if ($slug !== '' && in_array($slug, (array) ($parts['cta_hide'] ?? []), true)) {
        $footer = (string) preg_replace('~<div class="bde-div-108-\d+ bde-div">\s*<h\d class="bde-dual-heading-108-\d+[^>]*>[\s\S]*?</h\d>\s*<div class="bde-button-108-\d+ bde-button">[\s\S]*?</a>\s*</div>\s*</div>~', '', $footer, 1);
    }

    // The box at the top: heading in two parts and a button.
    $footer = (string) preg_replace_callback('~(<h\d class="bde-dual-heading-108-\d+[^"]*"[^>]*>)([\s\S]*?)(</h\d>)~', static function (array $m) use ($parts): string {
        $n = 0;
        $inner = (string) preg_replace_callback('~(<span[^>]*dual-heading--[^>]*>)[\s\S]*?(</span>)~', static function (array $s) use ($parts, &$n): string {
            $text = $n++ === 0 ? $parts['cta']['first'] : $parts['cta']['second'];
            return $s[1] . ($n === 2 ? ' ' : '') . esc_html($text) . $s[2];
        }, $m[2]);
        return $m[1] . $inner . $m[3];
    }, $footer, 1);
    $footer = (string) preg_replace_callback('~(class="bde-button-108-\d+ bde-button">\s*<a\b[^>]*href=")[^"]*("[^>]*>\s*<span class="button-atom__text">)[\s\S]*?(</span>)~', static fn(array $m): string => $m[1] . esc_url($parts['cta']['url']) . $m[2] . esc_html($parts['cta']['label']) . $m[3], $footer, 1);

    // Social media: the links with an address are shown, the block disappears when none has one.
    $footer = (string) preg_replace_callback('~<div class="bde-div-108-\d+ bde-div">\s*<a class="bde-container-link[\s\S]*?(?=<div class="bde-fancy-divider-108-)~', static function (array $m) use ($parts): string {
        $shown = 0;
        $block = (string) preg_replace_callback('~<a class="bde-container-link[^"]*"[^>]*>([\s\S]*?)</a>~', static function (array $a) use ($parts, &$shown): string {
            $name = strtolower(s1_site_parts_text($a[1]));
            $url = (string) ($parts['social'][$name] ?? '');
            if ($url === '') return '';
            $shown++;
            $open = (string) preg_replace('~href="[^"]*"~', 'href="' . esc_url($url) . '"', substr($a[0], 0, (int) strpos($a[0], '>') + 1), 1);
            $open = str_replace('target="_self"', 'target="_blank" rel="noopener"', $open);
            return $open . $a[1] . '</a>';
        }, $m[0]);
        return $shown ? $block : '';
    }, $footer, 1);

    // The link columns: heading and links, built from the column's first link.
    $column = 0;
    $footer = (string) preg_replace_callback('~(<div class="bde-text-108-\d+ bde-text">)[^<]*(</div>\s*<div class="bde-icon-list-108-\d+ bde-icon-list">\s*<ul>)([\s\S]*?)(</ul>)~', static function (array $m) use ($parts, &$column): string {
        $data = $parts['columns'][$column++] ?? null;
        if (!$data || !preg_match('~<li>[\s\S]*?</li>~', $m[3], $template)) return $m[0];
        $items = '';
        foreach ((array) $data['links'] as $link) {
            if (($link['label'] ?? '') === '') continue;
            $li = (string) preg_replace('~href="[^"]*"~', 'href="' . esc_url($link['url']) . '"', $template[0], 1);
            $items .= (string) preg_replace('~(<span class=\'bde-icon-list__text\'\s*>)[\s\S]*?(</span>)~', '${1} ' . str_replace(['\\', '$'], ['\\\\', '\\$'], esc_html($link['label'])) . ' ${2}', $li, 1) . ' ';
        }
        return $m[1] . ' ' . esc_html($data['heading']) . ' ' . $m[2] . ' ' . $items . $m[4];
    }, $footer);

    // The line at the very bottom.
    if (preg_match_all('~<div class="bde-text-108-\d+ bde-text">[\s\S]*?</div>~', $footer, $texts, PREG_OFFSET_CAPTURE)) {
        $last = end($texts[0]);
        $new = (string) preg_replace('~(<div[^>]*>)[\s\S]*?(</div>)~', '${1} ' . $put($parts['copyright']) . ' ${2}', $last[0], 1);
        $footer = substr_replace($footer, $new, (int) $last[1], strlen($last[0]));
    }
    return $footer;
}

/* --------------------------------------------------------------- wp-admin */

add_action('admin_menu', function (): void {
    add_menu_page('Meny och sidfot', 'Meny och sidfot', 'edit_pages', 's1-site-parts', 's1_site_parts_screen', 'dashicons-menu-alt3', 21);
});

function s1_site_parts_screen(): void {
    if (!current_user_can('edit_pages')) return;
    $lang = (isset($_GET['s1_lang']) && $_GET['s1_lang'] === 'en') ? 'en' : 'sv';
    $parts = s1_site_parts($lang);
    $saved = is_array(get_option('s1_site_parts_' . $lang));
    $base = admin_url('admin.php?page=s1-site-parts');
    $site = $lang === 'en' ? 'https://s1sports.co/' : 'https://s1sports.se/';
    $row = static function (string $prefix, array $link, bool $levels): string {
        return '<tr class="s1sp-row">'
            . '<td><input type="text" class="regular-text" data-name="label" value="' . esc_attr($link['label'] ?? '') . '" placeholder="Text"></td>'
            . '<td><input type="text" class="regular-text code" data-name="url" value="' . esc_attr($link['url'] ?? '') . '" placeholder="https://… eller /sida/"></td>'
            . ($levels ? '<td><select data-name="sub"><option value="0">Huvudmeny</option><option value="1"' . (!empty($link['sub']) ? ' selected' : '') . '>Undermeny (under raden ovanför)</option></select></td>' : '')
            . '<td class="s1sp-tools"><button type="button" class="button s1sp-up" aria-label="Flytta upp">↑</button> <button type="button" class="button s1sp-down" aria-label="Flytta ner">↓</button> <button type="button" class="button s1sp-remove" aria-label="Ta bort">✕</button></td>'
            . '</tr>';
    };
    $table = static function (string $prefix, array $links, bool $levels, string $add) use ($row): string {
        $rows = '';
        foreach ($links as $link) $rows .= $row($prefix, $link, $levels);
        return '<table class="widefat s1sp-table" data-prefix="' . esc_attr($prefix) . '"><thead><tr><th>Text</th><th>Länk</th>' . ($levels ? '<th>Nivå</th>' : '') . '<th></th></tr></thead>'
            . '<tbody>' . $rows . '</tbody></table>'
            . '<template>' . $row($prefix, ['label' => '', 'url' => ''], $levels) . '</template>'
            . '<p><button type="button" class="button s1sp-add">' . esc_html($add) . '</button></p>';
    };
    ?>
    <div class="wrap s1sp">
        <h1>Meny och sidfot</h1>
        <?php if (isset($_GET['s1_saved'])) echo '<div class="notice notice-success is-dismissible"><p>' . ($_GET['s1_saved'] === 'reset' ? 'Menyn och sidfoten visar ursprunget igen.' : 'Sparat. Ändringen syns direkt på alla sidor.') . '</p></div>'; ?>
        <p>Här ändrar du menyn högst upp och sidfoten längst ner. De är samma på alla sidor. Svenska och engelska ändras var för sig. Utseendet ligger i temat och ändras inte här.</p>
        <nav class="nav-tab-wrapper">
            <a class="nav-tab<?php echo $lang === 'sv' ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url($base); ?>">Svenska (s1sports.se)</a>
            <a class="nav-tab<?php echo $lang === 'en' ? ' nav-tab-active' : ''; ?>" href="<?php echo esc_url($base . '&s1_lang=en'); ?>">English (s1sports.co)</a>
        </nav>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" id="s1sp-form">
            <input type="hidden" name="action" value="s1_save_site_parts">
            <input type="hidden" name="s1_lang" value="<?php echo esc_attr($lang); ?>">
            <?php wp_nonce_field('s1_site_parts_' . $lang); ?>

            <h2>Menyn</h2>
            <p class="description">Raderna visas i den här ordningen. En rad med nivån <strong>Undermeny</strong> hamnar i rullgardinen under huvudraden ovanför, som Collegerekrytering under Våra tjänster.</p>
            <?php echo $table('menu', $parts['menu'], true, 'Lägg till menyval'); ?>
            <table class="form-table" role="presentation">
                <tr><th scope="row">Knappen i menyn</th><td>
                    <p><label>Knapptext<br><input type="text" class="regular-text" name="button[label]" value="<?php echo esc_attr($parts['button']['label']); ?>"></label></p>
                    <p><label>Knappens länk<br><input type="text" class="regular-text code" name="button[url]" value="<?php echo esc_attr($parts['button']['url']); ?>"></label></p>
                </td></tr>
            </table>

            <h2>Sidfoten</h2>
            <table class="form-table" role="presentation">
                <tr><th scope="row">Rutan överst</th><td>
                    <p><label>Rubrik, fet del<br><input type="text" class="large-text" name="cta[first]" value="<?php echo esc_attr($parts['cta']['first']); ?>"></label></p>
                    <p><label>Rubrik, kursiv del<br><input type="text" class="large-text" name="cta[second]" value="<?php echo esc_attr($parts['cta']['second']); ?>"></label></p>
                    <p><label>Knapptext<br><input type="text" class="regular-text" name="cta[label]" value="<?php echo esc_attr($parts['cta']['label']); ?>"></label></p>
                    <p><label>Knappens länk<br><input type="text" class="regular-text code" name="cta[url]" value="<?php echo esc_attr($parts['cta']['url']); ?>"></label></p>
                    <fieldset><legend>Visa inte rutan på de här sidorna</legend>
                    <input type="hidden" name="cta_hide[]" value="">
                    <?php foreach (s1_site_parts_pages($lang) as $page_slug => $title): ?>
                        <label style="display:inline-block;margin:4px 16px 0 0"><input type="checkbox" name="cta_hide[]" value="<?php echo esc_attr($page_slug); ?>"<?php checked(in_array($page_slug, (array) $parts['cta_hide'], true)); ?>> <?php echo esc_html($title); ?></label>
                    <?php endforeach; ?>
                    </fieldset>
                </td></tr>
                <?php foreach ($parts['columns'] as $n => $column): ?>
                <tr><th scope="row">Kolumn <?php echo (int) $n + 1; ?></th><td>
                    <p><label>Rubrik<br><input type="text" class="regular-text" name="columns[<?php echo (int) $n; ?>][heading]" value="<?php echo esc_attr($column['heading']); ?>"></label></p>
                    <?php echo $table('columns[' . (int) $n . '][links]', $column['links'], false, 'Lägg till länk'); ?>
                </td></tr>
                <?php endforeach; ?>
                <tr><th scope="row">Sociala medier</th><td>
                    <p class="description">Skriv hela adressen till profilen, t.ex. https://www.instagram.com/s1sports. Ett tomt fält visas inte på sajten.</p>
                    <?php foreach (S1_SOCIAL as $key => $name): ?>
                        <p><label><?php echo esc_html($name); ?><br><input type="url" class="regular-text code" name="social[<?php echo esc_attr($key); ?>]" value="<?php echo esc_attr($parts['social'][$key]); ?>" placeholder="https://"></label></p>
                    <?php endforeach; ?>
                </td></tr>
                <tr><th scope="row">Raden längst ner</th><td><input type="text" class="large-text" name="copyright" value="<?php echo esc_attr($parts['copyright']); ?>"></td></tr>
            </table>
            <p class="submit">
                <button type="submit" class="button button-primary">Spara</button>
                <a class="button" href="<?php echo esc_url($site); ?>" target="_blank" rel="noopener">Visa sajten</a>
            </p>
        </form>
        <?php if ($saved): ?>
        <form method="post" action="<?php echo esc_url(admin_url('admin-post.php')); ?>" onsubmit="return confirm('Visa menyn och sidfoten som de var från början? Dina ändringar för det här språket tas bort.');">
            <input type="hidden" name="action" value="s1_save_site_parts">
            <input type="hidden" name="s1_lang" value="<?php echo esc_attr($lang); ?>">
            <input type="hidden" name="s1_reset" value="1">
            <?php wp_nonce_field('s1_site_parts_' . $lang); ?>
            <p><button type="submit" class="button-link-delete">Återställ till ursprunget</button></p>
        </form>
        <?php endif; ?>
    </div>
    <style>.s1sp .s1sp-table{max-width:960px;margin-top:8px}.s1sp .s1sp-table td{vertical-align:middle}.s1sp .s1sp-table input{width:100%}.s1sp .s1sp-tools{white-space:nowrap;width:1%}.s1sp .s1sp-row.is-sub td:first-child{padding-left:32px}</style>
    <script>
    (() => {
        const form = document.getElementById('s1sp-form');
        const mark = row => { const sub = row.querySelector('select[data-name="sub"]'); row.classList.toggle('is-sub', !!sub && sub.value === '1'); };
        form.querySelectorAll('.s1sp-row').forEach(mark);
        form.addEventListener('change', e => { const row = e.target.closest('.s1sp-row'); if (row) mark(row); });
        form.addEventListener('click', e => {
            const button = e.target.closest('button'); if (!button) return;
            const row = button.closest('.s1sp-row');
            if (button.classList.contains('s1sp-add')) {
                const table = button.closest('p').previousElementSibling.previousElementSibling;
                const tpl = button.closest('p').previousElementSibling;
                table.querySelector('tbody').appendChild(tpl.content.firstElementChild.cloneNode(true));
                table.querySelector('tbody tr:last-child input')?.focus();
            } else if (row && button.classList.contains('s1sp-up') && row.previousElementSibling) row.parentNode.insertBefore(row, row.previousElementSibling);
            else if (row && button.classList.contains('s1sp-down') && row.nextElementSibling) row.parentNode.insertBefore(row.nextElementSibling, row);
            else if (row && button.classList.contains('s1sp-remove')) row.remove();
        });
        form.addEventListener('submit', () => {
            form.querySelectorAll('.s1sp-table').forEach(table => {
                const prefix = table.dataset.prefix;
                table.querySelectorAll('tbody .s1sp-row').forEach((row, i) => row.querySelectorAll('[data-name]').forEach(field => { field.name = prefix + '[' + i + '][' + field.dataset.name + ']'; }));
            });
        });
    })();
    </script>
    <?php
}

add_action('admin_post_s1_save_site_parts', function (): void {
    $lang = (isset($_POST['s1_lang']) && $_POST['s1_lang'] === 'en') ? 'en' : 'sv';
    if (!current_user_can('edit_pages') || !check_admin_referer('s1_site_parts_' . $lang)) wp_die('Du har inte behörighet att ändra menyn och sidfoten.');
    $back = admin_url('admin.php?page=s1-site-parts' . ($lang === 'en' ? '&s1_lang=en' : ''));
    if (!empty($_POST['s1_reset'])) {
        delete_option('s1_site_parts_' . $lang);
        wp_safe_redirect($back . '&s1_saved=reset');
        exit;
    }
    $post = wp_unslash($_POST);
    $text = static fn($v): string => sanitize_text_field((string) $v);
    $url = static fn($v): string => esc_url_raw(trim((string) $v));
    $links = static function ($rows, bool $levels) use ($text, $url): array {
        $out = [];
        foreach ((array) $rows as $row) {
            $label = $text($row['label'] ?? '');
            if ($label === '') continue;
            $link = ['label' => $label, 'url' => $url($row['url'] ?? '')];
            if ($levels) $link['sub'] = !empty($row['sub']);
            $out[] = $link;
        }
        return $out;
    };
    $parts = [
        'menu' => $links($post['menu'] ?? [], true),
        'button' => ['label' => $text($post['button']['label'] ?? ''), 'url' => $url($post['button']['url'] ?? '')],
        'cta' => [
            'first' => $text($post['cta']['first'] ?? ''), 'second' => $text($post['cta']['second'] ?? ''),
            'label' => $text($post['cta']['label'] ?? ''), 'url' => $url($post['cta']['url'] ?? ''),
        ],
        'cta_hide' => array_values(array_filter(array_map('sanitize_title', (array) ($post['cta_hide'] ?? [])))),
        'columns' => [],
        'social' => [],
        'copyright' => $text($post['copyright'] ?? ''),
    ];
    foreach ((array) ($post['columns'] ?? []) as $column) {
        $parts['columns'][] = ['heading' => $text($column['heading'] ?? ''), 'links' => $links($column['links'] ?? [], false)];
    }
    foreach (array_keys(S1_SOCIAL) as $key) $parts['social'][$key] = $url($post['social'][$key] ?? '');
    update_option('s1_site_parts_' . $lang, $parts, false);
    wp_safe_redirect($back . '&s1_saved=1');
    exit;
});
