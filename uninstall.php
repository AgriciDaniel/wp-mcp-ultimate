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
