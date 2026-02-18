<?php
declare(strict_types=1);

namespace WpMcpUltimate\Abilities\Media;

use WpMcpUltimate\Abilities\Helpers;

class Media {
	public static function register(): void {
		// =========================================================================
		// MEDIA - List
		// =========================================================================
		wp_register_ability(
			'content/list-media',
			array(
				'label'               => 'List Media',
				'description'         => 'List media. Params: per_page, page, mime_type, search (all optional).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'per_page'  => array(
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
						'page'      => array(
							'type'    => 'integer',
							'default' => 1,
							'minimum' => 1,
						),
						'mime_type' => array(
							'type'        => 'string',
							'description' => 'Filter by MIME type (e.g., "image", "image/jpeg", "application/pdf").',
						),
						'search'    => array(
							'type'        => 'string',
							'description' => 'Search media by filename or title.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'media'       => array( 'type' => 'array' ),
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
						'post_type'              => 'attachment',
						'post_status'            => 'inherit',
						'posts_per_page'         => $pagination['per_page'],
						'paged'                  => $pagination['page'],
						'orderby'                => 'date',
						'order'                  => 'DESC',
						// Performance optimizations.
						'no_found_rows'          => ! $include_totals,
						'update_post_term_cache' => false,
						'update_post_meta_cache' => false,
					);

					if ( ! empty( $input['mime_type'] ) ) {
						$args['post_mime_type'] = $input['mime_type'];
					}
					if ( ! empty( $input['search'] ) ) {
						$args['s'] = $input['search'];
					}

					$query = new \WP_Query( $args );
					$media = array();

					foreach ( $query->posts as $item ) {
						$media[] = array(
							'id'        => $item->ID,
							'title'     => $item->post_title,
							'filename'  => basename( get_attached_file( $item->ID ) ),
							'mime_type' => $item->post_mime_type,
							'url'       => wp_get_attachment_url( $item->ID ),
							'date'      => $item->post_date,
							'alt_text'  => get_post_meta( $item->ID, '_wp_attachment_image_alt', true ),
						);
					}

					$returned = count( $media );
					$total = $include_totals ? (int) $query->found_posts : null;
					$total_pages = $include_totals ? (int) $query->max_num_pages : null;
					$has_more = $include_totals
						? $pagination['page'] < (int) $query->max_num_pages
						: $returned === $pagination['per_page'];

					return array(
						'media'       => $media,
						'returned'    => $returned,
						'has_more'    => $has_more,
						'total'       => $total,
						'total_pages' => $total_pages,
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'upload_files' );
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
		// MEDIA - Upload
		// =========================================================================
		wp_register_ability(
			'media/upload',
			array(
				'label'               => 'Upload Media',
				'description'         => 'Uploads a file to the media library from a URL.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'url' ),
					'properties'           => array(
						'url'         => array(
							'type'        => 'string',
							'description' => 'URL of the file to upload.',
						),
						'title'       => array(
							'type'        => 'string',
							'description' => 'Title for the media item.',
						),
						'caption'     => array(
							'type'        => 'string',
							'description' => 'Caption for the media item.',
						),
						'alt_text'    => array(
							'type'        => 'string',
							'description' => 'Alt text for images.',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'Description for the media item.',
						),
						'post_id'     => array(
							'type'        => 'integer',
							'description' => 'Post ID to attach the media to.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'id'      => array( 'type' => 'integer' ),
						'url'     => array( 'type' => 'string' ),
						'message' => array( 'type' => 'string' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					if ( empty( $input['url'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'URL is required', 'wp-mcp-ultimate' ) );
					}

					$post_id = $input['post_id'] ?? 0;

					// Download file to temp location.
					$tmp = download_url( $input['url'] );
					if ( is_wp_error( $tmp ) ) {
						/* translators: %s: Error message */
						return array( 'success' => false, 'message' => esc_html( $tmp->get_error_message() ) );
					}

					// Get filename from URL.
					$filename = basename( wp_parse_url( $input['url'], PHP_URL_PATH ) );
					if ( empty( $filename ) ) {
						$filename = 'uploaded-file';
					}

					$file_array = array(
						'name'     => $filename,
						'tmp_name' => $tmp,
					);

					// Upload to media library.
					$attachment_id = media_handle_sideload( $file_array, $post_id );

					// Clean up temp file.
					if ( file_exists( $tmp ) ) {
						wp_delete_file( $tmp );
					}

					if ( is_wp_error( $attachment_id ) ) {
						return array( 'success' => false, 'message' => esc_html( $attachment_id->get_error_message() ) );
					}

					// Update attachment metadata.
					if ( ! empty( $input['title'] ) ) {
						wp_update_post( array(
							'ID'         => $attachment_id,
							'post_title' => sanitize_text_field( $input['title'] ),
						) );
					}
					if ( ! empty( $input['caption'] ) ) {
						wp_update_post( array(
							'ID'           => $attachment_id,
							'post_excerpt' => sanitize_text_field( $input['caption'] ),
						) );
					}
					if ( ! empty( $input['description'] ) ) {
						wp_update_post( array(
							'ID'           => $attachment_id,
							'post_content' => sanitize_textarea_field( $input['description'] ),
						) );
					}
					if ( ! empty( $input['alt_text'] ) ) {
						update_post_meta( $attachment_id, '_wp_attachment_image_alt', sanitize_text_field( $input['alt_text'] ) );
					}

					return array(
						'success' => true,
						'id'      => $attachment_id,
						'url'     => wp_get_attachment_url( $attachment_id ),
						'message' => esc_html__( 'Media uploaded successfully', 'wp-mcp-ultimate' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'upload_files' );
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
		// MEDIA - Get
		// =========================================================================
		wp_register_ability(
			'media/get',
			array(
				'label'               => 'Get Media Item',
				'description'         => 'Get media. Params: id (required).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'Media attachment ID.',
						),
					),
					'additionalProperties' => false,
				),
				'output_schema'       => array(
					'type'       => 'object',
					'properties' => array(
						'success' => array( 'type' => 'boolean' ),
						'media'   => array( 'type' => 'object' ),
					),
				),
				'execute_callback'    => function ( $input = array() ): array {
					$input = is_array( $input ) ? $input : array();

					if ( empty( $input['id'] ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Media ID is required', 'wp-mcp-ultimate' ) );
					}

					$attachment = get_post( $input['id'] );
					if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
						return array( 'success' => false, 'message' => esc_html__( 'Media not found', 'wp-mcp-ultimate' ) );
					}

					if ( ! current_user_can( 'read_post', $attachment->ID ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Permission denied to view this media item.', 'wp-mcp-ultimate' ) );
					}

					$metadata = wp_get_attachment_metadata( $input['id'] );
					$sizes    = array();

					if ( ! empty( $metadata['sizes'] ) ) {
						foreach ( $metadata['sizes'] as $size => $data ) {
							$sizes[ $size ] = array(
								'url'    => wp_get_attachment_image_url( $input['id'], $size ),
								'width'  => $data['width'],
								'height' => $data['height'],
							);
						}
					}

					return array(
						'success' => true,
						'media'   => array(
							'id'          => $attachment->ID,
							'title'       => $attachment->post_title,
							'caption'     => $attachment->post_excerpt,
							'description' => $attachment->post_content,
							'alt_text'    => get_post_meta( $input['id'], '_wp_attachment_image_alt', true ),
							'mime_type'   => $attachment->post_mime_type,
							'url'         => wp_get_attachment_url( $input['id'] ),
							'date'        => $attachment->post_date,
							'modified'    => $attachment->post_modified,
							'author_id'   => (int) $attachment->post_author,
							'parent_id'   => (int) $attachment->post_parent,
							'width'       => $metadata['width'] ?? null,
							'height'      => $metadata['height'] ?? null,
							'file'        => $metadata['file'] ?? null,
							'sizes'       => $sizes,
						),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'upload_files' );
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
		// MEDIA - Update
		// =========================================================================
		wp_register_ability(
			'media/update',
			array(
				'label'               => 'Update Media Item',
				'description'         => 'Update media. Params: id (required), title, caption, alt_text, description.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id'          => array(
							'type'        => 'integer',
							'description' => 'Media attachment ID.',
						),
						'title'       => array(
							'type'        => 'string',
							'description' => 'New title.',
						),
						'caption'     => array(
							'type'        => 'string',
							'description' => 'New caption.',
						),
						'alt_text'    => array(
							'type'        => 'string',
							'description' => 'New alt text.',
						),
						'description' => array(
							'type'        => 'string',
							'description' => 'New description.',
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
						return array( 'success' => false, 'message' => esc_html__( 'Media ID is required', 'wp-mcp-ultimate' ) );
					}

					$attachment = get_post( $input['id'] );
					if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
						return array( 'success' => false, 'message' => esc_html__( 'Media not found', 'wp-mcp-ultimate' ) );
					}

					if ( ! current_user_can( 'edit_post', $attachment->ID ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Permission denied to update this media item.', 'wp-mcp-ultimate' ) );
					}

					$post_data = array( 'ID' => $input['id'] );

					if ( isset( $input['title'] ) ) {
						$post_data['post_title'] = sanitize_text_field( $input['title'] );
					}
					if ( isset( $input['caption'] ) ) {
						$post_data['post_excerpt'] = sanitize_text_field( $input['caption'] );
					}
					if ( isset( $input['description'] ) ) {
						$post_data['post_content'] = sanitize_textarea_field( $input['description'] );
					}

					$result = wp_update_post( $post_data, true );

					if ( is_wp_error( $result ) ) {
						return array( 'success' => false, 'message' => esc_html( $result->get_error_message() ) );
					}

					if ( isset( $input['alt_text'] ) ) {
						update_post_meta( $input['id'], '_wp_attachment_image_alt', sanitize_text_field( $input['alt_text'] ) );
					}

					return array(
						'success' => true,
						'message' => esc_html__( 'Media updated successfully', 'wp-mcp-ultimate' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'upload_files' );
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
		// MEDIA - Delete
		// =========================================================================
		wp_register_ability(
			'media/delete',
			array(
				'label'               => 'Delete Media Item',
				'description'         => 'Permanently deletes a media item and its files.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'required'             => array( 'id' ),
					'properties'           => array(
						'id'    => array(
							'type'        => 'integer',
							'description' => 'Media attachment ID.',
						),
						'force' => array(
							'type'        => 'boolean',
							'default'     => true,
							'description' => 'Force permanent deletion (default true for media).',
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
						return array( 'success' => false, 'message' => esc_html__( 'Media ID is required', 'wp-mcp-ultimate' ) );
					}

					$attachment = get_post( $input['id'] );
					if ( ! $attachment || 'attachment' !== $attachment->post_type ) {
						return array( 'success' => false, 'message' => esc_html__( 'Media not found', 'wp-mcp-ultimate' ) );
					}

					if ( ! current_user_can( 'delete_post', $attachment->ID ) ) {
						return array( 'success' => false, 'message' => esc_html__( 'Permission denied to delete this media item.', 'wp-mcp-ultimate' ) );
					}

					$force  = $input['force'] ?? true;
					$result = wp_delete_attachment( $input['id'], $force );

					if ( ! $result ) {
						return array( 'success' => false, 'message' => esc_html__( 'Failed to delete media', 'wp-mcp-ultimate' ) );
					}

					return array(
						'success' => true,
						'message' => esc_html__( 'Media deleted successfully', 'wp-mcp-ultimate' ),
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
	}
}
