<?php
// If uninstall not called from WordPress, exit
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete plugin options
delete_option('wp_mcp_ultimate_settings');

// Clean up Application Passwords created by this plugin (tracked in user meta)
$users = get_users(['meta_key' => 'wp_mcp_ultimate_app_password']);
foreach ($users as $user) {
    $uuid = get_user_meta($user->ID, 'wp_mcp_ultimate_app_password', true);
    if ($uuid && class_exists('WP_Application_Passwords')) {
        WP_Application_Passwords::delete_application_password($user->ID, $uuid);
    }
    delete_user_meta($user->ID, 'wp_mcp_ultimate_app_password');
}

// Clean up transients
delete_transient('wp_mcp_ultimate_activated');

// OAuth server data: registered clients option and token/code/rate-limit
// transients (deleted directly — token transient names contain hashes, so
// they cannot be enumerated via the transient API).
delete_option('wp_mcp_ultimate_oauth_clients');
global $wpdb;
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '\_transient\_mcp\_oauth\_%'
        OR option_name LIKE '\_transient\_timeout\_mcp\_oauth\_%'"
);
if (function_exists('wp_cache_flush_group')) {
    wp_cache_flush_group('transient');
}
