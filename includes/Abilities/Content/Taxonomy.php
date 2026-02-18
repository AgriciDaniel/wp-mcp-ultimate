<?php
declare(strict_types=1);

namespace WpMcpUltimate\Abilities\Content;

use WpMcpUltimate\Abilities\Helpers;

/**
 * Taxonomy abilities: list/create categories and tags.
 */
class Taxonomy {

	/**
	 * Register all taxonomy-related abilities.
	 */
	public static function register(): void {

		// =====================================================================
		// CATEGORIES - List
		// =====================================================================
		wp_register_ability(
			'content/list-categories',
			array(
				'label'               => 'List Categories',
				'description'         => 'List categories. Params: hide_empty, parent (all optional).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'hide_empty' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => 'Hide categories with no posts.',
						),
						'parent'     => array(
							'type'        => 'integer',
							'description' => 'Filter by parent category ID. Use 0 for top-level.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'categories' => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					$args = array(
						'hide_empty' => $input['hide_empty'] ?? false,
					);

					if ( isset( $input['parent'] ) ) {
						$args['parent'] = $input['parent'];
					}

					$categories = get_categories( $args );

					return array(
						'categories' => array_map( function ( $cat ) {
							return array(
								'id'          => $cat->term_id,
								'name'        => $cat->name,
								'slug'        => $cat->slug,
								'description' => $cat->description,
								'parent_id'   => $cat->parent,
								'count'       => $cat->count,
							);
						}, $categories ),
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
		// CATEGORIES - Create
		// =====================================================================
		wp_register_ability(
			'content/create-category',
			array(
				'label'               => 'Create Category',
				'description'         => 'Create category. Params: name (required), slug, description, parent.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'name' ),
					'properties'           => array(
						'name'        => array(
							'type'        => 'string',
							'description' => 'The category name.',
						),
						'slug'        => array(
							'type'        => 'string',
							'description' => 'The category slug (optional, auto-generated from name if not provided).',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'The category description (optional).',
						),
						'parent'      => array(
							'type'        => 'integer',
							'description' => 'Parent category ID (optional). Use 0 for top-level.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
						'name'    => array( 'type' => 'string' ),
						'slug'    => array( 'type' => 'string' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input ): array {
					$args = array();

					if ( ! empty( $input['slug'] ) ) {
						$args['slug'] = $input['slug'];
					}

					if ( ! empty( $input['description'] ) ) {
						$args['description'] = $input['description'];
					}

					if ( isset( $input['parent'] ) ) {
						$args['parent'] = (int) $input['parent'];
					}

					$result = wp_insert_term( $input['name'], 'category', $args );

					if ( is_wp_error( $result ) ) {
						if ( $result->get_error_code() === 'term_exists' ) {
							$existing_term = get_term( $result->get_error_data(), 'category' );
							return array(
								'success' => true,
								'id'      => $existing_term->term_id,
								'name'    => $existing_term->name,
								'slug'    => $existing_term->slug,
								'message' => esc_html__( 'Category already exists.', 'wp-mcp-ultimate' ),
							);
						}
						return array(
							'success' => false,
							'message' => esc_html( $result->get_error_message() ),
						);
					}

					$term = get_term( $result['term_id'], 'category' );

					return array(
						'success' => true,
						'id'      => $term->term_id,
						'name'    => $term->name,
						'slug'    => $term->slug,
						'message' => esc_html__( 'Category created successfully.', 'wp-mcp-ultimate' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'manage_categories' );
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
		// TAGS - List
		// =====================================================================
		wp_register_ability(
			'content/list-tags',
			array(
				'label'               => 'List Tags',
				'description'         => 'List tags. Params: hide_empty, search (all optional).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'hide_empty' => array(
							'type'    => 'boolean',
							'default' => false,
						),
						'search'     => array(
							'type'        => 'string',
							'description' => 'Search tags by name.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'tags' => array( 'type' => 'array' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					$args = array(
						'hide_empty' => $input['hide_empty'] ?? false,
					);

					if ( ! empty( $input['search'] ) ) {
						$args['search'] = $input['search'];
					}

					$tags = get_tags( $args );

					return array(
						'tags' => array_map( function ( $tag ) {
							return array(
								'id'    => $tag->term_id,
								'name'  => $tag->name,
								'slug'  => $tag->slug,
								'count' => $tag->count,
							);
						}, $tags ?: array() ),
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
		// TAGS - Create
		// =====================================================================
		wp_register_ability(
			'content/create-tag',
			array(
				'label'               => 'Create Tag',
				'description'         => 'Create tag. Params: name (required), slug, description.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'name' ),
					'properties'           => array(
						'name'        => array(
							'type'        => 'string',
							'description' => 'The tag name.',
						),
						'slug'        => array(
							'type'        => 'string',
							'description' => 'The tag slug (optional, auto-generated from name if not provided).',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'The tag description (optional).',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
						'name'    => array( 'type' => 'string' ),
						'slug'    => array( 'type' => 'string' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input ): array {
					$args = array();

					if ( ! empty( $input['slug'] ) ) {
						$args['slug'] = $input['slug'];
					}

					if ( ! empty( $input['description'] ) ) {
						$args['description'] = $input['description'];
					}

					$result = wp_insert_term( $input['name'], 'post_tag', $args );

					if ( is_wp_error( $result ) ) {
						// Check if tag already exists
						if ( $result->get_error_code() === 'term_exists' ) {
							$existing_term = get_term( $result->get_error_data(), 'post_tag' );
							return array(
								'success' => true,
								'id'      => $existing_term->term_id,
								'name'    => $existing_term->name,
								'slug'    => $existing_term->slug,
								'message' => esc_html__( 'Tag already exists.', 'wp-mcp-ultimate' ),
							);
						}
						return array(
							'success' => false,
							'message' => esc_html( $result->get_error_message() ),
						);
					}

					$term = get_term( $result['term_id'], 'post_tag' );

					return array(
						'success' => true,
						'id'      => $term->term_id,
						'name'    => $term->name,
						'slug'    => $term->slug,
						'message' => esc_html__( 'Tag created successfully.', 'wp-mcp-ultimate' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'manage_categories' );
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
	}
}
