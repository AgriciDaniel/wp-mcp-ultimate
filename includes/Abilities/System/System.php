<?php
declare(strict_types=1);

namespace WpMcpUltimate\Abilities\System;

use WpMcpUltimate\Abilities\Helpers;

class System {
	public static function register(): void {
		// =========================================================================
		// SYSTEM - Get Transient
		// =========================================================================
		wp_register_ability(
			'system/get-transient',
			array(
				'label'               => 'Get Transient',
				'description'         => 'Get transient. Params: name (required).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'The transient name to retrieve.',
						),
					),
					'required'             => array( 'name' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'value'   => array(),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					if ( empty( $input['name'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Transient name is required', 'wp-mcp-ultimate' ), 'value' => null );
					}

					$value = get_transient( $input['name'] );

					if ( false === $value ) {
						return array( 'success' => false, 'message' => esc_html__( 'Transient not found or expired', 'wp-mcp-ultimate' ), 'value' => null );
					}

					return array(
						'success' => true,
						'value'   => $value,
						'message' => esc_html__( 'Transient retrieved successfully', 'wp-mcp-ultimate' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'manage_options' );
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
		// SYSTEM - Debug Log
		// =========================================================================
		wp_register_ability(
			'system/debug-log',
			array(
				'label'               => 'Read Debug Log',
				'description'         => 'Reads the WordPress debug.log file. Returns the last N lines, optionally filtered by a search pattern.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'lines'  => array(
							'type'        => 'integer',
							'default'     => 50,
							'minimum'     => 1,
							'maximum'     => 500,
							'description' => 'Number of lines to return from the end of the log.',
						),
						'filter' => array(
							'type'        => 'string',
							'description' => 'Optional filter string. Only lines containing this text will be returned.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'lines'   => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					$log_file = WP_CONTENT_DIR . '/debug.log';

					if ( ! file_exists( $log_file ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Debug log file not found', 'wp-mcp-ultimate' ), 'lines' => array() );
					}

					if ( ! is_readable( $log_file ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Debug log file not readable', 'wp-mcp-ultimate' ), 'lines' => array() );
					}

					$num_lines = isset( $input['lines'] ) ? min( max( 1, (int) $input['lines'] ), 500 ) : 50;
					$filter    = isset( $input['filter'] ) ? $input['filter'] : '';

					// Read file from end
					$file_content = file_get_contents( $log_file );
					$all_lines    = explode( "\n", $file_content );
					$all_lines    = array_filter( $all_lines, function( $line ) { return trim( $line ) !== ''; } );

					// Apply filter if specified
					if ( ! empty( $filter ) ) {
						$all_lines = array_filter( $all_lines, function( $line ) use ( $filter ) {
							return stripos( $line, $filter ) !== false;
						} );
					}

					// Get last N lines
					$result_lines = array_slice( $all_lines, -$num_lines );

					return array(
						'success' => true,
						'lines'   => array_values( $result_lines ),
						/* translators: %d: Number of lines returned. */
						'message' => esc_html( sprintf( _n( 'Returned %d line', 'Returned %d lines', count( $result_lines ), 'wp-mcp-ultimate' ), count( $result_lines ) ) ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'manage_options' );
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
		// SYSTEM - Toggle Debug Mode
		// =========================================================================
		wp_register_ability(
			'system/toggle-debug',
			array(
				'label'               => 'Toggle Debug Mode',
				'description'         => 'Toggles WP_DEBUG on or off in wp-config.php. Can also set WP_DEBUG_LOG and WP_DEBUG_DISPLAY.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'debug'         => array(
							'type'        => 'boolean',
							'description' => 'Set WP_DEBUG to true or false.',
						),
						'debug_log'     => array(
							'type'        => 'boolean',
							'description' => 'Set WP_DEBUG_LOG to true or false. Optional.',
						),
						'debug_display' => array(
							'type'        => 'boolean',
							'description' => 'Set WP_DEBUG_DISPLAY to true or false. Optional.',
						),
					),
					'required'             => array( 'debug' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
						'changes' => array( 'type' => 'array', 'items' => array( 'type' => 'string' ) ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					Helpers::load_admin_includes();

					if ( ! isset( $input['debug'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Missing required parameter: debug', 'wp-mcp-ultimate' ), 'changes' => array() );
					}

					$wp_config_path = ABSPATH . 'wp-config.php';

					// Initialize WP_Filesystem.
					global $wp_filesystem;
					\WP_Filesystem();

					if ( ! $wp_filesystem->exists( $wp_config_path ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'wp-config.php not found', 'wp-mcp-ultimate' ), 'changes' => array() );
					}

					if ( ! $wp_filesystem->is_writable( $wp_config_path ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'wp-config.php is not writable', 'wp-mcp-ultimate' ), 'changes' => array() );
					}

					$content = $wp_filesystem->get_contents( $wp_config_path );
					if ( false === $content ) {
						return array( 'success' => false, 'message' => esc_html__( 'Failed to read wp-config.php', 'wp-mcp-ultimate' ), 'changes' => array() );
					}

					$changes   = array();
					$debug_val = $input['debug'] ? 'true' : 'false';

					// Update or add WP_DEBUG
					if ( preg_match( "/define\s*\(\s*['\"]WP_DEBUG['\"]\s*,\s*(true|false)\s*\)/i", $content ) ) {
						$content   = preg_replace(
							"/define\s*\(\s*['\"]WP_DEBUG['\"]\s*,\s*(true|false)\s*\)/i",
							"define( 'WP_DEBUG', {$debug_val} )",
							$content
						);
						$changes[] = "WP_DEBUG set to {$debug_val}";
					} else {
						// Add before "That's all" comment or at end
						$insert = "define( 'WP_DEBUG', {$debug_val} );\n";
						if ( strpos( $content, "/* That's all" ) !== false ) {
							$content = str_replace( "/* That's all", $insert . "/* That's all", $content );
						} else {
							$content .= "\n" . $insert;
						}
						$changes[] = "WP_DEBUG added and set to {$debug_val}";
					}

					// Handle WP_DEBUG_LOG if specified
					if ( isset( $input['debug_log'] ) ) {
						$log_val = $input['debug_log'] ? 'true' : 'false';
						if ( preg_match( "/define\s*\(\s*['\"]WP_DEBUG_LOG['\"]\s*,\s*(true|false)\s*\)/i", $content ) ) {
							$content   = preg_replace(
								"/define\s*\(\s*['\"]WP_DEBUG_LOG['\"]\s*,\s*(true|false)\s*\)/i",
								"define( 'WP_DEBUG_LOG', {$log_val} )",
								$content
							);
							$changes[] = "WP_DEBUG_LOG set to {$log_val}";
						} elseif ( $input['debug_log'] ) {
							// Only add if setting to true
							$insert = "define( 'WP_DEBUG_LOG', true );\n";
							$content = preg_replace(
								"/(define\s*\(\s*['\"]WP_DEBUG['\"]\s*,\s*(true|false)\s*\)\s*;)/i",
								"$1\n" . $insert,
								$content
							);
							$changes[] = "WP_DEBUG_LOG added and set to true";
						}
					}

					// Handle WP_DEBUG_DISPLAY if specified
					if ( isset( $input['debug_display'] ) ) {
						$display_val = $input['debug_display'] ? 'true' : 'false';
						if ( preg_match( "/define\s*\(\s*['\"]WP_DEBUG_DISPLAY['\"]\s*,\s*(true|false)\s*\)/i", $content ) ) {
							$content   = preg_replace(
								"/define\s*\(\s*['\"]WP_DEBUG_DISPLAY['\"]\s*,\s*(true|false)\s*\)/i",
								"define( 'WP_DEBUG_DISPLAY', {$display_val} )",
								$content
							);
							$changes[] = "WP_DEBUG_DISPLAY set to {$display_val}";
						} elseif ( ! $input['debug_display'] ) {
							// Only add if setting to false (to hide errors)
							$insert = "define( 'WP_DEBUG_DISPLAY', false );\n";
							$content = preg_replace(
								"/(define\s*\(\s*['\"]WP_DEBUG['\"]\s*,\s*(true|false)\s*\)\s*;)/i",
								"$1\n" . $insert,
								$content
							);
							$changes[] = "WP_DEBUG_DISPLAY added and set to false";
						}
					}

					// Write changes
					$result = $wp_filesystem->put_contents( $wp_config_path, $content, FS_CHMOD_FILE );
					if ( false === $result ) {
						return array( 'success' => false, 'message' => esc_html__( 'Failed to write wp-config.php', 'wp-mcp-ultimate' ), 'changes' => array() );
					}

					return array(
						'success' => true,
						'message' => esc_html__( 'wp-config.php updated successfully', 'wp-mcp-ultimate' ),
						'changes' => $changes,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'manage_options' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					),
				),
			)
		);
	}
}
