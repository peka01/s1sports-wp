<?php
/**
 * Local Playground only: signs the browser in as the admin user so wp-admin can be
 * checked without a password. Mounted into Playground's mu-plugins by .claude/launch.json;
 * it is never uploaded to the live site, and it refuses to run on any other host.
 */
if (!defined('ABSPATH')) exit;

add_action('init', function (): void {
    $host = (string) parse_url(home_url(), PHP_URL_HOST);
    if (!in_array($host, ['127.0.0.1', 'localhost'], true) || is_user_logged_in()) return;
    wp_set_current_user(1);
    wp_set_auth_cookie(1, true);
});
