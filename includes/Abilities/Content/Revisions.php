<?php
declare(strict_types=1);

namespace WpMcpUltimate\Abilities\Content;

use WpMcpUltimate\Abilities\Helpers;

/**
 * Revision abilities: list and get post/page revisions.
 */
class Revisions {

	/**
	 * Register all revision-related abilities.
	 */
	public static function register(): void {

		// =====================================================================
		// REVISIONS - List
		// =====================================================================
		wp_register_ability(
			'content/list-revisions',
			array(
				'label'               => 'List Revisions',
				'description'         => 'List revisions. Params: id (required), per_page.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id'       => array(
							'type'        => 'integer',
							'description' => 'Post/Page ID to get revisions for.',
						),
						'per_page' => array(
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
						'success'   => array( 'type' => 'boolean' ),
						'revisions' => array( 'type' => 'array' ),
						'total'     => array( 'type' => 'integer' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					if ( empty( $input['id'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Post/Page ID is required', 'wp-mcp-ultimate' ) );
					}

					$post = get_post( $input['id'] );
					if ( ! $post ) {
						return array( 'success' => false, 'message' => esc_html__( 'Post not found', 'wp-mcp-ultimate' ) );
					}

					$per_page  = $input['per_page'] ?? 10;
					$revisions = wp_get_post_revisions( $input['id'], array( 'posts_per_page' => $per_page ) );

					$result = array();
					foreach ( $revisions as $revision ) {
						$author = get_user_by( 'id', $revision->post_author );
						$result[] = array(
							'id'       => $revision->ID,
							'date'     => $revision->post_date,
							'modified' => $revision->post_modified,
							'author'   => $author ? $author->display_name : 'Unknown',
							'title'    => $revision->post_title,
						);
					}

					return array(
						'success'   => true,
						'revisions' => $result,
						'total'     => count( $result ),
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
		// REVISIONS - Get
		// =====================================================================
		wp_register_ability(
			'content/get-revision',
			array(
				'label'               => 'Get Revision',
				'description'         => 'Get revision. Params: id (required).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Revision ID to retrieve.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success'   => array( 'type' => 'boolean' ),
						'id'        => array( 'type' => 'integer' ),
						'parent_id' => array( 'type' => 'integer' ),
						'date'      => array( 'type' => 'string' ),
						'author'    => array( 'type' => 'string' ),
						'title'     => array( 'type' => 'string' ),
						'content'   => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					if ( empty( $input['id'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Revision ID is required', 'wp-mcp-ultimate' ) );
					}

					$revision = get_post( $input['id'] );
					if ( ! $revision || 'revision' !== $revision->post_type ) {
						return array( 'success' => false, 'message' => esc_html__( 'Revision not found', 'wp-mcp-ultimate' ) );
					}

					$author = get_user_by( 'id', $revision->post_author );

					return array(
						'success'   => true,
						'id'        => $revision->ID,
						'parent_id' => $revision->post_parent,
						'date'      => $revision->post_date,
						'modified'  => $revision->post_modified,
						'author'    => $author ? $author->display_name : 'Unknown',
						'title'     => $revision->post_title,
						'content'   => $revision->post_content,
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
	}
}
