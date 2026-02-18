<?php
declare(strict_types=1);

namespace WpMcpUltimate\Abilities\Content;

use WpMcpUltimate\Abilities\Helpers;

/**
 * Search ability: global content search across post types.
 */
class Search {

	/**
	 * Register the search ability.
	 */
	public static function register(): void {

		// =====================================================================
		// SEARCH - Global Search
		// =====================================================================
		wp_register_ability(
			'content/search',
			array(
				'label'               => 'Search Content',
				'description'         => 'Search content. Params: query (required), type (optional: post/page/media), per_page.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'query' ),
					'properties'           => array(
						'query'      => array(
							'type'        => 'string',
							'description' => 'Search query.',
						),
						'post_types' => array(
							'type'        => 'array',
							'items'       => array( 'type' => 'string' ),
							'default'     => array( 'post', 'page' ),
							'description' => 'Post types to search (e.g., ["post", "page", "attachment"]).',
						),
						'per_page'   => array(
							'type'    => 'integer',
							'default' => 10,
							'minimum' => 1,
							'maximum' => 50,
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'results' => array( 'type' => 'array' ),
						'total'   => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					if ( empty( $input['query'] ) ) {
						return array( 'results' => array(), 'total' => 0 );
					}

					$query = new \WP_Query( array(
						's'                      => $input['query'],
						'post_type'              => $input['post_types'] ?? array( 'post', 'page' ),
						'post_status'            => 'publish',
						'posts_per_page'         => $input['per_page'] ?? 10,
						// Performance optimizations.
						'no_found_rows'          => true,
						'update_post_term_cache' => false,
						'update_post_meta_cache' => false,
					) );

					$results = array();
					foreach ( $query->posts as $post ) {
						$results[] = array(
							'id'        => $post->ID,
							'title'     => $post->post_title,
							'type'      => $post->post_type,
							'excerpt'   => wp_trim_words( $post->post_excerpt ?: $post->post_content, 20 ),
							'link'      => get_permalink( $post->ID ),
							'date'      => $post->post_date,
						);
					}

					return array(
						'results' => $results,
						'total'   => (int) $query->found_posts,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'read' );
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
