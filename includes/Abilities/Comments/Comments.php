<?php
declare(strict_types=1);

namespace WpMcpUltimate\Abilities\Comments;

use WpMcpUltimate\Abilities\Helpers;

class Comments {
	public static function register(): void {
		// =========================================================================
		// COMMENTS - List
		// =========================================================================
		wp_register_ability(
			'comments/list',
			array(
				'label'               => 'List Comments',
				'description'         => 'List comments. Params: status, post_id, author_email, per_page, page, search (all optional).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'status'  => array(
							'type'        => 'string',
							'enum'        => array( 'approve', 'hold', 'spam', 'trash', 'all' ),
							'default'     => 'all',
							'description' => 'Filter by comment status. "approve" = approved, "hold" = pending moderation.',
						),
						'post_id' => array(
							'type'        => 'integer',
							'description' => 'Filter by post ID.',
						),
						'per_page' => array(
							'type'        => 'integer',
							'default'     => 20,
							'minimum'     => 1,
							'maximum'     => 100,
							'description' => 'Number of comments to return.',
						),
						'orderby' => array(
							'type'        => 'string',
							'enum'        => array( 'comment_date', 'comment_ID' ),
							'default'     => 'comment_date',
							'description' => 'Field to order by.',
						),
						'order'   => array(
							'type'        => 'string',
							'enum'        => array( 'ASC', 'DESC' ),
							'default'     => 'DESC',
							'description' => 'Sort order.',
						),
					),
					'additionalProperties' => false,
				),
				'execute_callback'    => function ( array $params ): array {
					$args = array(
						'number'  => $params['per_page'] ?? 20,
						'orderby' => $params['orderby'] ?? 'comment_date',
						'order'   => $params['order'] ?? 'DESC',
					);

					if ( ! empty( $params['status'] ) && 'all' !== $params['status'] ) {
						$args['status'] = $params['status'];
					}

					if ( ! empty( $params['post_id'] ) ) {
						$args['post_id'] = $params['post_id'];
					}

					$comments = get_comments( $args );
					$data     = array();

					foreach ( $comments as $comment ) {
						$data[] = array(
							'id'           => (int) $comment->comment_ID,
							'post_id'      => (int) $comment->comment_post_ID,
							'post_title'   => get_the_title( $comment->comment_post_ID ),
							'author'       => $comment->comment_author,
							'author_email' => $comment->comment_author_email,
							'content'      => $comment->comment_content,
							'status'       => wp_get_comment_status( $comment ),
							'date'         => $comment->comment_date,
							'parent'       => (int) $comment->comment_parent,
						);
					}

					return array(
						'success'  => true,
						'comments' => $data,
						'total'    => count( $data ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'moderate_comments' );
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
		// COMMENTS - Get
		// =========================================================================
		wp_register_ability(
			'comments/get',
			array(
				'label'               => 'Get Comment',
				'description'         => 'Get comment. Params: id (required).',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id' => array(
							'type'        => 'integer',
							'description' => 'The comment ID.',
						),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'            => function ( array $params ): array {
					$comment = get_comment( $params['id'] );

					if ( ! $comment ) {
						return array(
							'success' => false,
							'error'   => 'Comment not found.',
						);
					}

					if ( ! current_user_can( 'edit_comment', $comment->comment_ID ) ) {
						return array(
							'success' => false,
							'error'   => 'You do not have permission to access this comment.',
						);
					}

					return array(
						'success' => true,
						'comment' => array(
							'id'           => (int) $comment->comment_ID,
							'post_id'      => (int) $comment->comment_post_ID,
							'post_title'   => get_the_title( $comment->comment_post_ID ),
							'author'       => $comment->comment_author,
							'author_email' => $comment->comment_author_email,
							'author_url'   => $comment->comment_author_url,
							'author_ip'    => $comment->comment_author_IP,
							'content'      => $comment->comment_content,
							'status'       => wp_get_comment_status( $comment ),
							'date'         => $comment->comment_date,
							'parent'       => (int) $comment->comment_parent,
							'user_id'      => (int) $comment->user_id,
						),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'moderate_comments' );
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
		// COMMENTS - Approve/Update Status
		// =========================================================================
		wp_register_ability(
			'comments/update-status',
			array(
				'label'               => 'Update Comment Status',
				'description'         => 'Approves, holds, spams, or trashes a comment.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'     => array(
							'type'        => 'integer',
							'description' => 'The comment ID.',
						),
						'status' => array(
							'type'        => 'string',
							'enum'        => array( 'approve', 'hold', 'spam', 'trash' ),
							'description' => 'New status: approve (publish), hold (pending), spam, or trash.',
						),
					),
					'required'             => array( 'id', 'status' ),
					'additionalProperties' => false,
				),
				'execute_callback'            => function ( array $params ): array {
					$comment = get_comment( $params['id'] );

					if ( ! $comment ) {
						return array(
							'success' => false,
							'error'   => 'Comment not found.',
						);
					}

					if ( ! current_user_can( 'edit_comment', $comment->comment_ID ) ) {
						return array(
							'success' => false,
							'error'   => 'You do not have permission to update this comment.',
						);
					}

					// Map status to WordPress values.
					$status_map = array(
						'approve' => 1,
						'hold'    => 0,
						'spam'    => 'spam',
						'trash'   => 'trash',
					);

					$result = wp_set_comment_status( $params['id'], $status_map[ $params['status'] ] );

					if ( ! $result ) {
						return array(
							'success' => false,
							'error'   => 'Failed to update comment status.',
						);
					}

					return array(
						'success'    => true,
						'comment_id' => $params['id'],
						'new_status' => $params['status'],
						'message'    => 'Comment status updated.',
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'moderate_comments' );
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
		// COMMENTS - Reply
		// =========================================================================
		wp_register_ability(
			'comments/reply',
			array(
				'label'               => 'Reply to Comment',
				'description'         => 'Posts a reply to an existing comment.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'parent_id' => array(
							'type'        => 'integer',
							'description' => 'The parent comment ID to reply to.',
						),
						'content'   => array(
							'type'        => 'string',
							'description' => 'The reply content.',
						),
						'author'    => array(
							'type'        => 'string',
							'description' => 'Author name for the reply.',
						),
						'email'     => array(
							'type'        => 'string',
							'description' => 'Author email for the reply.',
						),
						'user_id'   => array(
							'type'        => 'integer',
							'description' => 'WordPress user ID to associate with the comment. Defaults to authenticated user.',
						),
					),
					'required'             => array( 'parent_id', 'content' ),
					'additionalProperties' => false,
				),
				'execute_callback'            => function ( array $params ): array {
					$parent = get_comment( $params['parent_id'] );

					if ( ! $parent ) {
						return array(
							'success' => false,
							'error'   => 'Parent comment not found.',
						);
					}

					if ( ! current_user_can( 'edit_comment', $parent->comment_ID ) ) {
						return array(
							'success' => false,
							'error'   => 'You do not have permission to reply to this comment.',
						);
					}

					$user = wp_get_current_user();

					// Use provided user_id or fall back to authenticated user.
					$comment_user_id = $params['user_id'] ?? $user->ID;
					$comment_user    = $comment_user_id !== $user->ID ? get_userdata( $comment_user_id ) : $user;

					if ( ! $comment_user && isset( $params['user_id'] ) ) {
						return array(
							'success' => false,
							'error'   => 'User ID ' . $params['user_id'] . ' not found.',
						);
					}

					if ( $comment_user_id !== $user->ID && ! current_user_can( 'edit_user', $comment_user_id ) ) {
						return array(
							'success' => false,
							'error'   => 'You do not have permission to post as this user.',
						);
					}

					$comment_data = array(
						'comment_post_ID'      => $parent->comment_post_ID,
						'comment_content'      => $params['content'],
						'comment_parent'       => $params['parent_id'],
						'comment_author'       => $params['author'] ?? $comment_user->display_name,
						'comment_author_email' => $params['email'] ?? $comment_user->user_email,
						'user_id'              => $comment_user_id,
						'comment_approved'     => 1,
					);

					$comment_id = wp_insert_comment( $comment_data );

					if ( ! $comment_id ) {
						return array(
							'success' => false,
							'error'   => 'Failed to create reply.',
						);
					}

					return array(
						'success'    => true,
						'comment_id' => $comment_id,
						'message'    => 'Reply posted successfully.',
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'moderate_comments' );
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
		// COMMENTS - Create
		// =========================================================================
		wp_register_ability(
			'comments/create',
			array(
				'label'               => 'Create Comment',
				'description'         => 'Create comment. Params: post_id, content (required), author, email, user_id, parent_id.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'post_id'   => array(
							'type'        => 'integer',
							'description' => 'The post ID to comment on.',
						),
						'content'   => array(
							'type'        => 'string',
							'description' => 'The comment content.',
						),
						'author'    => array(
							'type'        => 'string',
							'description' => 'Author name for the comment.',
						),
						'email'     => array(
							'type'        => 'string',
							'description' => 'Author email for the comment.',
						),
						'user_id'   => array(
							'type'        => 'integer',
							'description' => 'WordPress user ID to associate with the comment. Defaults to authenticated user.',
						),
						'parent_id' => array(
							'type'        => 'integer',
							'default'     => 0,
							'description' => 'Parent comment ID for threading (0 for top-level).',
						),
					),
					'required'             => array( 'post_id', 'content' ),
					'additionalProperties' => false,
				),
				'execute_callback'    => function ( array $params ): array {
					$post = get_post( $params['post_id'] );

					if ( ! $post ) {
						return array(
							'success' => false,
							'error'   => 'Post not found.',
						);
					}

					if ( ! current_user_can( 'edit_post', $post->ID ) ) {
						return array(
							'success' => false,
							'error'   => 'You do not have permission to comment on this post.',
						);
					}

					$user = wp_get_current_user();

					// Use provided user_id or fall back to authenticated user.
					$comment_user_id = $params['user_id'] ?? $user->ID;
					$comment_user    = $comment_user_id !== $user->ID ? get_userdata( $comment_user_id ) : $user;

					if ( ! $comment_user && isset( $params['user_id'] ) ) {
						return array(
							'success' => false,
							'error'   => 'User ID ' . $params['user_id'] . ' not found.',
						);
					}

					if ( $comment_user_id !== $user->ID && ! current_user_can( 'edit_user', $comment_user_id ) ) {
						return array(
							'success' => false,
							'error'   => 'You do not have permission to post as this user.',
						);
					}

					$comment_data = array(
						'comment_post_ID'      => $params['post_id'],
						'comment_content'      => $params['content'],
						'comment_parent'       => $params['parent_id'] ?? 0,
						'comment_author'       => $params['author'] ?? $comment_user->display_name,
						'comment_author_email' => $params['email'] ?? $comment_user->user_email,
						'user_id'              => $comment_user_id,
						'comment_approved'     => 1,
					);

					$comment_id = wp_insert_comment( $comment_data );

					if ( ! $comment_id ) {
						return array(
							'success' => false,
							'error'   => 'Failed to create comment.',
						);
					}

					return array(
						'success'    => true,
						'comment_id' => $comment_id,
						'message'    => 'Comment created successfully.',
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'moderate_comments' );
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
		// COMMENTS - Delete
		// =========================================================================
		wp_register_ability(
			'comments/delete',
			array(
				'label'               => 'Delete Comment',
				'description'         => 'Permanently deletes a comment.',
				'category'            => 'site',
				'input_schema'        => array(
					'type'                 => 'object',
					'properties'           => array(
						'id'    => array(
							'type'        => 'integer',
							'description' => 'The comment ID to delete.',
						),
						'force' => array(
							'type'        => 'boolean',
							'default'     => false,
							'description' => 'If true, permanently delete. If false, move to trash.',
						),
					),
					'required'             => array( 'id' ),
					'additionalProperties' => false,
				),
				'execute_callback'            => function ( array $params ): array {
					$comment = get_comment( $params['id'] );

					if ( ! $comment ) {
						return array(
							'success' => false,
							'error'   => 'Comment not found.',
						);
					}

					if ( ! current_user_can( 'edit_comment', $comment->comment_ID ) ) {
						return array(
							'success' => false,
							'error'   => 'You do not have permission to delete this comment.',
						);
					}

					$force  = $params['force'] ?? false;
					$result = wp_delete_comment( $params['id'], $force );

					if ( ! $result ) {
						return array(
							'success' => false,
							'error'   => 'Failed to delete comment.',
						);
					}

					return array(
						'success' => true,
						'message' => $force ? esc_html__( 'Comment permanently deleted.', 'wp-mcp-ultimate' ) : esc_html__( 'Comment moved to trash.', 'wp-mcp-ultimate' ),
					);
				},
				'permission_callback' => function (): bool {
					return current_user_can( 'moderate_comments' );
				},
				'meta'                => array(
					'annotations' => array(
						'readonly'    => false,
						'destructive' => true,
						'idempotent'  => true,
					),
				),
			)
		);
	}
}
