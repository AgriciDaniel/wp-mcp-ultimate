<?php
declare(strict_types=1);

namespace WpMcpUltimate\Admin;

use WP_Application_Passwords;

class Ajax {
    public static function init(): void {
        add_action('wp_ajax_wp_mcp_ultimate_generate_key', [self::class, 'generate_key']);
        add_action('wp_ajax_wp_mcp_ultimate_revoke_key', [self::class, 'revoke_key']);
        add_action('wp_ajax_wp_mcp_ultimate_test_connection', [self::class, 'test_connection']);
    }

    public static function generate_key(): void {
        check_ajax_referer('wp-mcp-ultimate', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.']);
        }

        $user_id = get_current_user_id();

        // Remove existing app password if one exists.
        $existing_uuid = get_user_meta($user_id, 'wp_mcp_ultimate_app_password', true);
        if ($existing_uuid) {
            WP_Application_Passwords::delete_application_password($user_id, $existing_uuid);
            delete_user_meta($user_id, 'wp_mcp_ultimate_app_password');
        }

        $result = WP_Application_Passwords::create_new_application_password(
            $user_id,
            [
                'name' => 'WP MCP Ultimate',
            ]
        );

        if (is_wp_error($result)) {
            wp_send_json_error(['message' => $result->get_error_message()]);
        }

        [$password, $item] = $result;

        // Store the UUID for tracking and revocation.
        update_user_meta($user_id, 'wp_mcp_ultimate_app_password', $item['uuid']);

        $user = wp_get_current_user();

        wp_send_json_success([
            'password' => $password,
            'username' => $user->user_login,
            'base64'   => base64_encode($user->user_login . ':' . $password),
        ]);
    }

    public static function revoke_key(): void {
        check_ajax_referer('wp-mcp-ultimate', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.']);
        }

        $user_id = get_current_user_id();
        $uuid    = get_user_meta($user_id, 'wp_mcp_ultimate_app_password', true);

        if (!$uuid) {
            wp_send_json_error(['message' => 'No API key found.']);
        }

        $deleted = WP_Application_Passwords::delete_application_password($user_id, $uuid);

        if (is_wp_error($deleted)) {
            wp_send_json_error(['message' => $deleted->get_error_message()]);
        }

        delete_user_meta($user_id, 'wp_mcp_ultimate_app_password');

        wp_send_json_success(['message' => 'API key revoked.']);
    }

    public static function test_connection(): void {
        check_ajax_referer('wp-mcp-ultimate', 'nonce');

        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Insufficient permissions.']);
        }

        $user_id = get_current_user_id();
        $uuid    = get_user_meta($user_id, 'wp_mcp_ultimate_app_password', true);

        if (!$uuid) {
            wp_send_json_error(['message' => 'No API key configured. Generate one first.']);
        }

        $user      = wp_get_current_user();
        $passwords = WP_Application_Passwords::get_user_application_passwords($user_id);
        $app_pass  = null;

        foreach ($passwords as $pass) {
            if ($pass['uuid'] === $uuid) {
                $app_pass = $pass;
                break;
            }
        }

        if (!$app_pass) {
            delete_user_meta($user_id, 'wp_mcp_ultimate_app_password');
            wp_send_json_error(['message' => 'Application password not found. Please generate a new key.']);
        }

        // Self-request to REST API to verify it's reachable.
        $response = wp_remote_get(
            rest_url('mcp/wp-mcp-ultimate'),
            [
                'headers'   => [
                    'Authorization' => 'Basic ' . base64_encode($user->user_login . ':' . $uuid),
                ],
                'timeout'   => 10,
                'sslverify' => false,
            ]
        );

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Connection failed: ' . $response->get_error_message()]);
        }

        $code = wp_remote_retrieve_response_code($response);

        if ($code >= 200 && $code < 400) {
            wp_send_json_success(['message' => 'Connection successful.', 'status' => $code]);
        }

        wp_send_json_error([
            'message' => 'Unexpected response (HTTP ' . $code . ').',
            'status'  => $code,
        ]);
    }
}
