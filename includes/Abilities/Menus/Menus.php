<?php
declare(strict_types=1);

namespace WpMcpUltimate\Abilities\Menus;

use WpMcpUltimate\Abilities\Helpers;

class Menus {
	public static function register(): void {
		// =========================================================================
		// MENUS - List
		// =========================================================================
		wp_register_ability(
			'menus/list',
			array(
				'label'               => 'List Menus',
				'description'         => 'List menus. No params.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => array( 'object', 'null' ),
					'properties'           => (object) array(),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'   => array( 'type' => 'boolean' ),
						'menus'     => array( 'type' => 'array' ),
						'locations' => array( 'type' => 'object' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$menus     = wp_get_nav_menus();
					$locations = get_nav_menu_locations();
					$registered_locations = get_registered_nav_menus();

					$menu_list = array();
					foreach ( $menus as $menu ) {
						$menu_list[] = array(
							'id'          => $menu->term_id,
							'name'        => $menu->name,
							'slug'        => $menu->slug,
							'description' => $menu->description,
							'count'       => $menu->count,
						);
					}

					$location_list = array();
					foreach ( $registered_locations as $location => $description ) {
						$location_list[ $location ] = array(
							'description' => $description,
							'menu_id'     => $locations[ $location ] ?? 0,
						);
					}

					return array(
						'success'   => true,
						'menus'     => $menu_list,
						'locations' => $location_list,
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
		// MENUS - Get Menu Items
		// =========================================================================
		wp_register_ability(
			'menus/get-items',
			array(
				'label'               => 'Get Menu Items',
				'description'         => 'Get menu items. Params: id or location (one required).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'       => array(
							'type'        => 'integer',
							'description' => 'Menu ID.',
						),
						'location' => array(
							'type'        => 'string',
							'description' => 'Menu location slug (used if ID not provided).',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'menu'    => array( 'type' => 'object' ),
						'items'   => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input   = is_array( $input ) ? $input : array();
					$menu_id = 0;

					if ( ! empty( $input['id'] ) ) {
						$menu_id = (int) $input['id'];
					} elseif ( ! empty( $input['location'] ) ) {
						$locations = get_nav_menu_locations();
						$menu_id   = $locations[ $input['location'] ] ?? 0;
					}

					if ( ! $menu_id ) {
						return array( 'success' => false, 'message' => esc_html__( 'Menu ID or location required', 'wp-mcp-ultimate' ) );
					}

					$menu = wp_get_nav_menu_object( $menu_id );
					if ( ! $menu ) {
						return array( 'success' => false, 'message' => esc_html__( 'Menu not found', 'wp-mcp-ultimate' ) );
					}

					$items      = wp_get_nav_menu_items( $menu_id );
					$item_list  = array();

					if ( $items ) {
						foreach ( $items as $item ) {
							$item_list[] = array(
								'id'          => $item->ID,
								'title'       => $item->title,
								'url'         => $item->url,
								'target'      => $item->target,
								'attr_title'  => $item->attr_title,
								'description' => $item->description,
								'classes'     => $item->classes,
								'xfn'         => $item->xfn,
								'parent'      => (int) $item->menu_item_parent,
								'order'       => (int) $item->menu_order,
								'object'      => $item->object,
								'object_id'   => (int) $item->object_id,
								'type'        => $item->type,
							);
						}
					}

					return array(
						'success' => true,
						'menu'    => array(
							'id'    => $menu->term_id,
							'name'  => $menu->name,
							'slug'  => $menu->slug,
							'count' => $menu->count,
						),
						'items'   => $item_list,
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
		// MENUS - Create Menu
		// =========================================================================
		wp_register_ability(
			'menus/create',
			array(
				'label'               => 'Create Menu',
				'description'         => 'Create menu. Params: name (required).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'name' ),
					'properties'           => array(
						'name' => array(
							'type'        => 'string',
							'description' => 'Menu name.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					if ( empty( $input['name'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Menu name is required', 'wp-mcp-ultimate' ) );
					}

					$menu_id = wp_create_nav_menu( sanitize_text_field( $input['name'] ) );

					if ( is_wp_error( $menu_id ) ) {
						return array( 'success' => false, 'message' => esc_html( $menu_id->get_error_message() ) );
					}

					return array(
						'success' => true,
						'id'      => $menu_id,
						'message' => esc_html__( 'Menu created successfully', 'wp-mcp-ultimate' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_theme_options' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);

		// =========================================================================
		// MENUS - Add Menu Item
		// =========================================================================
		wp_register_ability(
			'menus/add-item',
			array(
				'label'               => 'Add Menu Item',
				'description'         => 'Adds a new item to a navigation menu.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'menu_id', 'title' ),
					'properties'           => array(
						'menu_id'   => array(
							'type'        => 'integer',
							'description' => 'Menu ID to add item to.',
						),
						'title'     => array(
							'type'        => 'string',
							'description' => 'Menu item title.',
						),
						'url'       => array(
							'type'        => 'string',
							'description' => 'URL for custom links.',
						),
						'object'    => array(
							'type'        => 'string',
							'description' => 'Object type (page, post, category, custom).',
							'default'     => 'custom',
						),
						'object_id' => array(
							'type'        => 'integer',
							'description' => 'Object ID (for pages/posts/categories).',
						),
						'parent'    => array(
							'type'        => 'integer',
							'description' => 'Parent menu item ID (for submenus).',
							'default'     => 0,
						),
						'position'  => array(
							'type'        => 'integer',
							'description' => 'Menu position/order.',
						),
						'target'    => array(
							'type'        => 'string',
							'enum'        => array( '', '_blank' ),
							'description' => 'Link target (_blank for new window).',
						),
						'classes'   => array(
							'type'        => 'string',
							'description' => 'CSS classes (space-separated).',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					if ( empty( $input['menu_id'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Menu ID is required', 'wp-mcp-ultimate' ) );
					}
					if ( empty( $input['title'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Title is required', 'wp-mcp-ultimate' ) );
					}

					$menu = wp_get_nav_menu_object( $input['menu_id'] );
					if ( ! $menu ) {
						return array( 'success' => false, 'message' => esc_html__( 'Menu not found', 'wp-mcp-ultimate' ) );
					}

					$object    = $input['object'] ?? 'custom';
					$object_id = $input['object_id'] ?? 0;
					$type      = 'custom';

					if ( 'page' === $object ) {
						$type = 'post_type';
					} elseif ( 'post' === $object ) {
						$type = 'post_type';
					} elseif ( 'category' === $object ) {
						$type      = 'taxonomy';
						$object    = 'category';
					}

					$item_data = array(
						'menu-item-title'     => sanitize_text_field( $input['title'] ),
						'menu-item-url'       => $input['url'] ?? '',
						'menu-item-object'    => $object,
						'menu-item-object-id' => $object_id,
						'menu-item-type'      => $type,
						'menu-item-parent-id' => $input['parent'] ?? 0,
						'menu-item-position'  => $input['position'] ?? 0,
						'menu-item-target'    => $input['target'] ?? '',
						'menu-item-classes'   => $input['classes'] ?? '',
						'menu-item-status'    => 'publish',
					);

					$item_id = wp_update_nav_menu_item( $input['menu_id'], 0, $item_data );

					if ( is_wp_error( $item_id ) ) {
						return array( 'success' => false, 'message' => esc_html( $item_id->get_error_message() ) );
					}

					return array(
						'success' => true,
						'id'      => $item_id,
						'message' => esc_html__( 'Menu item added successfully', 'wp-mcp-ultimate' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_theme_options' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => false,
						'idempotent'  => false,
					),
				),
			)
		);

		// =========================================================================
		// MENUS - Update Menu Item
		// =========================================================================
		wp_register_ability(
			'menus/update-item',
			array(
				'label'               => 'Update Menu Item',
				'description'         => 'Update menu item. Params: menu_id, item_id (required), title, url, parent, position, target, classes.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'menu_id', 'item_id' ),
					'properties'           => array(
						'menu_id'  => array(
							'type'        => 'integer',
							'description' => 'Menu ID.',
						),
						'item_id'  => array(
							'type'        => 'integer',
							'description' => 'Menu item ID to update.',
						),
						'title'    => array(
							'type'        => 'string',
							'description' => 'New title.',
						),
						'url'      => array(
							'type'        => 'string',
							'description' => 'New URL.',
						),
						'parent'   => array(
							'type'        => 'integer',
							'description' => 'New parent menu item ID.',
						),
						'position' => array(
							'type'        => 'integer',
							'description' => 'New position/order.',
						),
						'target'   => array(
							'type'        => 'string',
							'description' => 'Link target.',
						),
						'classes'  => array(
							'type'        => 'string',
							'description' => 'CSS classes.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					if ( empty( $input['menu_id'] ) || empty( $input['item_id'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Menu ID and item ID are required', 'wp-mcp-ultimate' ) );
					}

					$item = get_post( $input['item_id'] );
					if ( ! $item || 'nav_menu_item' !== $item->post_type ) {
						return array( 'success' => false, 'message' => esc_html__( 'Menu item not found', 'wp-mcp-ultimate' ) );
					}

					$item_data = array(
						'menu-item-status' => 'publish',
					);

					if ( isset( $input['title'] ) ) {
						$item_data['menu-item-title'] = sanitize_text_field( $input['title'] );
					}
					if ( isset( $input['url'] ) ) {
						$item_data['menu-item-url'] = esc_url_raw( $input['url'] );
					}
					if ( isset( $input['parent'] ) ) {
						$item_data['menu-item-parent-id'] = (int) $input['parent'];
					}
					if ( isset( $input['position'] ) ) {
						$item_data['menu-item-position'] = (int) $input['position'];
					}
					if ( isset( $input['target'] ) ) {
						$item_data['menu-item-target'] = $input['target'];
					}
					if ( isset( $input['classes'] ) ) {
						$item_data['menu-item-classes'] = $input['classes'];
					}

					$result = wp_update_nav_menu_item( $input['menu_id'], $input['item_id'], $item_data );

					if ( is_wp_error( $result ) ) {
						return array( 'success' => false, 'message' => esc_html( $result->get_error_message() ) );
					}

					return array(
						'success' => true,
						'message' => esc_html__( 'Menu item updated successfully', 'wp-mcp-ultimate' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_theme_options' );
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
		// MENUS - Delete Menu Item
		// =========================================================================
		wp_register_ability(
			'menus/delete-item',
			array(
				'label'               => 'Delete Menu Item',
				'description'         => 'Delete menu item. Params: item_id (required).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'item_id' ),
					'properties'           => array(
						'item_id' => array(
							'type'        => 'integer',
							'description' => 'Menu item ID to delete.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					if ( empty( $input['item_id'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Item ID is required', 'wp-mcp-ultimate' ) );
					}

					$item = get_post( $input['item_id'] );
					if ( ! $item || 'nav_menu_item' !== $item->post_type ) {
						return array( 'success' => false, 'message' => esc_html__( 'Menu item not found', 'wp-mcp-ultimate' ) );
					}

					$result = wp_delete_post( $input['item_id'], true );

					if ( ! $result ) {
						return array( 'success' => false, 'message' => esc_html__( 'Failed to delete menu item', 'wp-mcp-ultimate' ) );
					}

					return array(
						'success' => true,
						'message' => esc_html__( 'Menu item deleted successfully', 'wp-mcp-ultimate' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_theme_options' );
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
		// MENUS - Assign to Location
		// =========================================================================
		wp_register_ability(
			'menus/assign-location',
			array(
				'label'               => 'Assign Menu to Location',
				'description'         => 'Assigns a menu to a theme location.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'menu_id', 'location' ),
					'properties'           => array(
						'menu_id'  => array(
							'type'        => 'integer',
							'description' => 'Menu ID to assign (use 0 to unassign).',
						),
						'location' => array(
							'type'        => 'string',
							'description' => 'Theme location slug.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					if ( ! isset( $input['menu_id'] ) || empty( $input['location'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Menu ID and location are required', 'wp-mcp-ultimate' ) );
					}

					$registered = get_registered_nav_menus();
					if ( ! isset( $registered[ $input['location'] ] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Invalid menu location', 'wp-mcp-ultimate' ) );
					}

					$locations = get_nav_menu_locations();
					$locations[ $input['location'] ] = (int) $input['menu_id'];
					set_theme_mod( 'nav_menu_locations', $locations );

					$action = $input['menu_id'] > 0 ? 'assigned' : 'unassigned';
					return array(
						'success' => true,
						/* translators: %s: Action ("assigned" or "unassigned"). */
						'message' => esc_html( sprintf( __( 'Menu %s to location successfully', 'wp-mcp-ultimate' ), $action ) ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_theme_options' );
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
