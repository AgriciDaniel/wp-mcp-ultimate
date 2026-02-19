<?php
/**
 * WP-CLI Command for MCP STDIO Transport
 *
 * @package WpMcpUltimate\Server\Cli
 */

declare( strict_types=1 );

namespace WpMcpUltimate\Server\Cli;

use WpMcpUltimate\Server\McpAdapter;
use function WP_CLI\Utils\format_items;

/**
 * Manage MCP servers via WP-CLI.
 *
 * Provides commands to serve MCP servers over STDIO transport for
 * communication with MCP clients via subprocess.
 */
class McpCommand extends \WP_CLI_Command { // phpcs:ignore

	/**
	 * Serve an MCP server via STDIO transport.
	 *
	 * ## OPTIONS
	 *
	 * [--server=<server-id>]
	 * : The ID of the MCP server to serve. If not specified, uses the first available server.
	 *
	 * [--user=<id|login|email>]
	 * : Run as a specific WordPress user for permission checks.
	 *
	 * ## EXAMPLES
	 *
	 *     # Serve the default MCP server as admin user
	 *     wp wp-mcp-ultimate serve --user=admin
	 *
	 *     # Serve a specific server
	 *     wp wp-mcp-ultimate serve --server=my-mcp-server --user=1
	 *
	 * @when after_wp_load
	 * @synopsis [--server=<server-id>] [--user=<id|login|email>]
	 */
	public function serve( array $args, array $assoc_args ): void {

		$adapter = McpAdapter::instance();
		$servers = $adapter->get_servers();

		if ( empty( $servers ) ) {
			\WP_CLI::error( 'No MCP servers are registered. Please register at least one server first.' );
		}

		$server_id = $assoc_args['server'] ?? null;
		$server    = null;

		if ( $server_id ) {
			$server = $adapter->get_server( $server_id );
			if ( ! $server ) {
				\WP_CLI::error( sprintf( 'Server with ID "%s" not found.', $server_id ) );
			}
		} else {
			$server    = array_values( $servers )[0];
			$server_id = $server->get_server_id();
			\WP_CLI::line( sprintf( 'Using server: %s', $server_id ) );
		}

		if ( isset( $assoc_args['user'] ) ) {
			$user = $this->get_user( $assoc_args['user'] );
			if ( ! $user ) {
				\WP_CLI::error( sprintf( 'User "%s" not found.', $assoc_args['user'] ) );
			}

			wp_set_current_user( $user->ID );
			\WP_CLI::debug( sprintf( 'Running as user: %s (ID: %d)', $user->user_login, $user->ID ) );
		} else {
			\WP_CLI::debug( 'Running without authentication. Some capabilities may be limited.' );
		}

		try {
			\WP_CLI::debug( sprintf( 'Starting STDIO bridge for server: %s', $server_id ) );

			$stdio_bridge = new StdioServerBridge( $server );
			$stdio_bridge->serve();
		} catch ( \RuntimeException $e ) {
			\WP_CLI::error( $e->getMessage() );
		} catch ( \Throwable $e ) {
			\WP_CLI::error( 'Failed to start STDIO bridge: ' . $e->getMessage() );
		}
	}

	/**
	 * List all registered MCP servers.
	 *
	 * ## OPTIONS
	 *
	 * [--format=<format>]
	 * : Render output in a particular format.
	 * ---
	 * default: table
	 * options:
	 *   - table
	 *   - csv
	 *   - json
	 *   - yaml
	 * ---
	 *
	 * ## EXAMPLES
	 *
	 *     wp wp-mcp-ultimate list
	 *     wp wp-mcp-ultimate list --format=json
	 *
	 * @when after_wp_load
	 * @synopsis [--format=<format>]
	 */
	public function list( array $args, array $assoc_args ): void {
		$adapter = McpAdapter::instance();
		$servers = $adapter->get_servers();

		if ( empty( $servers ) ) {
			\WP_CLI::line( 'No MCP servers registered.' );
			return;
		}

		$items = array();
		foreach ( $servers as $server ) {
			$items[] = array(
				'ID'          => $server->get_server_id(),
				'Name'        => $server->get_server_name(),
				'Version'     => $server->get_server_version(),
				'Tools'       => count( $server->get_tools() ),
				'Resources'   => count( $server->get_resources() ),
				'Prompts'     => count( $server->get_prompts() ),
				'Description' => $server->get_server_description(),
			);
		}

		$format = $assoc_args['format'] ?? 'table';
		format_items( $format, $items, array( 'ID', 'Name', 'Version', 'Tools', 'Resources', 'Prompts' ) );
	}

	/**
	 * Get a user by ID, login, or email.
	 *
	 * @param string $user User identifier.
	 * @return \WP_User|false
	 */
	private function get_user( string $user ) {
		if ( is_numeric( $user ) ) {
			return get_user_by( 'id', (int) $user );
		}

		$user_obj = get_user_by( 'login', $user );
		if ( $user_obj ) {
			return $user_obj;
		}

		return get_user_by( 'email', $user );
	}
}
