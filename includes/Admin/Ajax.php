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

    private const TEST_PASSWORD_NAME = 'WP MCP Ultimate (connection test)';

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
            }

            // Clean up a temp password left behind by an interrupted test —
            // names are unique, so a leftover would block creating a new one.
            if ($pass['name'] === self::TEST_PASSWORD_NAME) {
                WP_Application_Passwords::delete_application_password($user_id, $pass['uuid']);
            }
        }

        if (!$app_pass) {
            delete_user_meta($user_id, 'wp_mcp_ultimate_app_password');
            wp_send_json_error(['message' => 'Application password not found. Please generate a new key.']);
        }

        // The stored key's plaintext only existed at creation time, so it
        // cannot be replayed here. Instead, create a short-lived password,
        // run a real MCP initialize call through it, and delete it — this
        // exercises routing, Basic auth, and Authorization-header
        // pass-through exactly like an external client would.
        $created = WP_Application_Passwords::create_new_application_password(
            $user_id,
            ['name' => self::TEST_PASSWORD_NAME]
        );

        if (is_wp_error($created)) {
            wp_send_json_error(['message' => 'Could not create a temporary test password: ' . $created->get_error_message()]);
        }

        [$test_password, $test_item] = $created;

        $response = wp_remote_post(
            rest_url('mcp/wp-mcp-ultimate'),
            [
                'headers'   => [
                    'Authorization' => 'Basic ' . base64_encode($user->user_login . ':' . $test_password),
                    'Content-Type'  => 'application/json',
                    'Accept'        => 'application/json, text/event-stream',
                ],
                'body'      => wp_json_encode([
                    'jsonrpc' => '2.0',
                    'id'      => 1,
                    'method'  => 'initialize',
                    'params'  => [
                        'protocolVersion' => '2025-06-18',
                        'capabilities'    => new \stdClass(),
                        'clientInfo'      => [
                            'name'    => 'wp-mcp-ultimate-connection-test',
                            'version' => WP_MCP_ULTIMATE_VERSION,
                        ],
                    ],
                ]),
                'timeout'   => 10,
                'sslverify' => false, // Loopback request; local certs are often self-signed.
            ]
        );

        // wp_send_json_* exits, so the temp password must be gone first.
        WP_Application_Passwords::delete_application_password($user_id, $test_item['uuid']);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => 'Connection failed: ' . $response->get_error_message()]);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = json_decode(wp_remote_retrieve_body($response), true);

        if ($code === 200 && isset($body['result']['serverInfo'])) {
            wp_send_json_success(['message' => 'Connection successful. MCP server responded to initialize.', 'status' => $code]);
        }

        if ($code === 401 || $code === 403) {
            wp_send_json_error([
                'message' => 'Authentication failed (HTTP ' . $code . '). Your server may be stripping the Authorization header before it reaches WordPress (common on Apache/FastCGI hosts). Add this to your .htaccess: RewriteEngine On / RewriteCond %{HTTP:Authorization} ^(.*) / RewriteRule .* - [E=HTTP_AUTHORIZATION:%1]',
                'status'  => $code,
            ]);
        }

        wp_send_json_error([
            'message' => 'Unexpected response (HTTP ' . $code . ').',
            'status'  => $code,
        ]);
    }
}
