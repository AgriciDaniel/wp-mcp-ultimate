<?php
declare(strict_types=1);

namespace WpMcpUltimate\Abilities\Plugins;

use WpMcpUltimate\Abilities\Helpers;

class Plugins {
	public static function register(): void {
		// =========================================================================
		// PLUGINS - Upload & Install
		// =========================================================================
		wp_register_ability(
			'plugins/upload',
			array(
				'label'               => 'Upload Plugin',
				'description'         => 'Uploads and installs a plugin from a URL (zip file). Can optionally activate after install and overwrite existing plugin.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'url' ),
					'properties'           => array(
						'url'       => array(
							'type'        => 'string',
							'description' => 'URL to the plugin zip file.',
						),
						'activate'  => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => 'Activate the plugin after installation.',
						),
						'overwrite' => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => 'Overwrite existing plugin if it exists.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'   => array( 'type' => 'boolean' ),
						'message'   => array( 'type' => 'string' ),
						'plugin'    => array( 'type' => 'string' ),
						'activated' => array( 'type' => 'boolean' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					Helpers::load_admin_includes();

					if ( empty( $input['url'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Plugin URL is required', 'wp-mcp-ultimate' ) );
					}

					// Download the zip file.
					$download_file = download_url( $input['url'] );
					if ( is_wp_error( $download_file ) ) {
						/* translators: %s: Error message */
						return array( 'success' => false, 'message' => esc_html__( 'Download failed: ', 'wp-mcp-ultimate' ) . esc_html( $download_file->get_error_message() ) );
					}

					$result = Helpers::install_plugin_zip( $download_file, $input );
					wp_delete_file( $download_file );

					return $result;
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'install_plugins' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// =========================================================================
		// PLUGINS - Upload From Base64
		// =========================================================================
		wp_register_ability(
			'plugins/upload-base64',
			array(
				'label'               => 'Upload Plugin (Base64 or Zip Path)',
				'description'         => 'Uploads and installs a plugin from base64-encoded zip content or a local zip path. Can optionally activate after install and overwrite existing plugin.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'content_base64' => array(
							'type'        => 'string',
							'description' => 'Base64-encoded zip file content.',
						),
						'zip_path'       => array(
							'type'        => 'string',
							'description' => 'Absolute path to a local plugin zip on the WordPress server.',
						),
						'filename'       => array(
							'type'        => 'string',
							'description' => 'Optional filename used for the temp zip.',
						),
						'activate'       => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => 'Activate the plugin after installation.',
						),
						'overwrite'      => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => 'Overwrite existing plugin if it exists.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'   => array( 'type' => 'boolean' ),
						'message'   => array( 'type' => 'string' ),
						'plugin'    => array( 'type' => 'string' ),
						'activated' => array( 'type' => 'boolean' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					Helpers::load_admin_includes();

					if ( ! empty( $input['zip_path'] ) ) {
						$zip_path = wp_normalize_path( $input['zip_path'] );
						if ( ! is_file( $zip_path ) || ! is_readable( $zip_path ) ) {
							return array( 'success' => false, 'message' => esc_html__( 'zip_path must point to a readable .zip file', 'wp-mcp-ultimate' ) );
						}
						if ( ! str_ends_with( $zip_path, '.zip' ) ) {
							return array( 'success' => false, 'message' => esc_html__( 'zip_path must point to a .zip file', 'wp-mcp-ultimate' ) );
						}

						return Helpers::install_plugin_zip( $zip_path, $input );
					}

					if ( empty( $input['content_base64'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'content_base64 or zip_path is required', 'wp-mcp-ultimate' ) );
					}

					$decoded = base64_decode( $input['content_base64'], true );
					if ( false === $decoded ) {
						return array( 'success' => false, 'message' => esc_html__( 'Invalid base64 payload', 'wp-mcp-ultimate' ) );
					}

					$filename = ! empty( $input['filename'] ) ? sanitize_file_name( $input['filename'] ) : 'plugin.zip';
					if ( ! str_ends_with( $filename, '.zip' ) ) {
						$filename .= '.zip';
					}

					$temp_file = wp_tempnam( $filename );
					if ( ! $temp_file ) {
						return array( 'success' => false, 'message' => esc_html__( 'Unable to create temporary file', 'wp-mcp-ultimate' ) );
					}

					$bytes_written = file_put_contents( $temp_file, $decoded );
					if ( false === $bytes_written ) {
						wp_delete_file( $temp_file );
						return array( 'success' => false, 'message' => esc_html__( 'Failed to write temporary zip file', 'wp-mcp-ultimate' ) );
					}

					$result = Helpers::install_plugin_zip( $temp_file, $input );
					wp_delete_file( $temp_file );

					return $result;
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'install_plugins' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// =========================================================================
		// PLUGINS - List
		// =========================================================================
		wp_register_ability(
			'plugins/list',
			array(
				'label'               => 'List Plugins',
				'description'         => 'List plugins. Params: status (all/active/inactive, optional).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'status' => array(
							'type'        => 'string',
							'enum'        => array( 'all', 'active', 'inactive' ),
							'default'     => 'all',
							'description' => 'Filter by plugin status.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'plugins' => array( 'type' => 'array' ),
						'total'   => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					$all_plugins    = get_plugins();
					$active_plugins = get_option( 'active_plugins', array() );
					$status_filter  = $input['status'] ?? 'all';

					$plugins = array();
					foreach ( $all_plugins as $file => $data ) {
						$is_active = in_array( $file, $active_plugins, true );

						if ( 'active' === $status_filter && ! $is_active ) {
							continue;
						}
						if ( 'inactive' === $status_filter && $is_active ) {
							continue;
						}

						$plugins[] = array(
							'file'        => $file,
							'name'        => $data['Name'],
							'version'     => $data['Version'],
							'author'      => $data['Author'],
							'description' => $data['Description'],
							'active'      => $is_active,
						);
					}

					return array(
						'plugins' => $plugins,
						'total'   => count( $plugins ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'activate_plugins' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => true,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// =========================================================================
		// PLUGINS - Delete
		// =========================================================================
		wp_register_ability(
			'plugins/delete',
			array(
				'label'               => 'Delete Plugin',
				'description'         => 'Delete plugin. Params: plugin (required, e.g. "folder/file.php").',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'plugin' => array(
							'type'        => 'string',
							'description' => 'Plugin file path (e.g., "plugin-folder/plugin-file.php").',
						),
					),
					'required'             => array( 'plugin' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( array $input ): array {
					if ( empty( $input['plugin'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Plugin parameter is required', 'wp-mcp-ultimate' ) );
					}

					$plugin_file = $input['plugin'];

					// Check if plugin exists.
					$all_plugins = get_plugins();
					if ( ! isset( $all_plugins[ $plugin_file ] ) ) {
						/* translators: %s: Plugin file name */
						return array( 'success' => false, 'message' => esc_html__( 'Plugin not found: ', 'wp-mcp-ultimate' ) . esc_html( $plugin_file ) );
					}

					// Check if plugin is active.
					if ( is_plugin_active( $plugin_file ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Cannot delete active plugin. Deactivate it first.', 'wp-mcp-ultimate' ) );
					}

					// Delete the plugin.
					$deleted = delete_plugins( array( $plugin_file ) );
					if ( is_wp_error( $deleted ) ) {
						/* translators: %s: Error message */
						return array( 'success' => false, 'message' => esc_html__( 'Delete failed: ', 'wp-mcp-ultimate' ) . esc_html( $deleted->get_error_message() ) );
					}

					return array(
						'success' => true,
						/* translators: %s: Plugin file name */
						'message' => esc_html__( 'Plugin deleted successfully: ', 'wp-mcp-ultimate' ) . esc_html( $plugin_file ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'delete_plugins' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => false,
					),
				),
			)
		);

		// =========================================================================
		// PLUGINS - Activate
		// =========================================================================
		wp_register_ability(
			'plugins/activate',
			array(
				'label'               => 'Activate Plugin',
				'description'         => 'Activates an installed plugin. Params: plugin (required, e.g. "folder/file.php").',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'plugin' => array(
							'type'        => 'string',
							'description' => 'Plugin file path (e.g., "plugin-folder/plugin-file.php").',
						),
					),
					'required'             => array( 'plugin' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( array $input ): array {
					if ( empty( $input['plugin'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Plugin parameter is required', 'wp-mcp-ultimate' ) );
					}

					$plugin_file = $input['plugin'];

					// Check if plugin exists.
					$all_plugins = get_plugins();
					if ( ! isset( $all_plugins[ $plugin_file ] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Plugin not found: ', 'wp-mcp-ultimate' ) . esc_html( $plugin_file ) );
					}

					// Check if already active.
					if ( is_plugin_active( $plugin_file ) ) {
						return array( 'success' => true, 'message' => esc_html__( 'Plugin is already active: ', 'wp-mcp-ultimate' ) . esc_html( $plugin_file ) );
					}

					// Activate the plugin.
					$result = activate_plugin( $plugin_file );
					if ( is_wp_error( $result ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Activation failed: ', 'wp-mcp-ultimate' ) . esc_html( $result->get_error_message() ) );
					}

					return array(
						'success' => true,
						'message' => esc_html__( 'Plugin activated successfully: ', 'wp-mcp-ultimate' ) . esc_html( $plugin_file ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'activate_plugins' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);

		// =========================================================================
		// PLUGINS - Deactivate
		// =========================================================================
		wp_register_ability(
			'plugins/deactivate',
			array(
				'label'               => 'Deactivate Plugin',
				'description'         => 'Deactivates an active plugin. Params: plugin (required, e.g. "folder/file.php").',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'plugin' => array(
							'type'        => 'string',
							'description' => 'Plugin file path (e.g., "plugin-folder/plugin-file.php").',
						),
					),
					'required'             => array( 'plugin' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( array $input ): array {
					if ( empty( $input['plugin'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Plugin parameter is required', 'wp-mcp-ultimate' ) );
					}

					$plugin_file = $input['plugin'];

					// Check if plugin exists.
					$all_plugins = get_plugins();
					if ( ! isset( $all_plugins[ $plugin_file ] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Plugin not found: ', 'wp-mcp-ultimate' ) . esc_html( $plugin_file ) );
					}

					// Check if already inactive.
					if ( ! is_plugin_active( $plugin_file ) ) {
						return array( 'success' => true, 'message' => esc_html__( 'Plugin is already inactive: ', 'wp-mcp-ultimate' ) . esc_html( $plugin_file ) );
					}

					// Deactivate the plugin.
					deactivate_plugins( $plugin_file );

					// Verify deactivation.
					if ( is_plugin_active( $plugin_file ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Deactivation failed for: ', 'wp-mcp-ultimate' ) . esc_html( $plugin_file ) );
					}

					return array(
						'success' => true,
						'message' => esc_html__( 'Plugin deactivated successfully: ', 'wp-mcp-ultimate' ) . esc_html( $plugin_file ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'activate_plugins' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => true,
					),
				),
			)
		);
	}
}
