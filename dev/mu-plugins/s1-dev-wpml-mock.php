<?php
/**
 * Local Playground only: a stand-in for the parts of WPML the theme uses, so the English
 * rendering can be tested without the (commercial) plugin. Add ?lang=en to a URL to view
 * it in English. It does not filter content by language and never runs on the live site.
 */
if (!defined('ABSPATH')) exit;
if (!in_array((string) parse_url((string) get_option('home'), PHP_URL_HOST), ['127.0.0.1', 'localhost'], true)) return;

function s1dev_lang(): string {
    return (isset($_GET['lang']) && $_GET['lang'] === 'en') ? 'en' : 'sv';
}

add_filter('locale', fn($locale) => s1dev_lang() === 'en' ? 'en_US' : $locale, 99);
add_filter('wpml_current_language', fn() => s1dev_lang());
add_filter('wpml_active_languages', function () {
    $here = home_url(add_query_arg([]));
    $sv = remove_query_arg('lang', $here);
    $en = add_query_arg('lang', 'en', $sv);
    return [
        'sv' => ['code' => 'sv', 'active' => s1dev_lang() === 'sv' ? 1 : 0, 'url' => $sv, 'native_name' => 'Svenska'],
        'en' => ['code' => 'en', 'active' => s1dev_lang() === 'en' ? 1 : 0, 'url' => $en, 'native_name' => 'English'],
    ];
});
