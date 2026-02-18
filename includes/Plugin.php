<?php
declare(strict_types=1);

namespace WpMcpUltimate;

use WpMcpUltimate\Server\McpAdapter;
use WpMcpUltimate\Abilities\Registry;
use WpMcpUltimate\Admin\Dashboard;

final class Plugin {
    private static self $instance;

    public static function instance(): self {
        if (!isset(self::$instance)) {
            self::$instance = new self();
            self::$instance->setup();
        }
        return self::$instance;
    }

    private function setup(): void {
        // Load Abilities API polyfill if needed (WordPress < 6.9)
        if (!function_exists('wp_register_ability')) {
            require_once WP_MCP_ULTIMATE_DIR . 'includes/Compat/AbilitiesApi.php';
        }

        // Register all WordPress abilities
        if (class_exists(Registry::class)) {
            Registry::init();
        }

        // Initialize MCP server
        if (class_exists(McpAdapter::class)) {
            McpAdapter::instance();
        }

        // Admin dashboard
        if (is_admin() && class_exists(Dashboard::class)) {
            Dashboard::init();
        }

        // Conflict detection
        add_action('admin_init', [$this, 'check_conflicts']);

        // First-time activation redirect
        add_action('admin_init', [$this, 'maybe_redirect_to_dashboard']);
    }

    public function check_conflicts(): void {
        $conflicts = [
            'mcp-adapter/mcp-adapter.php' => 'MCP Adapter',
            'mcp-expose-abilities/mcp-expose-abilities.php' => 'MCP Expose Abilities',
            'abilities-api/abilities-api.php' => 'Abilities API',
        ];

        foreach ($conflicts as $plugin_file => $plugin_name) {
            if (is_plugin_active($plugin_file)) {
                add_action('admin_notices', function () use ($plugin_name) {
                    printf(
                        '<div class="notice notice-warning is-dismissible"><p>%s</p></div>',
                        sprintf(
                            esc_html__('WP MCP Ultimate includes all functionality from %s. You can safely deactivate %s to avoid conflicts.', 'wp-mcp-ultimate'),
                            '<strong>' . esc_html($plugin_name) . '</strong>',
                            esc_html($plugin_name)
                        )
                    );
                });
            }
        }
    }

    public function maybe_redirect_to_dashboard(): void {
        if (!get_transient('wp_mcp_ultimate_activated')) {
            return;
        }
        delete_transient('wp_mcp_ultimate_activated');
        if (wp_doing_ajax() || isset($_GET['activate-multi'])) {
            return;
        }
        wp_safe_redirect(admin_url('tools.php?page=wp-mcp-ultimate'));
        exit;
    }

    public function __clone() {
        _doing_it_wrong(__FUNCTION__, 'Cloning is not allowed.', '1.0.0');
    }

    public function __wakeup() {
        _doing_it_wrong(__FUNCTION__, 'Deserializing is not allowed.', '1.0.0');
    }
}
