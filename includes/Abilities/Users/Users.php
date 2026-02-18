<?php
declare(strict_types=1);

namespace WpMcpUltimate\Abilities\Users;

use WpMcpUltimate\Abilities\Helpers;

class Users {
	public static function register(): void {
		// =========================================================================
		// USERS - List (content/list-users)
		// =========================================================================
		wp_register_ability(
			'content/list-users',
			array(
				'label'               => 'List Users',
				'description'         => 'List users. Params: role, per_page, page, search (all optional).',
				'category'            => 'user',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'role'     => array(
							'type'        => 'string',
							'description' => 'Filter by role (e.g., "administrator", "editor", "author").',
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'search'   => array(
							'type'        => 'string',
							'description' => 'Search users by name or email.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'users' => array( 'type' => 'array' ),
						'total' => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					$pagination = Helpers::parse_pagination( $input, 20, 100 );
					$args = array(
						'number' => $pagination['per_page'],
						'paged'  => $pagination['page'],
					);

					if ( ! empty( $input['role'] ) ) {
						$args['role'] = $input['role'];
					}
					if ( ! empty( $input['search'] ) ) {
						$args['search'] = '*' . $input['search'] . '*';
					}

					$user_query = new \WP_User_Query( $args );
					$users      = array();

					foreach ( $user_query->get_results() as $user ) {
						$users[] = array(
							'id'           => $user->ID,
							'username'     => $user->user_login,
							'email'        => $user->user_email,
							'display_name' => $user->display_name,
							'roles'        => $user->roles,
							'registered'   => $user->user_registered,
						);
					}

					return array(
						'users' => $users,
						'total' => (int) $user_query->get_total(),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'list_users' );
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
		// USERS - List (Extended)
		// =========================================================================
		wp_register_ability(
			'users/list',
			array(
				'label'               => 'List Users (Extended)',
				'description'         => 'List users extended. Params: role, per_page, page, orderby, order (all optional).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'role'     => array(
							'type'        => 'string',
							'description' => 'Filter by role (administrator, editor, author, contributor, subscriber).',
						),
						'per_page' => array(
							'type'        => 'integer',
							'default'     => 20,
							'minimum'     => 1,
							'maximum'     => 100,
						),
						'page'     => array(
							'type'        => 'integer',
							'default'     => 1,
							'minimum'     => 1,
						),
						'orderby'  => array(
							'type'    => 'string',
							'enum'    => array( 'ID', 'login', 'nicename', 'email', 'registered', 'display_name' ),
							'default' => 'display_name',
						),
						'order'    => array(
							'type'    => 'string',
							'enum'    => array( 'ASC', 'DESC' ),
							'default' => 'ASC',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'     => array( 'type' => 'boolean' ),
						'users'       => array( 'type' => 'array' ),
						'total'       => array( 'type' => 'integer' ),
						'total_pages' => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					$pagination = Helpers::parse_pagination( $input, 20, 100 );
					$args = array(
						'number'  => $pagination['per_page'],
						'paged'   => $pagination['page'],
						'orderby' => $input['orderby'] ?? 'display_name',
						'order'   => $input['order'] ?? 'ASC',
					);

					if ( ! empty( $input['role'] ) ) {
						$args['role'] = $input['role'];
					}

					$query = new \WP_User_Query( $args );
					$users = array();

					foreach ( $query->get_results() as $user ) {
						$users[] = array(
							'id'           => $user->ID,
							'login'        => $user->user_login,
							'email'        => $user->user_email,
							'display_name' => $user->display_name,
							'nicename'     => $user->user_nicename,
							'url'          => $user->user_url,
							'registered'   => $user->user_registered,
							'roles'        => $user->roles,
						);
					}

					$total = $query->get_total();
					return array(
						'success'     => true,
						'users'       => $users,
						'total'       => $total,
						'total_pages' => (int) ceil( $total / $pagination['per_page'] ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'list_users' );
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
		// USERS - Get
		// =========================================================================
		wp_register_ability(
			'users/get',
			array(
				'label'               => 'Get User',
				'description'         => 'Get user. Params: id, login, or email (one required).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'    => array(
							'type'        => 'integer',
							'description' => 'User ID.',
						),
						'login' => array(
							'type'        => 'string',
							'description' => 'Username (used if ID not provided).',
						),
						'email' => array(
							'type'        => 'string',
							'description' => 'Email address (used if ID and login not provided).',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'user'    => array( 'type' => 'object' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();
					$user  = null;

					if ( ! empty( $input['id'] ) ) {
						$user = get_user_by( 'id', $input['id'] );
					} elseif ( ! empty( $input['login'] ) ) {
						$user = get_user_by( 'login', $input['login'] );
					} elseif ( ! empty( $input['email'] ) ) {
						$user = get_user_by( 'email', $input['email'] );
					}

					if ( ! $user ) {
						return array( 'success' => false, 'message' => esc_html__( 'User not found', 'wp-mcp-ultimate' ) );
					}

					if ( ! current_user_can( 'edit_user', $user->ID ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Permission denied to view this user.', 'wp-mcp-ultimate' ) );
					}

					return array(
						'success' => true,
						'user'    => array(
							'id'           => $user->ID,
							'login'        => $user->user_login,
							'email'        => $user->user_email,
							'display_name' => $user->display_name,
							'first_name'   => $user->first_name,
							'last_name'    => $user->last_name,
							'nickname'     => $user->nickname,
							'nicename'     => $user->user_nicename,
							'url'          => $user->user_url,
							'description'  => $user->description,
							'registered'   => $user->user_registered,
							'roles'        => $user->roles,
							'caps'         => array_keys( array_filter( $user->allcaps ) ),
						),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'list_users' );
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
		// USERS - Create
		// =========================================================================
		wp_register_ability(
			'users/create',
			array(
				'label'               => 'Create User',
				'description'         => 'Create user. Params: username, email (required), password, first_name, last_name, display_name, role, url, description.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'username', 'email' ),
					'properties'           => array(
						'username'     => array(
							'type'        => 'string',
							'description' => 'Username (login name).',
						),
						'email'        => array(
							'type'        => 'string',
							'description' => 'Email address.',
						),
						'password'     => array(
							'type'        => 'string',
							'description' => 'Password (auto-generated if not provided).',
						),
						'first_name'   => array(
							'type'        => 'string',
							'description' => 'First name.',
						),
						'last_name'    => array(
							'type'        => 'string',
							'description' => 'Last name.',
						),
						'display_name' => array(
							'type'        => 'string',
							'description' => 'Display name.',
						),
						'role'         => array(
							'type'        => 'string',
							'description' => 'User role.',
							'default'     => 'subscriber',
						),
						'url'          => array(
							'type'        => 'string',
							'description' => 'User website URL.',
						),
						'description'  => array(
							'type'        => 'string',
							'description' => 'User bio/description.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'  => array( 'type' => 'boolean' ),
						'id'       => array( 'type' => 'integer' ),
						'message'  => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					if ( empty( $input['username'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Username is required', 'wp-mcp-ultimate' ) );
					}
					if ( empty( $input['email'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Email is required', 'wp-mcp-ultimate' ) );
					}

					$userdata = array(
						'user_login' => sanitize_user( $input['username'] ),
						'user_email' => sanitize_email( $input['email'] ),
						'user_pass'  => $input['password'] ?? wp_generate_password(),
						'role'       => $input['role'] ?? 'subscriber',
					);

					if ( ! empty( $input['first_name'] ) ) {
						$userdata['first_name'] = sanitize_text_field( $input['first_name'] );
					}
					if ( ! empty( $input['last_name'] ) ) {
						$userdata['last_name'] = sanitize_text_field( $input['last_name'] );
					}
					if ( ! empty( $input['display_name'] ) ) {
						$userdata['display_name'] = sanitize_text_field( $input['display_name'] );
					}
					if ( ! empty( $input['url'] ) ) {
						$userdata['user_url'] = esc_url_raw( $input['url'] );
					}
					if ( ! empty( $input['description'] ) ) {
						$userdata['description'] = sanitize_textarea_field( $input['description'] );
					}

					$user_id = wp_insert_user( $userdata );

					if ( is_wp_error( $user_id ) ) {
						return array( 'success' => false, 'message' => esc_html( $user_id->get_error_message() ) );
					}

					return array(
						'success' => true,
						'id'      => $user_id,
						'message' => esc_html__( 'User created successfully', 'wp-mcp-ultimate' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'create_users' );
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
		// USERS - Update
		// =========================================================================
		wp_register_ability(
			'users/update',
			array(
				'label'               => 'Update User',
				'description'         => 'Update user. Params: id (required), email, password, first_name, last_name, display_name, nickname, role, url, description.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id'           => array(
							'type'        => 'integer',
							'description' => 'User ID to update.',
						),
						'email'        => array(
							'type'        => 'string',
							'description' => 'New email address.',
						),
						'password'     => array(
							'type'        => 'string',
							'description' => 'New password.',
						),
						'first_name'   => array(
							'type'        => 'string',
							'description' => 'New first name.',
						),
						'last_name'    => array(
							'type'        => 'string',
							'description' => 'New last name.',
						),
						'display_name' => array(
							'type'        => 'string',
							'description' => 'New display name.',
						),
						'nickname'     => array(
							'type'        => 'string',
							'description' => 'New nickname.',
						),
						'role'         => array(
							'type'        => 'string',
							'description' => 'New role.',
						),
						'url'          => array(
							'type'        => 'string',
							'description' => 'New website URL.',
						),
						'description'  => array(
							'type'        => 'string',
							'description' => 'New bio/description.',
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

					if ( empty( $input['id'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'User ID is required', 'wp-mcp-ultimate' ) );
					}

					$user = get_user_by( 'id', $input['id'] );
					if ( ! $user ) {
						return array( 'success' => false, 'message' => esc_html__( 'User not found', 'wp-mcp-ultimate' ) );
					}

					if ( ! current_user_can( 'edit_user', $user->ID ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Permission denied to update this user.', 'wp-mcp-ultimate' ) );
					}

					$userdata = array( 'ID' => $input['id'] );

					if ( isset( $input['email'] ) ) {
						$userdata['user_email'] = sanitize_email( $input['email'] );
					}
					if ( isset( $input['password'] ) ) {
						$userdata['user_pass'] = $input['password'];
					}
					if ( isset( $input['first_name'] ) ) {
						$userdata['first_name'] = sanitize_text_field( $input['first_name'] );
					}
					if ( isset( $input['last_name'] ) ) {
						$userdata['last_name'] = sanitize_text_field( $input['last_name'] );
					}
					if ( isset( $input['display_name'] ) ) {
						$userdata['display_name'] = sanitize_text_field( $input['display_name'] );
					}
					if ( isset( $input['nickname'] ) ) {
						$userdata['nickname'] = sanitize_text_field( $input['nickname'] );
					}
					if ( isset( $input['role'] ) ) {
						if ( ! current_user_can( 'promote_user', $user->ID ) ) {
							return array( 'success' => false, 'message' => esc_html__( 'Permission denied to change user role.', 'wp-mcp-ultimate' ) );
						}
						$userdata['role'] = $input['role'];
					}
					if ( isset( $input['url'] ) ) {
						$userdata['user_url'] = esc_url_raw( $input['url'] );
					}
					if ( isset( $input['description'] ) ) {
						$userdata['description'] = sanitize_textarea_field( $input['description'] );
					}

					$result = wp_update_user( $userdata );

					if ( is_wp_error( $result ) ) {
						return array( 'success' => false, 'message' => esc_html( $result->get_error_message() ) );
					}

					return array(
						'success' => true,
						'message' => esc_html__( 'User updated successfully', 'wp-mcp-ultimate' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_users' );
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
		// USERS - Delete
		// =========================================================================
		wp_register_ability(
			'users/delete',
			array(
				'label'               => 'Delete User',
				'description'         => 'Delete user. Params: id (required), reassign_to.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id'          => array(
							'type'        => 'integer',
							'description' => 'User ID to delete.',
						),
						'reassign_to' => array(
							'type'        => 'integer',
							'description' => 'User ID to reassign content to. If not provided, content will be deleted.',
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

					if ( empty( $input['id'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'User ID is required', 'wp-mcp-ultimate' ) );
					}

					$user = get_user_by( 'id', $input['id'] );
					if ( ! $user ) {
						return array( 'success' => false, 'message' => esc_html__( 'User not found', 'wp-mcp-ultimate' ) );
					}

					if ( ! current_user_can( 'delete_user', $user->ID ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Permission denied to delete this user.', 'wp-mcp-ultimate' ) );
					}

					// Don't allow deleting yourself.
					if ( $input['id'] === get_current_user_id() ) {
						return array( 'success' => false, 'message' => esc_html__( 'Cannot delete your own account', 'wp-mcp-ultimate' ) );
					}

					$reassign = ! empty( $input['reassign_to'] ) ? (int) $input['reassign_to'] : null;
					$result   = wp_delete_user( $input['id'], $reassign );

					if ( ! $result ) {
						return array( 'success' => false, 'message' => esc_html__( 'Failed to delete user', 'wp-mcp-ultimate' ) );
					}

					return array(
						'success' => true,
						'message' => esc_html__( 'User deleted successfully', 'wp-mcp-ultimate' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'delete_users' );
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
	}
}
