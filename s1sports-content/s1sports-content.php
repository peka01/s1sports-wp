<?php
/**
 * Plugin Name: S1 Sports Content
 * Description: Athletes, events and the meeting form for the S1 Sports theme.
 * Version: 0.9.0
 * Requires PHP: 8.0
 * Text Domain: s1sports-content
 */
defined('ABSPATH') || exit;

const S1_CONTENT_VERSION = '0.9.0';

require_once __DIR__ . '/inc/contact.php';
require_once __DIR__ . '/inc/events.php';

add_action('init', function () {
    // The production site already owns this post type. Register it only in fresh local installs.
    if (!post_type_exists('atleter')) {
        register_post_type('atleter', [
            'labels' => ['name' => 'Atleter', 'singular_name' => 'Atlet'],
            'public' => true,
            'has_archive' => false,
            'show_in_rest' => true,
            'supports' => ['title', 'editor', 'thumbnail', 'page-attributes'],
            'rewrite' => ['slug' => 'atleter'],
            'menu_icon' => 'dashicons-groups',
        ]);
    }
}, 20);

add_action('acf/init', function () {
    if (!function_exists('acf_add_local_field_group')) return;
    acf_add_local_field_group([
        'key' => 'group_s1_athlete',
        'title' => 'S1 Sports — Visning',
        'fields' => [
            ['key' => 'field_s1_featured', 'label' => 'Visa på startsidan', 'name' => 's1_featured', 'type' => 'true_false', 'default_value' => 0],
            ['key' => 'field_s1_display_order', 'label' => 'Visningsordning', 'name' => 's1_display_order', 'type' => 'number', 'default_value' => 0],
        ],
        'location' => [[['param' => 'post_type', 'operator' => '==', 'value' => 'atleter']]],
        'position' => 'normal',
        'style' => 'default',
        'active' => true,
    ]);
});

/*
 * The live athlete field group "Atleter" (made in wp-admin) offers fotboll, tennis, golf, hockey
 * and ovrigt. Add friidrott here so it is available without editing that field group.
 */
add_filter('acf/load_field/key=field_69f9e0813226b', function ($field) {
    if (is_array($field) && isset($field['choices']) && is_array($field['choices']) && !isset($field['choices']['friidrott'])) {
        $field['choices']['friidrott'] = 'friidrott';
    }
    return $field;
});
