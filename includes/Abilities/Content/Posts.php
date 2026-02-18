<?php
declare(strict_types=1);

namespace WpMcpUltimate\Abilities\Content;

use WpMcpUltimate\Abilities\Helpers;

/**
 * Post content abilities: list, get, create, update, delete, patch.
 */
class Posts {

	/**
	 * Register all post-related abilities.
	 */
	public static function register(): void {

		// =====================================================================
		// POSTS - List
		// =====================================================================
		wp_register_ability(
			'content/list-posts',
			array(
				'label'               => 'List Posts',
				'description'         => 'List posts. Params: status, per_page, page, orderby, order, search, category_id, author_id (all optional).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'status'      => array(
							'type'        => 'string',
							'enum'        => array( 'publish', 'draft', 'pending', 'private', 'future', 'any' ),
							'default'     => 'publish',
							'description' => 'Filter by post status.',
						),
						'post_type'   => array(
							'type'        => 'string',
							'default'     => 'post',
							'description' => 'Post type to list (default: post).',
						),
						'per_page'    => array(
							'type'        => 'integer',
							'default'     => 10,
							'minimum'     => 1,
							'maximum'     => 100,
							'description' => 'Number of posts to return.',
						),
						'include_totals' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => 'Include total counts (disables no_found_rows optimization).',
						),
						'page'        => array(
							'type'        => 'integer',
							'default'     => 1,
							'minimum'     => 1,
							'description' => 'Page number for pagination.',
						),
						'orderby'     => array(
							'type'        => 'string',
							'enum'        => array( 'date', 'modified', 'title', 'ID' ),
							'default'     => 'date',
							'description' => 'Field to order by.',
						),
						'order'       => array(
							'type'        => 'string',
							'enum'        => array( 'ASC', 'DESC' ),
							'default'     => 'DESC',
							'description' => 'Sort order.',
						),
						'search'      => array(
							'type'        => 'string',
							'description' => 'Search term to filter posts.',
						),
						'category_id' => array(
							'type'        => 'integer',
							'description' => 'Filter by category ID.',
						),
						'author_id'   => array(
							'type'        => 'integer',
							'description' => 'Filter by author ID.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'posts'       => array(
							'type'  => 'array',
							'items' => array(
								'type'       => 'object',
								'properties' => array(
									'id'       => array( 'type' => 'integer' ),
									'title'    => array( 'type' => 'string' ),
									'slug'     => array( 'type' => 'string' ),
									'status'   => array( 'type' => 'string' ),
									'date'     => array( 'type' => 'string' ),
									'modified' => array( 'type' => 'string' ),
									'excerpt'  => array( 'type' => 'string' ),
									'link'     => array( 'type' => 'string' ),
								),
							),
						),
						'returned'    => array( 'type' => 'integer' ),
						'has_more'    => array( 'type' => 'boolean' ),
						'total'       => array( 'type' => array( 'integer', 'null' ) ),
						'total_pages' => array( 'type' => array( 'integer', 'null' ) ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					$pagination = Helpers::parse_pagination( $input, 10, 100 );
					$include_totals = ! empty( $input['include_totals'] );
					$post_type = sanitize_key( $input['post_type'] ?? 'post' );
					if ( ! post_type_exists( $post_type ) ) {
						/* translators: %s: Post type name */
						return array( 'success' => false, 'message' => esc_html__( 'Invalid post_type: ', 'wp-mcp-ultimate' ) . esc_html( $post_type ) );
					}

					$args = array(
						'post_type'              => $post_type,
						'post_status'            => $input['status'] ?? 'publish',
						'posts_per_page'         => $pagination['per_page'],
						'paged'                  => $pagination['page'],
						'orderby'                => $input['orderby'] ?? 'date',
						'order'                  => $input['order'] ?? 'DESC',
						// Performance optimizations.
						'no_found_rows'          => ! $include_totals,
						'update_post_term_cache' => false,
						'update_post_meta_cache' => false,
					);

					if ( 'any' === $args['post_status'] ) {
						$args['post_status'] = array( 'publish', 'draft', 'pending', 'private', 'future' );
					}

					if ( ! empty( $input['search'] ) ) {
						$args['s'] = $input['search'];
					}
					if ( ! empty( $input['category_id'] ) ) {
						$args['cat'] = $input['category_id'];
					}
					if ( ! empty( $input['author_id'] ) ) {
						$args['author'] = $input['author_id'];
					}

					$query = new \WP_Query( $args );
					$posts = array();

					foreach ( $query->posts as $post ) {
						$posts[] = array(
							'id'       => $post->ID,
							'title'    => $post->post_title,
							'slug'     => $post->post_name,
							'status'   => $post->post_status,
							'date'     => $post->post_date,
							'modified' => $post->post_modified,
							'excerpt'  => wp_trim_words( $post->post_excerpt ?: $post->post_content, 30 ),
							'link'     => get_permalink( $post->ID ),
						);
					}

					$returned = count( $posts );
					$total = $include_totals ? (int) $query->found_posts : null;
					$total_pages = $include_totals ? (int) $query->max_num_pages : null;
					$has_more = $include_totals
						? $pagination['page'] < (int) $query->max_num_pages
						: $returned === $pagination['per_page'];

					return array(
						'posts'       => $posts,
						'returned'    => $returned,
						'has_more'    => $has_more,
						'total'       => $total,
						'total_pages' => $total_pages,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_posts' );
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
		// POSTS - Get Single
		// =====================================================================
		wp_register_ability(
			'content/get-post',
			array(
				'label'               => 'Get Post',
				'description'         => 'Get single post. Params: id or slug (one required).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'   => array(
							'type'        => 'integer',
							'description' => 'Post ID to retrieve.',
						),
						'slug' => array(
							'type'        => 'string',
							'description' => 'Post slug to retrieve (used if ID not provided).',
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
						'date'           => array( 'type' => 'string' ),
						'modified'       => array( 'type' => 'string' ),
						'author_id'      => array( 'type' => 'integer' ),
						'author_name'    => array( 'type' => 'string' ),
						'categories'     => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
						'tags'           => array( 'type' => 'array', 'items' => array( 'type' => 'object' ) ),
						'featured_image' => array( 'type' => 'string' ),
						'link'           => array( 'type' => 'string' ),
						'message'        => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();
					$post  = null;

					if ( ! empty( $input['id'] ) ) {
						$post = get_post( $input['id'] );
					} elseif ( ! empty( $input['slug'] ) ) {
						$posts = get_posts( array(
							'name'        => $input['slug'],
							'post_type'   => 'post',
							'post_status' => 'any',
							'numberposts' => 1,
						) );
						$post = $posts[0] ?? null;
					}

					if ( ! $post ) {
						return array( 'success' => false, 'message' => esc_html__( 'Post not found', 'wp-mcp-ultimate' ) );
					}

					if ( ! current_user_can( 'read_post', $post->ID ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Permission denied', 'wp-mcp-ultimate' ) );
					}

					$categories = wp_get_post_categories( $post->ID, array( 'fields' => 'all' ) );
					$tags       = wp_get_post_tags( $post->ID );
					$author     = get_user_by( 'id', $post->post_author );
					$thumbnail  = get_the_post_thumbnail_url( $post->ID, 'full' );

					return array(
						'success'        => true,
						'id'             => $post->ID,
						'title'          => $post->post_title,
						'slug'           => $post->post_name,
						'status'         => $post->post_status,
						'content'        => $post->post_content,
						'excerpt'        => $post->post_excerpt,
						'date'           => $post->post_date,
						'modified'       => $post->post_modified,
						'author_id'      => (int) $post->post_author,
						'author_name'    => $author ? $author->display_name : '',
						'categories'     => array_map( function ( $cat ) {
							return array( 'id' => $cat->term_id, 'name' => $cat->name, 'slug' => $cat->slug );
						}, $categories ),
						'tags'           => array_map( function ( $tag ) {
							return array( 'id' => $tag->term_id, 'name' => $tag->name, 'slug' => $tag->slug );
						}, $tags ),
						'featured_image' => $thumbnail ?: '',
						'link'           => get_permalink( $post->ID ),
						'message'        => 'Post retrieved successfully',
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_posts' );
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
		// POSTS - Create
		// =====================================================================
		wp_register_ability(
			'content/create-post',
			array(
				'label'               => 'Create Post',
				'description'         => 'Create post. Params: title (required), content, excerpt, status, slug, category_ids, tag_ids, date, author_id.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'title' ),
					'properties'           => array(
						'title'        => array(
							'type'        => 'string',
							'description' => 'Post title.',
						),
						'content'      => array(
							'type'        => 'string',
							'description' => 'Post content (supports Gutenberg blocks).',
						),
						'excerpt'      => array(
							'type'        => 'string',
							'description' => 'Post excerpt.',
						),
						'status'       => array(
							'type'        => 'string',
							'enum'        => array( 'publish', 'draft', 'pending', 'private', 'future' ),
							'default'     => 'draft',
							'description' => 'Post status.',
						),
						'slug'         => array(
							'type'        => 'string',
							'description' => 'Post slug (auto-generated from title if not provided).',
						),
						'category_ids' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => 'Array of category IDs.',
						),
						'tag_ids'      => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => 'Array of tag IDs.',
						),
						'date'         => array(
							'type'        => 'string',
							'description' => 'Post date (Y-m-d H:i:s format). For scheduled posts.',
						),
						'author_id'    => array(
							'type'        => 'integer',
							'description' => 'Author user ID. Defaults to current user.',
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

					if ( ! empty( $input['author_id'] ) ) {
						$author_id = intval( $input['author_id'] );
						if ( $author_id !== get_current_user_id() && ! current_user_can( 'edit_others_posts' ) ) {
							return array( 'success' => false, 'message' => esc_html__( 'Permission denied to set a different author.', 'wp-mcp-ultimate' ) );
						}
					}

					$post_data = array(
						'post_title'   => sanitize_text_field( $input['title'] ),
						'post_content' => $input['content'] ?? '',
						'post_excerpt' => $input['excerpt'] ?? '',
						'post_status'  => $input['status'] ?? 'draft',
						'post_type'    => 'post',
					);

					if ( ! empty( $input['slug'] ) ) {
						$post_data['post_name'] = sanitize_title( $input['slug'] );
					}
					if ( ! empty( $input['date'] ) ) {
						$post_data['post_date'] = $input['date'];
					}
					if ( ! empty( $input['author_id'] ) ) {
						$post_data['post_author'] = intval( $input['author_id'] );
					}

					$post_id = wp_insert_post( $post_data, true );

					if ( is_wp_error( $post_id ) ) {
						return array( 'success' => false, 'message' => esc_html( $post_id->get_error_message() ) );
					}

					if ( ! empty( $input['category_ids'] ) ) {
						wp_set_post_categories( $post_id, array_map( 'intval', $input['category_ids'] ) );
					}
					if ( ! empty( $input['tag_ids'] ) ) {
						wp_set_post_tags( $post_id, array_map( 'intval', $input['tag_ids'] ) );
					}

					return array(
						'success' => true,
						'id'      => $post_id,
						'link'    => get_permalink( $post_id ),
						'message' => esc_html__( 'Post created successfully', 'wp-mcp-ultimate' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'publish_posts' );
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
		// POSTS - Update
		// =====================================================================
		wp_register_ability(
			'content/update-post',
			array(
				'label'               => 'Update Post',
				'description'         => 'Update post. Params: id (required), title, content, excerpt, status, slug, category_ids, tag_ids, author_id.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id'           => array(
							'type'        => 'integer',
							'description' => 'Post ID to update.',
						),
						'title'        => array(
							'type'        => 'string',
							'description' => 'New post title.',
						),
						'content'      => array(
							'type'        => 'string',
							'description' => 'New post content.',
						),
						'excerpt'      => array(
							'type'        => 'string',
							'description' => 'New post excerpt.',
						),
						'status'       => array(
							'type'        => 'string',
							'enum'        => array( 'publish', 'draft', 'pending', 'private', 'future' ),
							'description' => 'New post status.',
						),
						'slug'         => array(
							'type'        => 'string',
							'description' => 'New post slug.',
						),
						'category_ids' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => 'New category IDs (replaces existing).',
						),
						'tag_ids'      => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'integer' ),
							'description' => 'New tag IDs (replaces existing).',
						),
						'author_id'    => array(
							'type'        => 'integer',
							'description' => 'New author user ID.',
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
						return array( 'success' => false, 'message' => esc_html__( 'Post ID is required', 'wp-mcp-ultimate' ) );
					}

					$post = get_post( $input['id'] );
					if ( ! $post ) {
						return array( 'success' => false, 'message' => esc_html__( 'Post not found', 'wp-mcp-ultimate' ) );
					}

					if ( ! current_user_can( 'edit_post', $post->ID ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Permission denied to edit this post.', 'wp-mcp-ultimate' ) );
					}

					$post_data = array( 'ID' => $input['id'] );

					if ( isset( $input['title'] ) ) {
						$post_data['post_title'] = sanitize_text_field( $input['title'] );
					}
					if ( isset( $input['content'] ) ) {
						$post_data['post_content'] = $input['content'];
					}
					if ( isset( $input['excerpt'] ) ) {
						$post_data['post_excerpt'] = $input['excerpt'];
					}
					if ( isset( $input['status'] ) ) {
						$post_data['post_status'] = $input['status'];
					}
					if ( isset( $input['slug'] ) ) {
						$post_data['post_name'] = sanitize_title( $input['slug'] );
					}
					if ( isset( $input['author_id'] ) ) {
						$author_id = intval( $input['author_id'] );
						if ( $author_id !== get_current_user_id() && ! current_user_can( 'edit_others_posts' ) ) {
							return array( 'success' => false, 'message' => esc_html__( 'Permission denied to change the author.', 'wp-mcp-ultimate' ) );
						}
						$post_data['post_author'] = $author_id;
					}

					$result = wp_update_post( $post_data, true );

					if ( is_wp_error( $result ) ) {
						return array( 'success' => false, 'message' => esc_html( $result->get_error_message() ) );
					}

					if ( isset( $input['category_ids'] ) ) {
						wp_set_post_categories( $input['id'], array_map( 'intval', $input['category_ids'] ) );
					}
					if ( isset( $input['tag_ids'] ) ) {
						wp_set_post_tags( $input['id'], array_map( 'intval', $input['tag_ids'] ) );
					}

					return array(
						'success' => true,
						'id'      => $input['id'],
						'link'    => get_permalink( $input['id'] ),
						'message' => esc_html__( 'Post updated successfully', 'wp-mcp-ultimate' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_posts' );
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
		// POSTS - Delete
		// =====================================================================
		wp_register_ability(
			'content/delete-post',
			array(
				'label'               => 'Delete Post',
				'description'         => 'Delete post. Params: id (required), force (optional, true=permanent).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id'         => array(
							'type'        => 'integer',
							'description' => 'Post ID to delete.',
						),
						'force'      => array(
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
						return array( 'success' => false, 'message' => esc_html__( 'Post ID is required', 'wp-mcp-ultimate' ) );
					}

					$post = get_post( $input['id'] );
					if ( ! $post ) {
						return array( 'success' => false, 'message' => esc_html__( 'Post not found', 'wp-mcp-ultimate' ) );
					}

					if ( ! current_user_can( 'delete_post', $post->ID ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Permission denied to delete this post.', 'wp-mcp-ultimate' ) );
					}

					$force  = ! empty( $input['force'] );
					$result = wp_delete_post( $input['id'], $force );

					if ( ! $result ) {
						return array( 'success' => false, 'message' => esc_html__( 'Failed to delete post', 'wp-mcp-ultimate' ) );
					}

					return array(
						'success' => true,
						'message' => $force ? esc_html__( 'Post permanently deleted', 'wp-mcp-ultimate' ) : esc_html__( 'Post moved to trash', 'wp-mcp-ultimate' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'delete_posts' );
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
		// POSTS - Patch (Find & Replace)
		// =====================================================================
		wp_register_ability(
			'content/patch-post',
			array(
				'label'               => 'Patch Post Content',
				'description'         => 'Patch post content. Params: id (required), find (required), replace (required), regex (optional).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id', 'find', 'replace' ),
					'properties'           => array(
						'id'      => array(
							'type'        => 'integer',
							'description' => 'Post ID to patch.',
						),
						'find'    => array(
							'type'        => 'string',
							'description' => 'String or regex pattern to find.',
						),
						'replace' => array(
							'type'        => 'string',
							'description' => 'Replacement string. For regex, supports backreferences ($1, $2, etc.).',
						),
						'regex'   => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => 'If true, treat "find" as a regex pattern.',
						),
						'limit'   => array(
							'type'        => 'integer',
							'default'     => -1,
							'description' => 'Max replacements (-1 for all). Only applies to non-regex mode.',
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
						return array( 'success' => false, 'message' => esc_html__( 'Post ID is required', 'wp-mcp-ultimate' ) );
					}
					if ( ! isset( $input['find'] ) || '' === $input['find'] ) {
						return array( 'success' => false, 'message' => esc_html__( 'Find string is required', 'wp-mcp-ultimate' ) );
					}
					if ( ! isset( $input['replace'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Replace string is required', 'wp-mcp-ultimate' ) );
					}

					$post = get_post( $input['id'] );
					if ( ! $post ) {
						return array( 'success' => false, 'message' => esc_html__( 'Post not found', 'wp-mcp-ultimate' ) );
					}

					$content     = $post->post_content;
					$find        = $input['find'];
					$replace     = $input['replace'];
					$use_regex   = ! empty( $input['regex'] );
					$limit       = $input['limit'] ?? -1;
					$count       = 0;

					if ( $use_regex ) {
						// Regex mode
						$new_content = preg_replace( $find, $replace, $content, -1, $count );
						if ( null === $new_content ) {
							return array( 'success' => false, 'message' => esc_html__( 'Invalid regex pattern', 'wp-mcp-ultimate' ) );
						}
					} else {
						// Plain text mode with optional limit
						if ( $limit === -1 ) {
							$new_content = str_replace( $find, $replace, $content, $count );
						} else {
							// Manual limited replacement
							$new_content = $content;
							$count       = 0;
							$pos         = 0;
							while ( $count < $limit && ( $pos = strpos( $new_content, $find, $pos ) ) !== false ) {
								$new_content = substr_replace( $new_content, $replace, $pos, strlen( $find ) );
								$pos        += strlen( $replace );
								$count++;
							}
						}
					}

					if ( $count === 0 ) {
						return array(
							'success'      => true,
							'id'           => $post->ID,
							'replacements' => 0,
							'message'      => 'No matches found - content unchanged',
							'link'         => get_permalink( $post->ID ),
						);
					}

					$result = wp_update_post(
						array(
							'ID'           => $post->ID,
							'post_content' => $new_content,
						),
						true
					);

					if ( is_wp_error( $result ) ) {
						return array( 'success' => false, 'message' => esc_html( $result->get_error_message() ) );
					}

					return array(
						'success'      => true,
						'id'           => $post->ID,
						'replacements' => $count,
						'message'      => "Successfully replaced {$count} occurrence(s)",
						'link'         => get_permalink( $post->ID ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'edit_posts' );
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
