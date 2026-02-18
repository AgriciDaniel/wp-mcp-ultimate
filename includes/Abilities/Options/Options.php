<?php
declare(strict_types=1);

namespace WpMcpUltimate\Abilities\Options;

use WpMcpUltimate\Abilities\Helpers;

class Options {
	public static function register(): void {
		// =========================================================================
		// OPTIONS - Get Option
		// =========================================================================
		wp_register_ability(
			'options/get',
			array(
				'label'               => 'Get Option',
				'description'         => 'Get option. Params: name (required).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'The option name to retrieve (e.g., "blogname", "rank_math_options_titles").',
						),
					),
					'required'             => array( 'name' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'name'    => array( 'type' => 'string' ),
						'value'   => array( 'description' => 'The option value (type varies)' ),
						'type'    => array( 'type' => 'string', 'description' => 'PHP type of the value' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					if ( empty( $input['name'] ) ) {
						return array( 'success' => false, 'name' => '', 'value' => null, 'type' => 'null' );
					}

					$name  = sanitize_key( $input['name'] );
					$value = get_option( $name, null );

					if ( null === $value ) {
						return array(
							'success' => false,
							'name'    => $name,
							'value'   => null,
							'type'    => 'null',
							'message' => esc_html__( 'Option not found', 'wp-mcp-ultimate' ),
						);
					}

					return array(
						'success' => true,
						'name'    => $name,
						'value'   => $value,
						'type'    => gettype( $value ),
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
		// OPTIONS - Update Option
		// =========================================================================
		wp_register_ability(
			'options/update',
			array(
				'label'               => 'Update Option',
				'description'         => 'Update option. Params: name, value (required), key (optional for array options).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'name'  => array(
							'type'        => 'string',
							'description' => 'The option name to update.',
						),
						'value' => array(
							'description' => 'The new value (can be string, number, boolean, array, or object).',
						),
						'key'   => array(
							'type'        => 'string',
							'description' => 'Optional: If the option is an array, update only this specific key within it.',
						),
					),
					'required'             => array( 'name', 'value' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'   => array( 'type' => 'boolean' ),
						'name'      => array( 'type' => 'string' ),
						'message'   => array( 'type' => 'string' ),
						'old_value' => array( 'description' => 'Previous value (for verification)' ),
						'new_value' => array( 'description' => 'New value after update' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					if ( empty( $input['name'] ) ) {
						return array( 'success' => false, 'name' => '', 'message' => esc_html__( 'Missing required parameter: name', 'wp-mcp-ultimate' ) );
					}

					$name = sanitize_key( $input['name'] );

					// Protected options that cannot be modified via MCP for security.
					$protected_options = array(
						'active_plugins',           // Can disable security plugins.
						'siteurl',                  // Can break site access.
						'home',                     // Can break site access.
						'users_can_register',       // Security: user registration.
						'default_role',             // Security: new user privileges.
						'admin_email',              // Security: site recovery email.
						'cron',                     // Can inject malicious scheduled tasks.
						'auto_updater.lock',        // Can block security updates.
						'rewrite_rules',            // Can break permalinks.
						'recently_activated',       // Plugin state tracking.
						'uninstall_plugins',        // Plugin cleanup callbacks.
						'wp_user_roles',            // Security: role definitions.
					);

					if ( in_array( $name, $protected_options, true ) ) {
						return array(
							'success' => false,
							'name'    => $name,
							/* translators: %s: Option name. */
							'message' => esc_html( sprintf( __( "Option '%s' is protected and cannot be modified via MCP for security reasons.", 'wp-mcp-ultimate' ), $name ) ),
						);
					}
					$new_value = $input['value'];
					$key       = isset( $input['key'] ) ? $input['key'] : null;
					$old_value = get_option( $name );

					// If updating a specific key within an array option
					if ( null !== $key && is_array( $old_value ) ) {
						$updated_value       = $old_value;
						$old_key_value       = isset( $old_value[ $key ] ) ? $old_value[ $key ] : null;
						$updated_value[ $key ] = $new_value;

						$result = update_option( $name, $updated_value );

						return array(
							'success'   => $result,
							'name'      => $name,
							'key'       => $key,
							'message'   => $result ? "Updated key '{$key}' in option '{$name}'" : 'Update failed or value unchanged',
							'old_value' => $old_key_value,
							'new_value' => $new_value,
						);
					}

					// Update entire option
					$result = update_option( $name, $new_value );

					return array(
						'success'   => $result,
						'name'      => $name,
						'message'   => $result ? "Option '{$name}' updated successfully" : 'Update failed or value unchanged',
						'old_value' => $old_value,
						'new_value' => $new_value,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'manage_options' );
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
		// OPTIONS - List Options (search)
		// =========================================================================
		wp_register_ability(
			'options/list',
			array(
				'label'               => 'List Options',
				'description'         => 'List options. Params: search (required, SQL LIKE pattern), per_page.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'search'   => array(
							'type'        => 'string',
							'description' => 'Search pattern (SQL LIKE pattern, e.g., "rank_math%" or "%seo%").',
						),
						'per_page' => array(
							'type'        => 'integer',
							'default'     => 50,
							'minimum'     => 1,
							'maximum'     => 200,
							'description' => 'Number of options to return.',
						),
					),
					'required'             => array( 'search' ),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'options' => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'name' => array( 'type' => 'string' ),
									'type' => array( 'type' => 'string' ),
									'size' => array( 'type' => 'integer', 'description' => 'Approximate size in bytes' ),
								),
							),
						),
						'total'   => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					global $wpdb;

					$input = is_array( $input ) ? $input : array();

					if ( empty( $input['search'] ) ) {
						return array( 'success' => false, 'options' => array(), 'total' => 0, 'message' => esc_html__( 'Missing search pattern', 'wp-mcp-ultimate' ) );
					}

					$search   = $input['search'];
					$per_page = isset( $input['per_page'] ) ? min( (int) $input['per_page'], 200 ) : 50;

					// phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery, WordPress.DB.DirectDatabaseQuery.NoCaching
					$results = $wpdb->get_results(
						$wpdb->prepare(
							"SELECT option_name, option_value FROM {$wpdb->options} WHERE option_name LIKE %s LIMIT %d",
							$search,
							$per_page
						),
						ARRAY_A
					);

					$options = array();
					foreach ( $results as $row ) {
						$value     = maybe_unserialize( $row['option_value'] );
						$options[] = array(
							'name' => $row['option_name'],
							'type' => gettype( $value ),
							'size' => strlen( $row['option_value'] ),
						);
					}

					return array(
						'success' => true,
						'options' => $options,
						'total'   => count( $options ),
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
	}
}
