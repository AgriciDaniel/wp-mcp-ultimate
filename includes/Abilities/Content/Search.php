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
						'results'  => array( 'type' => 'array' ),
						'returned' => array( 'type' => 'integer' ),
						'total'    => array( 'type' => 'integer' ),
						'has_more' => array( 'type' => 'boolean' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					if ( empty( $input['query'] ) ) {
						return array( 'results' => array(), 'returned' => 0, 'total' => 0, 'has_more' => false );
					}

					$per_page = isset( $input['per_page'] ) ? max( 1, min( 50, (int) $input['per_page'] ) ) : 10;

					$query = new \WP_Query( array(
						's'                      => $input['query'],
						'post_type'              => $input['post_types'] ?? array( 'post', 'page' ),
						'post_status'            => 'publish',
						'posts_per_page'         => $per_page,
						'no_found_rows'          => false,
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

					$returned = count( $results );

					return array(
						'results'  => $results,
						'returned' => $returned,
						'total'    => (int) $query->found_posts,
						'has_more' => $returned === $per_page && (int) $query->found_posts > $returned,
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
