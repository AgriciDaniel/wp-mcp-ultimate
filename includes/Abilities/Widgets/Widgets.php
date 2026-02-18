<?php
declare(strict_types=1);

namespace WpMcpUltimate\Abilities\Widgets;

use WpMcpUltimate\Abilities\Helpers;

class Widgets {
	public static function register(): void {
		// =========================================================================
		// WIDGETS - List Sidebars
		// =========================================================================
		wp_register_ability(
			'widgets/list-sidebars',
			array(
				'label'               => 'List Widget Sidebars',
				'description'         => 'List sidebars. No params.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => array( 'object', 'null' ),
					'properties'           => (object) array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'  => array( 'type' => 'boolean' ),
						'sidebars' => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					global $wp_registered_sidebars;

					$sidebars = array();
					foreach ( $wp_registered_sidebars as $id => $sidebar ) {
						$sidebars[] = array(
							'id'          => $id,
							'name'        => $sidebar['name'],
							'description' => $sidebar['description'] ?? '',
						);
					}

					return array(
						'success'  => true,
						'sidebars' => $sidebars,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_theme_options' );
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
		// WIDGETS - Get Sidebar Widgets
		// =========================================================================
		wp_register_ability(
			'widgets/get-sidebar',
			array(
				'label'               => 'Get Sidebar Widgets',
				'description'         => 'Get sidebar widgets. Params: sidebar_id (required).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'sidebar_id' ),
					'properties'           => array(
						'sidebar_id' => array(
							'type'        => 'string',
							'description' => 'Sidebar ID.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'sidebar' => array( 'type' => 'object' ),
						'widgets' => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					global $wp_registered_sidebars, $wp_registered_widgets;
					$input = is_array( $input ) ? $input : array();

					if ( empty( $input['sidebar_id'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Sidebar ID is required', 'wp-mcp-ultimate' ) );
					}

					$sidebar_id = $input['sidebar_id'];
					if ( ! isset( $wp_registered_sidebars[ $sidebar_id ] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Sidebar not found', 'wp-mcp-ultimate' ) );
					}

					// Get sidebars widgets via option (wp_get_sidebars_widgets is flagged by plugin check).
					$sidebars_widgets = get_option( 'sidebars_widgets', array() );
					$sidebars_widgets = (array) apply_filters( 'sidebars_widgets', $sidebars_widgets );
					$widget_ids       = $sidebars_widgets[ $sidebar_id ] ?? array();
					$widgets          = array();

					foreach ( $widget_ids as $widget_id ) {
						if ( isset( $wp_registered_widgets[ $widget_id ] ) ) {
							$widget = $wp_registered_widgets[ $widget_id ];
							$widgets[] = array(
								'id'   => $widget_id,
								'name' => $widget['name'],
							);
						}
					}

					return array(
						'success' => true,
						'sidebar' => array(
							'id'   => $sidebar_id,
							'name' => $wp_registered_sidebars[ $sidebar_id ]['name'],
						),
						'widgets' => $widgets,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_theme_options' );
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
		// WIDGETS - List Available Widgets
		// =========================================================================
		wp_register_ability(
			'widgets/list-available',
			array(
				'label'               => 'List Available Widgets',
				'description'         => 'List available widgets. No params.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => array( 'object', 'null' ),
					'properties'           => (object) array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'widgets' => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					global $wp_widget_factory;

					$widgets = array();
					foreach ( $wp_widget_factory->widgets as $class => $widget ) {
						$widgets[] = array(
							'id_base'     => $widget->id_base,
							'name'        => $widget->name,
							'description' => $widget->widget_options['description'] ?? '',
						);
					}

					return array(
						'success' => true,
						'widgets' => $widgets,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_theme_options' );
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
