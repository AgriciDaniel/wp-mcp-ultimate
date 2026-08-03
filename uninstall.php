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
// Remove OAuth code/token/rate-limit transients on installs that keep
// transients in wp_options (the common case). On a persistent object cache
// these rows don't exist and there is no safe pattern-delete for a single
// plugin's transients — those entries auto-expire within their TTL (refresh
// tokens are the longest at 30 days), so we intentionally let them lapse
// rather than flush the shared 'transient' cache group.
$wpdb->query(
    "DELETE FROM {$wpdb->options}
     WHERE option_name LIKE '\_transient\_mcp\_oauth\_%'
        OR option_name LIKE '\_transient\_timeout\_mcp\_oauth\_%'"
);
