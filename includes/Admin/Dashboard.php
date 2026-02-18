<?php
declare(strict_types=1);

namespace WpMcpUltimate\Admin;

class Dashboard {
    public static function init(): void {
        add_action('admin_menu', [self::class, 'add_menu_page']);
        add_action('admin_enqueue_scripts', [self::class, 'enqueue_assets']);
    }

    public static function add_menu_page(): void {
        add_management_page(
            'WP MCP Ultimate',
            'MCP Ultimate',
            'manage_options',
            'wp-mcp-ultimate',
            [self::class, 'render_page']
        );
    }

    public static function render_page(): void {
        include WP_MCP_ULTIMATE_DIR . 'includes/Admin/views/dashboard.php';
    }

    public static function enqueue_assets(string $hook): void {
        if ($hook !== 'tools_page_wp-mcp-ultimate') {
            return;
        }

        wp_enqueue_style(
            'wp-mcp-ultimate-admin',
            WP_MCP_ULTIMATE_URL . 'assets/css/admin.css',
            [],
            WP_MCP_ULTIMATE_VERSION
        );

        wp_enqueue_script(
            'wp-mcp-ultimate-admin',
            WP_MCP_ULTIMATE_URL . 'assets/js/admin.js',
            ['jquery'],
            WP_MCP_ULTIMATE_VERSION,
            true
        );

        wp_localize_script('wp-mcp-ultimate-admin', 'wpMcpUltimate', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce'   => wp_create_nonce('wp-mcp-ultimate'),
            'restUrl' => rest_url('mcp/wp-mcp-ultimate'),
            'siteUrl' => site_url(),
        ]);
    }
}
