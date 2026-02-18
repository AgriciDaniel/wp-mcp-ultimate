<?php
declare(strict_types=1);

namespace WpMcpUltimate\Abilities\Content;

use WpMcpUltimate\Abilities\Helpers;

/**
 * Page content abilities: list, get, create, update, delete, patch.
 */
class Pages {

	/**
	 * Register all page-related abilities.
	 */
	public static function register(): void {

		// =====================================================================
		// PAGES - List
		// =====================================================================
		wp_register_ability(
			'content/list-pages',
			array(
				'label'               => 'List Pages',
				'description'         => 'List pages. Params: status, per_page, page, orderby, order, search, parent_id (all optional).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'status'   => array(
							'type'    => 'string',
							'enum'    => array( 'publish', 'draft', 'pending', 'private', 'any' ),
							'default' => 'publish',
						),
						'per_page' => array(
							'type'    => 'integer',
							'default' => 20,
							'minimum' => 1,
							'maximum' => 100,
						),
						'include_totals' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => 'Include total counts (disables no_found_rows optimization).',
						),
						'page'     => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'parent'   => array(
							'type'        => 'integer',
							'description' => 'Filter by parent page ID. Use 0 for top-level pages.',
						),
						'orderby'  => array(
							'type'    => 'string',
							'enum'    => array( 'title', 'date', 'modified', 'menu_order', 'ID' ),
							'default' => 'menu_order',
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
						'pages'       => array( 'type' => 'array' ),
						'returned'    => array( 'type' => 'integer' ),
						'has_more'    => array( 'type' => 'boolean' ),
						'total'       => array( 'type' => array( 'integer', 'null' ) ),
						'total_pages' => array( 'type' => array( 'integer', 'null' ) ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					$pagination = Helpers::parse_pagination( $input, 20, 100 );
					$include_totals = ! empty( $input['include_totals'] );
					$args = array(
						'post_type'              => 'page',
						'post_status'            => $input['status'] ?? 'publish',
						'posts_per_page'         => $pagination['per_page'],
						'paged'                  => $pagination['page'],
						'orderby'                => $input['orderby'] ?? 'menu_order',
						'order'                  => $input['order'] ?? 'ASC',
						// Performance optimizations.
						'no_found_rows'          => ! $include_totals,
						'update_post_term_cache' => false,
						'update_post_meta_cache' => false,
					);

					if ( 'any' === $args['post_status'] ) {
						$args['post_status'] = array( 'publish', 'draft', 'pending', 'private' );
					}

					if ( isset( $input['parent'] ) ) {
						$args['post_parent'] = $input['parent'];
					}

					$query = new \WP_Query( $args );
					$pages = array();

					foreach ( $query->posts as $page ) {
						$pages[] = array(
							'id'         => $page->ID,
							'title'      => $page->post_title,
							'slug'       => $page->post_name,
							'status'     => $page->post_status,
							'parent_id'  => $page->post_parent,
							'menu_order' => $page->menu_order,
							'date'       => $page->post_date,
							'modified'   => $page->post_modified,
							'link'       => get_permalink( $page->ID ),
						);
					}

					$returned = count( $pages );
					$total = $include_totals ? (int) $query->found_posts : null;
					$total_pages = $include_totals ? (int) $query->max_num_pages : null;
					$has_more = $include_totals
						? $pagination['page'] < (int) $query->max_num_pages
						: $returned === $pagination['per_page'];

					return array(
						'pages'       => $pages,
						'returned'    => $returned,
						'has_more'    => $has_more,
						'total'       => $total,
						'total_pages' => $total_pages,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_pages' );
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

		// =====================================================================
		// PAGES - Get
		// =====================================================================
		wp_register_ability(
			'content/get-page',
			array(
				'label'               => 'Get Page',
				'description'         => 'Get single page. Params: id or slug (one required).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'   => array(
							'type'        => 'integer',
							'description' => 'Page ID to retrieve.',
						),
						'slug' => array(
							'type'        => 'string',
							'description' => 'Page slug to retrieve (used if ID not provided).',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'        => array( 'type' => 'boolean' ),
						'id'             => array( 'type' => 'integer' ),
						'title'          => array( 'type' => 'string' ),
						'slug'           => array( 'type' => 'string' ),
						'status'         => array( 'type' => 'string' ),
						'content'        => array( 'type' => 'string' ),
						'excerpt'        => array( 'type' => 'string' ),
						'parent_id'      => array( 'type' => 'integer' ),
						'menu_order'     => array( 'type' => 'integer' ),
						'template'       => array( 'type' => 'string' ),
						'date'           => array( 'type' => 'string' ),
						'modified'       => array( 'type' => 'string' ),
						'author_id'      => array( 'type' => 'integer' ),
						'author_name'    => array( 'type' => 'string' ),
						'featured_image' => array( 'type' => 'string' ),
						'link'           => array( 'type' => 'string' ),
						'message'        => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();
					$page  = null;

					if ( ! empty( $input['id'] ) ) {
						$page = get_post( $input['id'] );
						if ( $page && 'page' !== $page->post_type ) {
							$page = null;
						}
					} elseif ( ! empty( $input['slug'] ) ) {
						$page = get_page_by_path( $input['slug'] );
					}

					if ( ! $page ) {
						return array( 'success' => false, 'message' => esc_html__( 'Page not found', 'wp-mcp-ultimate' ) );
					}

					if ( ! current_user_can( 'read_post', $page->ID ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Permission denied', 'wp-mcp-ultimate' ) );
					}

					$author    = get_user_by( 'id', $page->post_author );
					$thumbnail = get_the_post_thumbnail_url( $page->ID, 'full' );
					$template  = get_page_template_slug( $page->ID );

					return array(
						'success'        => true,
						'id'             => $page->ID,
						'title'          => $page->post_title,
						'slug'           => $page->post_name,
						'status'         => $page->post_status,
						'content'        => $page->post_content,
						'excerpt'        => $page->post_excerpt,
						'parent_id'      => (int) $page->post_parent,
						'menu_order'     => (int) $page->menu_order,
						'template'       => $template ?: 'default',
						'date'           => $page->post_date,
						'modified'       => $page->post_modified,
						'author_id'      => (int) $page->post_author,
						'author_name'    => $author ? $author->display_name : '',
						'featured_image' => $thumbnail ?: '',
						'link'           => get_permalink( $page->ID ),
						'message'        => 'Page retrieved successfully',
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_pages' );
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

		// =====================================================================
		// PAGES - Create
		// =====================================================================
		wp_register_ability(
			'content/create-page',
			array(
				'label'               => 'Create Page',
				'description'         => 'Create page. Params: title (required), content, excerpt, status, slug, parent_id, menu_order, template.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'title' ),
					'properties'           => array(
						'title'      => array(
							'type'        => 'string',
							'description' => 'Page title.',
						),
						'content'    => array(
							'type'        => 'string',
							'description' => 'Page content (supports Gutenberg blocks).',
						),
						'excerpt'    => array(
							'type'        => 'string',
							'description' => 'Page excerpt.',
						),
						'status'     => array(
							'type'        => 'string',
							'enum'        => array( 'publish', 'draft', 'pending', 'private' ),
							'default'     => 'draft',
							'description' => 'Page status.',
						),
						'slug'       => array(
							'type'        => 'string',
							'description' => 'Page slug (auto-generated from title if not provided).',
						),
						'parent'     => array(
							'type'        => 'integer',
							'description' => 'Parent page ID. Use 0 for top-level page.',
						),
						'menu_order' => array(
							'type'        => 'integer',
							'description' => 'Menu order for page sorting.',
						),
						'template'   => array(
							'type'        => 'string',
							'description' => 'Page template slug.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
						'link'    => array( 'type' => 'string' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					if ( empty( $input['title'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Title is required', 'wp-mcp-ultimate' ) );
					}

					$page_data = array(
						'post_type'    => 'page',
						'post_title'   => sanitize_text_field( $input['title'] ),
						'post_content' => $input['content'] ?? '',
						'post_excerpt' => $input['excerpt'] ?? '',
						'post_status'  => $input['status'] ?? 'draft',
					);

					if ( ! empty( $input['slug'] ) ) {
						$page_data['post_name'] = sanitize_title( $input['slug'] );
					}

					if ( isset( $input['parent'] ) ) {
						$page_data['post_parent'] = (int) $input['parent'];
					}

					if ( isset( $input['menu_order'] ) ) {
						$page_data['menu_order'] = (int) $input['menu_order'];
					}

					$page_id = wp_insert_post( $page_data, true );

					if ( is_wp_error( $page_id ) ) {
						return array( 'success' => false, 'message' => esc_html( $page_id->get_error_message() ) );
					}

					if ( ! empty( $input['template'] ) ) {
						update_post_meta( $page_id, '_wp_page_template', $input['template'] );
					}

					return array(
						'success' => true,
						'id'      => $page_id,
						'link'    => get_permalink( $page_id ),
						'message' => esc_html__( 'Page created successfully', 'wp-mcp-ultimate' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'publish_pages' );
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

		// =====================================================================
		// PAGES - Update
		// =====================================================================
		wp_register_ability(
			'content/update-page',
			array(
				'label'               => 'Update Page',
				'description'         => 'Update page. Params: id (required), title, content, excerpt, status, slug, parent_id, menu_order, template.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id'         => array(
							'type'        => 'integer',
							'description' => 'Page ID to update.',
						),
						'title'      => array(
							'type'        => 'string',
							'description' => 'New page title.',
						),
						'content'    => array(
							'type'        => 'string',
							'description' => 'New page content.',
						),
						'excerpt'    => array(
							'type'        => 'string',
							'description' => 'New page excerpt.',
						),
						'status'     => array(
							'type'        => 'string',
							'enum'        => array( 'publish', 'draft', 'pending', 'private' ),
							'description' => 'New page status.',
						),
						'slug'       => array(
							'type'        => 'string',
							'description' => 'New page slug.',
						),
						'parent'     => array(
							'type'        => 'integer',
							'description' => 'New parent page ID.',
						),
						'menu_order' => array(
							'type'        => 'integer',
							'description' => 'New menu order.',
						),
						'template'   => array(
							'type'        => 'string',
							'description' => 'New page template slug.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
						'link'    => array( 'type' => 'string' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					if ( empty( $input['id'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Page ID is required', 'wp-mcp-ultimate' ) );
					}

					$page = get_post( $input['id'] );
					if ( ! $page || 'page' !== $page->post_type ) {
						return array( 'success' => false, 'message' => esc_html__( 'Page not found', 'wp-mcp-ultimate' ) );
					}

					if ( ! current_user_can( 'delete_post', $page->ID ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Permission denied to delete this page.', 'wp-mcp-ultimate' ) );
					}

					if ( ! current_user_can( 'edit_post', $page->ID ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Permission denied to edit this page.', 'wp-mcp-ultimate' ) );
					}

					$page_data = array( 'ID' => $input['id'] );

					if ( isset( $input['title'] ) ) {
						$page_data['post_title'] = sanitize_text_field( $input['title'] );
					}
					if ( isset( $input['content'] ) ) {
						$page_data['post_content'] = $input['content'];
					}
					if ( isset( $input['excerpt'] ) ) {
						$page_data['post_excerpt'] = $input['excerpt'];
					}
					if ( isset( $input['status'] ) ) {
						$page_data['post_status'] = $input['status'];
					}
					if ( isset( $input['slug'] ) ) {
						$page_data['post_name'] = sanitize_title( $input['slug'] );
					}
					if ( isset( $input['parent'] ) ) {
						$page_data['post_parent'] = (int) $input['parent'];
					}
					if ( isset( $input['menu_order'] ) ) {
						$page_data['menu_order'] = (int) $input['menu_order'];
					}

					$result = wp_update_post( $page_data, true );

					if ( is_wp_error( $result ) ) {
						return array( 'success' => false, 'message' => esc_html( $result->get_error_message() ) );
					}

					if ( isset( $input['template'] ) ) {
						update_post_meta( $input['id'], '_wp_page_template', $input['template'] );
					}

					return array(
						'success' => true,
						'id'      => $input['id'],
						'link'    => get_permalink( $input['id'] ),
						'message' => esc_html__( 'Page updated successfully', 'wp-mcp-ultimate' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_pages' );
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

		// =====================================================================
		// PAGES - Delete
		// =====================================================================
		wp_register_ability(
			'content/delete-page',
			array(
				'label'               => 'Delete Page',
				'description'         => 'Delete page. Params: id (required), force (optional, true=permanent).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id'    => array(
							'type'        => 'integer',
							'description' => 'Page ID to delete.',
						),
						'force' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => 'If true, permanently deletes. If false, moves to trash.',
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
						return array( 'success' => false, 'message' => esc_html__( 'Page ID is required', 'wp-mcp-ultimate' ) );
					}

					$page = get_post( $input['id'] );
					if ( ! $page || 'page' !== $page->post_type ) {
						return array( 'success' => false, 'message' => esc_html__( 'Page not found', 'wp-mcp-ultimate' ) );
					}

					$force  = ! empty( $input['force'] );
					$result = wp_delete_post( $input['id'], $force );

					if ( ! $result ) {
						return array( 'success' => false, 'message' => esc_html__( 'Failed to delete page', 'wp-mcp-ultimate' ) );
					}

					$message = $force ? esc_html__( 'Page permanently deleted', 'wp-mcp-ultimate' ) : esc_html__( 'Page moved to trash', 'wp-mcp-ultimate' );
					return array( 'success' => true, 'message' => $message );
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'delete_pages' );
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

		// =====================================================================
		// PAGES - Patch (Find & Replace)
		// =====================================================================
		wp_register_ability(
			'content/patch-page',
			array(
				'label'               => 'Patch Page Content',
				'description'         => 'Patch page content. Params: id (required), find (required), replace (required), regex (optional).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id', 'find', 'replace' ),
					'properties'           => array(
						'id'      => array(
							'type'        => 'integer',
							'description' => 'Page ID to patch.',
						),
						'find'    => array(
							'type'        => 'string',
							'description' => 'String or regex pattern to find.',
						),
						'replace' => array(
							'type'        => 'string',
							'description' => 'Replacement string. Supports backreferences ($1, $2, etc.) when using regex.',
						),
						'regex'   => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => 'If true, treat "find" as a regex pattern.',
						),
						'limit'   => array(
							'type'        => 'integer',
							'default'     => -1,
							'description' => 'Maximum replacements (-1 for all). Only applies to non-regex mode.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'      => array( 'type' => 'boolean' ),
						'id'           => array( 'type' => 'integer' ),
						'replacements' => array( 'type' => 'integer' ),
						'message'      => array( 'type' => 'string' ),
						'link'         => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					if ( empty( $input['id'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Page ID is required', 'wp-mcp-ultimate' ) );
					}
					if ( ! isset( $input['find'] ) || '' === $input['find'] ) {
						return array( 'success' => false, 'message' => esc_html__( 'Find string is required', 'wp-mcp-ultimate' ) );
					}
					if ( ! isset( $input['replace'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Replace string is required', 'wp-mcp-ultimate' ) );
					}

					$page = get_post( $input['id'] );
					if ( ! $page || 'page' !== $page->post_type ) {
						return array( 'success' => false, 'message' => esc_html__( 'Page not found', 'wp-mcp-ultimate' ) );
					}

					$content   = $page->post_content;
					$find      = $input['find'];
					$replace   = $input['replace'];
					$use_regex = ! empty( $input['regex'] );
					$limit     = isset( $input['limit'] ) ? (int) $input['limit'] : -1;
					$count     = 0;

					if ( $use_regex ) {
						$new_content = preg_replace( $find, $replace, $content, -1, $count );
						if ( null === $new_content ) {
							return array( 'success' => false, 'message' => esc_html__( 'Invalid regex pattern', 'wp-mcp-ultimate' ) );
						}
					} else {
						if ( -1 === $limit ) {
							$new_content = str_replace( $find, $replace, $content, $count );
						} else {
							$new_content = preg_replace( '/' . preg_quote( $find, '/' ) . '/', $replace, $content, $limit, $count );
						}
					}

					if ( 0 === $count ) {
						return array(
							'success'      => true,
							'id'           => $input['id'],
							'replacements' => 0,
							'message'      => 'No matches found - content unchanged',
							'link'         => get_permalink( $input['id'] ),
						);
					}

					$result = wp_update_post( array(
						'ID'           => $input['id'],
						'post_content' => $new_content,
					), true );

					if ( is_wp_error( $result ) ) {
						return array( 'success' => false, 'message' => esc_html( $result->get_error_message() ) );
					}

					return array(
						'success'      => true,
						'id'           => $input['id'],
						'replacements' => $count,
						'message'      => "Successfully replaced {$count} occurrence(s)",
						'link'         => get_permalink( $input['id'] ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_pages' );
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
