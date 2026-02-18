<?php
/**
 * WP MCP Ultimate
 *
 * @package     WpMcpUltimate
 * @author      agricidaniel
 * @copyright   2026
 * @license     GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       WP MCP Ultimate
 * Plugin URI:        https://github.com/agricidaniel/wp-mcp-ultimate
 * Description:       Connect WordPress to AI in one click. Full MCP server with 57 WordPress abilities — no other plugins needed.
 * Requires at least: 6.7
 * Version:           1.0.0
 * Requires PHP:      8.0
 * Author:            agricidaniel
 * Author URI:        https://github.com/agricidaniel
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/old-licenses/gpl-2.0.html
 * Text Domain:       wp-mcp-ultimate
 */

declare(strict_types=1);

namespace WpMcpUltimate;

defined('ABSPATH') || exit();

define('WP_MCP_ULTIMATE_DIR', plugin_dir_path(__FILE__));
define('WP_MCP_ULTIMATE_URL', plugin_dir_url(__FILE__));
define('WP_MCP_ULTIMATE_VERSION', '1.0.0');

require_once __DIR__ . '/includes/Autoloader.php';

register_activation_hook(__FILE__, function (): void {
    // Set transient for admin redirect on first activation
    set_transient('wp_mcp_ultimate_activated', true, 30);
    // Flush rewrite rules for REST API routes
    flush_rewrite_rules();
});

register_deactivation_hook(__FILE__, function (): void {
    // Clean up transients
    delete_transient('wp_mcp_ultimate_activated');
    // Flush rewrite rules
    flush_rewrite_rules();
});

if (Autoloader::register()) {
    Plugin::instance();
}
