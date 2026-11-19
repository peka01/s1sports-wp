<?php
/**
 * "Instruktioner": the editor's guide as a page in wp-admin, for everyone who can edit pages.
 * The text is guide/guide.html in the theme and the screenshots are in guide/img/, so the guide
 * is updated together with the forms it describes. Link: /wp-admin/admin.php?page=s1-guide
 */
defined('ABSPATH') || exit;

add_action('admin_menu', function (): void {
    add_menu_page('Instruktioner', 'Instruktioner', 'edit_pages', 's1-guide', 's1_guide_screen', 'dashicons-book-alt', 2);
});

function s1_guide_screen(): void {
    if (!current_user_can('edit_pages')) return;
    $file = get_template_directory() . '/guide/guide.html';
    $html = is_readable($file) ? (string) file_get_contents($file) : '<p>Instruktionerna saknas i temat.</p>';
    $html = str_replace('{{IMG}}', esc_url(get_template_directory_uri() . '/guide/img/'), $html);
    $version = (string) wp_get_theme(get_template())->get('Version');
    ?>
    <div class="wrap s1g">
        <h1>Så redigerar du sajten</h1>
        <?php echo wp_kses_post($html); ?>
        <p class="s1g-foot">Instruktionerna följer med temat (version <?php echo esc_html($version); ?>) och uppdateras när formulären ändras.</p>
    </div>
    <style>
    .s1g{max-width:860px;font-size:14px;line-height:1.6}
    .s1g h1{margin-bottom:8px}
    .s1g h2{margin:40px 0 10px;padding-top:16px;border-top:1px solid #dcdcde;font-size:20px}
    .s1g h3{margin:24px 0 8px;font-size:16px}
    .s1g p,.s1g li{font-size:14px;max-width:760px}
    .s1g .s1g-lead{font-size:16px;margin:0 0 16px}
    .s1g ol,.s1g ul{margin-left:1.6em}
    .s1g ul{list-style:disc}
    .s1g ol ul{list-style:circle;margin-top:6px}
    .s1g li{margin-bottom:6px}
    .s1g figure{margin:16px 0 20px}
    .s1g figure img{display:block;max-width:100%;height:auto;border:1px solid #c3c4c7;border-radius:6px;box-shadow:0 1px 3px rgba(0,0,0,.08)}
    .s1g table.widefat{margin:12px 0 16px;max-width:860px}
    .s1g table.widefat td,.s1g table.widefat th{font-size:13px;vertical-align:top}
    .s1g .s1g-toc{display:inline-block;margin:4px 0 8px;padding:12px 20px 4px 16px;background:#fff;border:1px solid #dcdcde;border-radius:6px}
    .s1g .s1g-toc ol{margin:6px 0 8px 1.4em}
    .s1g .s1g-toc li{margin-bottom:2px}
    .s1g .s1g-foot{margin-top:40px;color:#646970;font-size:12px}
    .s1g h2[id]{scroll-margin-top:40px}
    </style>
    <?php
}
